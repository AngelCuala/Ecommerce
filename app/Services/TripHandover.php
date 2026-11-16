<?php

namespace App\Services;

use App\Models\LinehaulHandover;
use App\Models\LinehaulTrip;
use App\Models\LogisticsHub;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\QrCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The trip handover between a linehaul driver and a hub, by QR code:
 *
 *  Driver  shows a Handover QR (and a 6-digit code as a fallback) on the active trip. It names the trip, the driver,
 *          the truck and the hub of the current stage; no personal data. It is signed with the app key (HMAC) and
 *          changes every config('linehaul.handover_seconds') seconds; it is valid for that period and the next.
 *  Hub     scans it (or types the code) on "Scan driver QR": the trip must be at that hub's stage (loading at the
 *          origin, arrival at the destination), the driver active, the code fresh and not used before (a screenshot
 *          can't be reused). The hub sees the driver, truck, trip and expected parcels, scans the parcels on or off,
 *          and confirms: loading seals the truck (the driver can then depart), arrival closes receiving.
 *          A count that doesn't match needs the hub's note (10 to 300 characters).
 *  Every QR scan is a LinehaulHandover row and a line in the trip's log.
 */
class TripHandover
{
    public function __construct(private readonly Linehaul $linehaul) {}

    public static function period(): int
    {
        return (int) config('linehaul.handover_seconds', 45);
    }

    private static function window(): int
    {
        return intdiv(now()->getTimestamp(), self::period());
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** The handover the trip is waiting for: load (at the origin, before sealing) or receive (at the destination, after arrival). */
    public static function stageOf(LinehaulTrip $trip): ?string
    {
        if (in_array($trip->status, ['accepted', 'loading'], true) && ! $trip->sealed_at && ! in_array($trip->handover_status, ['pending', 'rejected'], true)) {
            return 'load';
        }

        return $trip->status === 'arrived' ? 'receive' : null;
    }

    public static function hubIdFor(LinehaulTrip $trip, string $stage): int
    {
        return (int) ($stage === 'load' ? $trip->origin_hub_id : $trip->destination_hub_id);
    }

    private static function sign(string $data): string
    {
        $key = (string) config('app.key');

        return hash_hmac('sha256', $data, str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key);
    }

    private static function payload(LinehaulTrip $trip, string $stage, int $window): string
    {
        return implode('|', [$trip->id, (int) $trip->courier_id, (string) $trip->courier?->plate_number, $stage, self::hubIdFor($trip, $stage), $window]);
    }

    private static function b36(int $n): string
    {
        return strtoupper(base_convert((string) $n, 10, 36));
    }

    /** HO-{trip}-{driver}-{L|R}{hub}-{period}-{signature}: capitals, digits and dashes only, so the waybill scanner reads it. */
    public static function qrFor(LinehaulTrip $trip, string $stage, int $window): string
    {
        return 'HO-' . self::b36($trip->id) . '-' . self::b36((int) $trip->courier_id) . '-' . ($stage === 'load' ? 'L' : 'R') . self::b36(self::hubIdFor($trip, $stage))
            . '-' . self::b36($window) . '-' . strtoupper(substr(self::sign('qr|' . self::payload($trip, $stage, $window)), 0, 16));
    }

    public static function codeFor(LinehaulTrip $trip, string $stage, int $window): string
    {
        $h = self::sign('code|' . self::payload($trip, $stage, $window));

        return str_pad((string) (hexdec(substr($h, 0, 12)) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** What the driver's phone shows: the QR (as SVG), the code, when it changes, and the load counts. */
    public function forDriver(LinehaulTrip $trip): array
    {
        $counts = $trip->counts();
        $stage = self::stageOf($trip);
        if (! $stage || ! $trip->courier_id) {
            return ['stage' => null, 'status' => $trip->status, 'status_label' => $trip->label(), 'counts' => $counts];
        }
        $w = self::window();
        $qr = self::qrFor($trip, $stage, $w);
        $hub = $stage === 'load' ? $trip->origin : $trip->destination;
        $changesAt = ($w + 1) * self::period();

        return [
            'stage' => $stage, 'stage_label' => LinehaulHandover::STAGES[$stage], 'status' => $trip->status, 'status_label' => $trip->label(),
            'hub' => $hub?->code, 'qr' => $qr, 'svg' => QrCode::svg($qr, 240), 'code' => self::codeFor($trip, $stage, $w),
            'changes_at' => \Illuminate\Support\Carbon::createFromTimestamp($changesAt)->toIso8601String(),
            'seconds_left' => max(1, $changesAt - now()->getTimestamp()), 'period' => self::period(), 'counts' => $counts,
        ];
    }

    /**
     * The hub scans the driver's QR (or types the 6-digit code) at one of its hubs. Returns the handover to work on;
     * refuses a code that is wrong, expired, already used, for another hub or stage, or whose driver isn't active.
     */
    public function verify(User $sc, int $hubId, string $input): LinehaulHandover
    {
        $hub = LogisticsHub::where('sorting_center_id', $sc->id)->find($hubId) ?? $this->fail('hub_id', 'Choose the hub you are at.');
        $input = strtoupper(preg_replace('/\s+/', '', $input));
        $now = self::window();

        if (preg_match('/^\d{6}$/', $input)) {
            [$trip, $stage, $w] = $this->byCode($hub, $input, $now);
            $method = 'code';
        } elseif (preg_match('/^HO-([0-9A-Z]+)-([0-9A-Z]+)-([LR])([0-9A-Z]+)-([0-9A-Z]+)-([0-9A-F]{16})$/', $input, $m)) {
            $trip = LinehaulTrip::with('courier', 'origin', 'destination')->find((int) base_convert($m[1], 36, 10))
                ?? $this->fail('code', 'This Handover QR is not for a trip ALVY knows. Ask the driver to show it again.');
            $stage = $m[3] === 'L' ? 'load' : 'receive';
            $w = (int) base_convert($m[5], 36, 10);
            if ((int) base_convert($m[2], 36, 10) !== (int) $trip->courier_id) {
                $this->fail('code', "This QR is from a driver who is no longer on {$trip->code}.");
            }
            if (! hash_equals(self::qrFor($trip, $stage, $w), $input)) {
                $this->fail('code', 'This is not a valid Handover QR. Ask the driver to open it again on their phone.');
            }
            if ($w > $now) {
                $this->fail('code', 'This Handover QR is not valid yet. Check the time on the driver\'s phone and scan again.');
            }
            if ($w < $now - 1) {
                $this->fail('code', 'This Handover QR has expired. Ask the driver to show the new one (it changes every ' . self::period() . ' seconds).');
            }
            $method = 'qr';
        } else {
            $this->fail('code', "That isn't a driver's Handover QR. Scan the QR on the driver's phone, or type the 6-digit code under it.");
        }

        $this->checkTrip($sc, $hub, $trip, $stage);

        return DB::transaction(function () use ($sc, $hub, $trip, $stage, $w, $method, $input) {
            $hash = hash('sha256', implode('|', [$method, $trip->id, $stage, $w, $input]));
            if (LinehaulHandover::where('token_hash', $hash)->exists()) {
                $this->fail('code', 'This ' . ($method === 'qr' ? 'QR' : 'code') . ' was already used. Ask the driver for the new one: it changes every ' . self::period() . ' seconds.');
            }
            // The driver hasn't tapped Start Loading yet: the hub's scan starts it.
            if ($stage === 'load' && $trip->status === 'accepted') {
                $trip = $this->linehaul->startLoading($trip->courier, $trip->id);
            }
            $counts = $trip->counts();
            try {
                $h = LinehaulHandover::create([
                    'linehaul_trip_id' => $trip->id, 'stage' => $stage, 'hub_id' => $hub->id, 'courier_id' => $trip->courier_id,
                    'plate_number' => $trip->courier?->plate_number, 'scanned_by' => $sc->id, 'method' => $method, 'token_hash' => $hash,
                    'expected' => $counts['expected'], 'weight_kg' => $counts['weight_kg'], 'verified_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $this->fail('code', 'This QR was already used. Ask the driver for the new one.');
            }
            $trip->log('handover_scanned', LinehaulHandover::STAGES[$stage] . ' at ' . $hub->code,
                ['new_value' => ($method === 'qr' ? 'QR' : '6-digit code') . " · {$counts['expected']} parcel(s) expected · {$counts['weight_kg']} kg"]);

            return $h;
        });
    }

    /** A typed 6-digit code: the trip at this hub, at a handover stage, whose current (or previous) code it is. */
    private function byCode(LogisticsHub $hub, string $code, int $now): array
    {
        $trips = LinehaulTrip::with('courier', 'origin', 'destination')->whereNotNull('courier_id')
            ->where(fn ($q) => $q->where(fn ($o) => $o->where('origin_hub_id', $hub->id)->whereIn('status', ['accepted', 'loading'])->whereNull('sealed_at'))
                ->orWhere(fn ($d) => $d->where('destination_hub_id', $hub->id)->where('status', 'arrived')))->get();
        $expired = false;
        foreach ($trips as $trip) {
            $stage = (int) $trip->origin_hub_id === $hub->id && $trip->status !== 'arrived' ? 'load' : 'receive';
            foreach ([$now, $now - 1] as $w) {
                if (hash_equals(self::codeFor($trip, $stage, $w), $code)) {
                    return [$trip, $stage, $w];
                }
            }
            foreach (range($now - 2, $now - 20) as $w) {
                $expired = $expired || hash_equals(self::codeFor($trip, $stage, $w), $code);
            }
        }
        $this->fail('code', $expired
            ? 'This code has expired. Ask the driver for the new one (it changes every ' . self::period() . ' seconds).'
            : "No trip at {$hub->code} matches code $code. Check the 6 digits on the driver's phone.");
    }

    /** The trip is at this hub's stage, for this company, and its driver is active. */
    private function checkTrip(User $sc, LogisticsHub $hub, LinehaulTrip $trip, string $stage): void
    {
        $tripHub = self::hubIdFor($trip, $stage);
        if ($tripHub !== (int) $hub->id) {
            $other = LogisticsHub::find($tripHub);
            $this->fail('code', "This QR is for the " . ($stage === 'load' ? 'loading' : 'arrival') . " at {$other?->code}, not {$hub->code}. {$trip->code} goes {$trip->route()}.");
        }
        $company = $stage === 'load' ? (int) $trip->sorting_center_id : $trip->receiverId();
        if ($company !== (int) $sc->id) {
            $this->fail('code', "{$trip->code} isn't " . ($stage === 'load' ? 'loading' : 'arriving') . ' at your hub.');
        }
        $current = self::stageOf($trip);
        if ($current !== $stage) {
            $this->fail('code', match (true) {
                $stage === 'load' && $trip->sealed_at && in_array($trip->status, ['accepted', 'loading'], true) => "{$trip->code} is already sealed (handover confirmed). The driver can depart.",
                $stage === 'load' && $trip->handover_status === 'pending' => "{$trip->code} is waiting for the receiving sorting center to accept it.",
                $stage === 'receive' && $trip->status === 'in_transit' => "{$trip->code} hasn't arrived yet: the driver marks Arrived first.",
                default => "{$trip->code} is {$trip->label()}: there is no " . ($stage === 'load' ? 'loading' : 'arrival') . ' handover now.',
            });
        }
        $c = $trip->courier;
        $user = $c?->user;
        if (! $c || $c->status !== 'approved' || ! $user || ! $user->isActive() || $user->role !== 'courier') {
            $this->fail('code', "The driver's account isn't active" . ($c ? ' (' . ($c->status === 'suspended' ? 'suspended' : $c->status) . ')' : '') . '. They can\'t hand over trips.');
        }
    }

    /** The handover is the hub's own and still open. */
    private function open(LinehaulHandover $h, User $sc): LinehaulTrip
    {
        abort_unless((int) $h->hub?->sorting_center_id === (int) $sc->id, 404);
        $trip = $h->trip()->with('courier', 'origin', 'destination')->firstOrFail();
        if ($h->confirmed_at) {
            $this->fail('tracking', "This handover was confirmed {$h->confirmed_at->diffForHumans()}.");
        }
        if (self::stageOf($trip) !== $h->stage) {
            $this->fail('tracking', "{$trip->code} is {$trip->label()} now. Scan the driver's QR again.");
        }

        return $trip;
    }

    /** A parcel scanned on (loading) or off (arrival) the truck during the handover. */
    public function scan(LinehaulHandover $h, User $sc, string $tracking): array
    {
        $trip = $this->open($h, $sc);
        if ($h->isLoad()) {
            $r = $this->linehaul->hubLoad($trip, $tracking);

            return ['message' => $r['message'], 'counts' => $trip->fresh()->counts()];
        }

        return $this->linehaul->hubReceive($trip, $tracking);
    }

    /**
     * The hub confirms. Loading: the truck is sealed (parcels not scanned come off the trip and stay at the hub);
     * arrival: receiving closes (parcels not scanned become Missing in Transit). A count that doesn't match needs a note.
     */
    public function confirm(LinehaulHandover $h, User $sc, ?string $note): LinehaulTrip
    {
        $trip = $this->open($h, $sc);
        $note = trim((string) $note) ?: null;
        $c = $trip->counts();
        [$done, $word] = $h->isLoad() ? [$c['loaded'], 'on the truck'] : [$c['unloaded'], 'taken off the truck'];
        if ($h->isLoad() && $done === 0) {
            $this->fail('note', 'Scan the parcels onto the truck first.');
        }
        if ($done !== $c['expected'] && (! $note || mb_strlen($note) < 10)) {
            $this->fail('note', "$done of {$c['expected']} parcels are $word. Write a note on why (10 to 300 characters) before " . ($h->isLoad() ? 'sealing' : 'confirming') . '.');
        }
        if ($note && mb_strlen($note) > 300) {
            $this->fail('note', 'Keep the note to 300 characters.');
        }

        return DB::transaction(function () use ($h, $trip, $note, $c, $done) {
            if ($h->isLoad()) {
                // Not scanned: off the trip, back at the origin hub.
                foreach ($trip->onBoard()->where('status', 'pending')->with('parcel')->get() as $item) {
                    $this->linehaul->removeParcel($trip, $item->parcel);
                }
                $trip->update(['sealed_at' => now()]);
                $trip->log('sealed', $note, ['new_value' => "$done/{$c['expected']} parcel(s) · {$c['weight_kg']} kg"]);
                $this->tell($trip->courier?->user_id, "{$trip->code} sealed: you can depart",
                    "{$trip->origin?->code} confirmed the handover: $done parcel(s), {$c['weight_kg']} kg." . ($note ? " Note: $note" : ''));
            } else {
                $trip = $this->linehaul->closeReceiving($trip, true);
                $trip->log('receipt_confirmed', $note, ['new_value' => "$done/{$c['expected']} parcel(s) received"]);
            }
            $h->update(['confirmed_at' => now(), 'scanned' => $done, 'note' => $note]);

            return $trip->fresh();
        });
    }

    private function tell(?int $userId, string $title, string $body): void
    {
        if ($userId) {
            UserNotification::create(['user_id' => $userId, 'title' => $title, 'body' => $body, 'type' => 'linehaul']);
        }
    }
}
