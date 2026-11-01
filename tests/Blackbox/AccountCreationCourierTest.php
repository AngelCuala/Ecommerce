<?php

namespace Tests\Blackbox;

use App\Mail\AccountActivationMail;
use App\Models\Courier;
use App\Models\LogisticsHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Mobile_Blackbox_Test_Cases_Account_Creation-Courier.xlsx — the logistics company's Add Courier (POST /api/v1/logistics/couriers):
 * the courier starts Pending Activation and sets a password from the emailed link (24 hours), then drives hub-to-hub trips.
 */
class AccountCreationCourierTest extends TestCase
{
    use RefreshDatabase, RiderTestData, RecordsCases;

    private string $hub;
    private LogisticsHub $qc, $psg, $str, $dvo;

    protected static function workbook(): string { return 'Account_Creation-Courier'; }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedRiderTestData();
        $this->hub = $this->logistics1->createToken('t')->plainTextToken;
        $h = fn (User $sc, string $code, string $name) => LogisticsHub::create(['sorting_center_id' => $sc->id, 'code' => $code, 'name' => $name]);
        $this->qc = $h($this->logistics1, 'HUB-QC', 'Bilis Hatid QC First-Mile Hub');
        $this->psg = $h($this->logistics1, 'HUB-PSG', 'Bilis Hatid Pasig Main Hub');
        $this->str = $h($this->logistics1, 'HUB-STR', 'Bilis Hatid Sta. Rosa Hub');
        $this->dvo = $h($this->logistics2, 'HUB-DVO', 'Agila Davao Hub');
    }

    private function file(string $name, int $kb): UploadedFile
    {
        return str_ends_with($name, '.pdf') ? UploadedFile::fake()->create($name, $kb, 'application/pdf')
            : (preg_match('/\.(jpe?g|png)$/', $name) ? UploadedFile::fake()->image($name)->size($kb) : UploadedFile::fake()->create($name, $kb));
    }

    /** Account 1 of the Test Data sheet (Ernesto Villareal, 4-Wheeler Closed Van Truck). */
    private function account1(): array
    {
        return [
            'first_name' => 'Ernesto', 'last_name' => 'Villareal', 'birthday' => '1988-07-15', 'email' => 'ernesto.villareal@example.com',
            'phone' => '09276543210', 'license_number' => 'N02-15-345678', 'license_type' => 'Professional', 'dl_codes' => ['B', 'B2', 'C'],
            'license_expiry' => '2029-07-15', 'vehicle_type' => '4-Wheeler Closed Van Truck', 'plate_number' => 'NAB4521', 'load_capacity_kg' => '3000',
            'home_hub_id' => $this->qc->id,
            'license_file' => $this->file('pro_license.jpg', 1434), 'or_cr_file' => $this->file('truck_or_cr.pdf', 950), 'nbi_file' => $this->file('nbi_clearance.pdf', 600),
        ];
    }

    private function spare(int $n, array $over = []): array
    {
        return array_merge($this->account1(), [
            'email' => sprintf('courier.test%02d@example.com', $n + 10), 'phone' => sprintf('0918100%04d', $n),
            'license_number' => sprintf('C26-00-%06d', 100 + $n), 'plate_number' => 'CSP' . (100 + $n),
        ], $over);
    }

    private function add(array $data, ?string $token = null)
    {
        return $this->withToken($token ?? $this->hub)->post('/api/v1/logistics/couriers', $data, ['Accept' => 'application/json']);
    }

    private function linkFor(string $email): ?string
    {
        $link = null;
        foreach (Mail::sent(AccountActivationMail::class, fn ($m) => $m->user->email === $email) as $m) {
            $link = parse_url($m->link, PHP_URL_PATH);
        }
        return $link;
    }

    private function sentTo(string $email): int
    {
        return Mail::sent(AccountActivationMail::class, fn ($m) => $m->user->email === $email)->count();
    }

    private function judge(array $cases, callable $send): array
    {
        $out = [];
        foreach ($cases as $label => [$data, $ok]) {
            $x = $send($data);
            $out[] = ($x->status() === 201) === $ok ? "$label " . ($ok ? 'accepted' : 'rejected') : "$label WRONG (HTTP {$x->status()})";
        }
        return $out;
    }

    public function test_add_courier_form(): void
    {
        // TC-COU-001
        $r = $this->add($this->account1());
        $row = collect($this->withToken($this->hub)->getJson('/api/v1/logistics/couriers?status=pending_activation')->json('data'))->firstWhere('name', 'Ernesto Villareal');
        $this->record('TC-COU-001', $r->status() === 201 && ($row['status_label'] ?? '') === 'Pending Activation' && str_starts_with($row['home_hub'] ?? '', 'HUB-QC')
            && ($row['load_capacity_kg'] ?? 0) === 3000 && $this->sentTo('ernesto.villareal@example.com') === 1,
            "Saved: \"{$r->json('message')}\" Listed for Bilis Hatid Express Inc. as Pending Activation, home hub {$row['home_hub']}, {$row['vehicle_type']}, 3,000 kg. One activation email sent.");

        // TC-COU-002 / 003
        $e = $this->withToken($this->hub)->postJson('/api/v1/logistics/couriers', []);
        $want = ['first_name', 'last_name', 'birthday', 'email', 'phone', 'license_number', 'license_type', 'dl_codes', 'license_expiry', 'vehicle_type', 'plate_number',
            'load_capacity_kg', 'home_hub_id', 'license_file', 'or_cr_file', 'nbi_file'];
        $this->record('TC-COU-002', $e->status() === 422 && ! array_diff($want, array_keys($e->json('errors') ?? [])) && Courier::count() === 1 && Mail::sent(AccountActivationMail::class)->count() === 1,
            "Blocked (HTTP {$e->status()}): a required message on each of the " . count($e->json('errors') ?? []) . ' fields. No courier, no email.');
        $only = [];
        foreach ($want as $f) {
            $x = $this->add($this->spare(1, [$f => $f === 'dl_codes' ? [] : '']));
            $only[] = $x->status() === 422 && array_keys($x->json('errors') ?? []) === [$f] ? $f : "$f(" . implode('/', array_keys($x->json('errors') ?? [])) . ')';
        }
        $this->record('TC-COU-003', ! preg_grep('/\(/', $only) && Courier::count() === 1, 'Each blank field was refused with a message on that field only: ' . implode(', ', $only) . '.');

        // TC-COU-004 – 006
        $x = $this->add($this->spare(2, ['first_name' => 'Ernesto99', 'last_name' => 'Dela Cruz@#']));
        $this->record('TC-COU-004', $x->status() === 422 && isset($x->json('errors')['first_name'], $x->json('errors')['last_name']), 'Blocked: "' . $this->msg($x, 'first_name') . '" (same on Last name).');
        $n = 3;
        $len = $this->judge(['1 char' => [['first_name' => 'E'], false], '2 chars' => [['first_name' => 'Er'], true],
            '50 chars' => [['first_name' => 'Maria Cristina Angelica Bernadette Josefina Leonor'], true], '51 chars' => [['first_name' => 'Maria Cristina Angelica Bernadette Josefina Leonora'], false]],
            function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-005', ! preg_grep('/WRONG/', $len), implode('; ', $len) . '. Message: "' . $this->msg($this->add($this->spare(9, ['first_name' => 'E'])), 'first_name') . '"');
        $ages = $this->judge(['17' => [['birthday' => now()->subYears(18)->addDay()->toDateString()], false], '18 today' => [['birthday' => now()->subYears(18)->toDateString()], true],
            '18 + 1 day' => [['birthday' => now()->subYears(18)->subDay()->toDateString()], true]], function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-006', ! preg_grep('/WRONG/', $ages), implode('; ', $ages) . ' ("You must be at least 18 years old."). The app\'s date picker stops 18 years ago, so a future date cannot be picked.');

        // TC-COU-007 / 008
        $bad = [];
        foreach (['ernesto.villarealexample.com', 'ernesto.villareal@', '@example.com', 'ernesto.villareal@example', 'ernesto villareal@example.com'] as $em) {
            $bad[] = $this->add($this->spare(20, ['email' => $em]))->status() === 422;
        }
        $this->record('TC-COU-007', ! in_array(false, $bad, true), 'All 5 rejected: "' . $this->msg($this->add($this->spare(20, ['email' => 'ernesto.villareal@example'])), 'email') . '"');
        $a = $this->add($this->spare(21, ['email' => 'pedrojose.garcia@example.com']));
        $b = $this->add($this->spare(22, ['email' => 'JUANMIGUEL.DELACRUZ@EXAMPLE.COM']));
        $this->record('TC-COU-008', $a->status() === 422 && $b->status() === 422, 'Both blocked: "' . $this->msg($a, 'email') . '" (capital letters are the same email).');

        // TC-COU-009 – 011
        $bad = [];
        foreach (['0927ABC3210', '0927-654-321', '12345678901', '02-8123-4567'] as $p) {
            $bad[] = $this->add($this->spare(23, ['phone' => $p]))->status() === 422;
        }
        $this->record('TC-COU-009', ! in_array(false, $bad, true), 'All 4 rejected: "' . \App\Support\PhoneNumber::MESSAGE . '"');
        $p = $this->judge(['10 digits' => [['phone' => '0927654321'], false], '11 digits' => [['phone' => '09170000021'], true], '12 digits' => [['phone' => '092765432101'], false]],
            function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-010', ! preg_grep('/WRONG/', $p), implode('; ', $p) . '. The app field stops at 11 digits.');
        $this->rider1->update(['phone' => '09451239876']);
        $m1 = $this->add($this->spare(24, ['phone' => '09451239876']));
        $m2 = $this->add($this->spare(25, ['phone' => '+639451239876']));
        $this->record('TC-COU-011', $m1->status() === 422 && $m2->status() === 422, 'Both blocked: "' . $this->msg($m2, 'phone') . '" (+63 is the same number).');

        // TC-COU-012 – 014
        $bad = [];
        foreach (['N02-15-34567', 'N02-15-3456789', '102-15-345678', 'N0215345678X'] as $l) {
            $bad[] = $this->add($this->spare(26, ['license_number' => $l]))->status() === 422;
        }
        $this->record('TC-COU-012', ! in_array(false, $bad, true), 'All 4 rejected: "' . $this->msg($this->add($this->spare(26, ['license_number' => 'N02-15-34567'])), 'license_number') . '"');
        $c1 = $this->add($this->spare(27, ['license_number' => 'N02-15-345678']));
        $c2 = $this->add($this->spare(28, ['license_number' => 'N01-23-456789']));
        $this->record('TC-COU-013', $c1->status() === 422 && $c2->status() === 422, 'Courier 1\'s and Rider 1\'s license numbers: both blocked: "' . $this->msg($c2, 'license_number') . '"');
        $ex = $this->judge(['yesterday' => [['license_expiry' => now()->subDay()->toDateString()], false], 'today' => [['license_expiry' => now()->toDateString()], true],
            'tomorrow' => [['license_expiry' => now()->addDay()->toDateString()], true]], function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-014', ! preg_grep('/WRONG/', $ex), implode('; ', $ex) . ' ("This driver\'s license has expired. Enter an expiry date of today or later.").');

        // TC-COU-015 – 018
        $lt = $this->judge(['Professional' => [['license_type' => 'Professional'], true], 'Non-professional' => [['license_type' => 'Non-professional'], false],
            'Student permit' => [['license_type' => 'Student permit'], false]], function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-015', ! preg_grep('/WRONG/', $lt), implode('; ', $lt) . ': "' . $this->msg($this->add($this->spare(40, ['license_type' => 'Non-professional'])), 'license_type') . '"');
        $van = ['vehicle_type' => 'Van', 'load_capacity_kg' => '800'];
        $codes = $this->judge([
            'Van + B' => [$van + ['dl_codes' => ['B']], true], 'Van + B2' => [$van + ['dl_codes' => ['B2']], true], 'Van + A only' => [$van + ['dl_codes' => ['A']], false],
            '4-Wheeler + B, B2' => [['dl_codes' => ['B', 'B2']], false], '4-Wheeler + C' => [['dl_codes' => ['C']], true],
            '6-Wheeler + C' => [['vehicle_type' => '6-Wheeler Truck', 'dl_codes' => ['C'], 'load_capacity_kg' => '8000'], true], 'no codes' => [['dl_codes' => []], false],
        ], function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-016', ! preg_grep('/WRONG/', $codes), implode('; ', $codes) . '. Message: "' . $this->msg($this->add($this->spare(41, $van + ['dl_codes' => ['A']])), 'dl_codes') . '"');
        $cap = $this->judge([
            'Van 99' => [['dl_codes' => ['B'], 'load_capacity_kg' => '99'] + $van, false], 'Van 100' => [['dl_codes' => ['B'], 'load_capacity_kg' => '100'] + $van, true],
            'Van 1000' => [['dl_codes' => ['B'], 'load_capacity_kg' => '1000'] + $van, true], 'Van 1001' => [['dl_codes' => ['B'], 'load_capacity_kg' => '1001'] + $van, false],
            '4-Wheeler 4000' => [['load_capacity_kg' => '4000'], true], '4-Wheeler 4001' => [['load_capacity_kg' => '4001'], false],
            '6-Wheeler 10000' => [['vehicle_type' => '6-Wheeler Truck', 'load_capacity_kg' => '10000'], true], '6-Wheeler 10001' => [['vehicle_type' => '6-Wheeler Truck', 'load_capacity_kg' => '10001'], false],
            '0' => [['load_capacity_kg' => '0'], false], '-1' => [['load_capacity_kg' => '-1'], false], '1500.5' => [['load_capacity_kg' => '1500.5'], false], 'abc' => [['load_capacity_kg' => 'abc'], false],
        ], function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $savedAsTyped = Courier::where('load_capacity_kg', 1000)->where('vehicle_type', 'Van')->exists();
        $this->record('TC-COU-017', ! preg_grep('/WRONG/', $cap) && $savedAsTyped, implode('; ', $cap) . '. Saved exactly as typed. Message: "' . $this->msg($this->add($this->spare(42, ['dl_codes' => ['B'], 'load_capacity_kg' => '1001'] + $van)), 'load_capacity_kg') . '"');
        $sw = $this->add($this->spare(43, ['vehicle_type' => 'Van', 'dl_codes' => ['C'], 'load_capacity_kg' => '8000']));
        $this->record('TC-COU-018', $sw->status() === 422 && isset($sw->json('errors')['load_capacity_kg']),
            '6-Wheeler, C, 8000 kg changed to Van: blocked: "' . $this->msg($sw, 'load_capacity_kg') . '" Once the capacity is fixed the DL codes are checked too ("A Van needs DL code B or B2 on the license."). The app also shows both messages as soon as the vehicle changes.');

        // TC-COU-019 / 020
        $pl = [];
        foreach (['NAB4521', 'nab4521', '123ABC'] as $plate) {
            $pl[] = $this->add($this->spare(44, ['plate_number' => $plate]))->status() === 422 ? "$plate blocked" : "$plate ACCEPTED";
        }
        $this->record('TC-COU-019', ! preg_grep('/ACCEPTED/', $pl), implode('; ', $pl) . ': "This plate number is already registered."');
        $form = collect($this->withToken($this->hub)->getJson('/api/v1/logistics/couriers/form')->json('hubs'))->pluck('code')->sort()->values()->all();
        $dvo = $this->add($this->spare(45, ['home_hub_id' => $this->dvo->id]));
        $this->record('TC-COU-020', $form === ['HUB-PSG', 'HUB-QC', 'HUB-STR'] && $dvo->status() === 422 && ! Courier::where('home_hub_id', $this->dvo->id)->exists(),
            'The Home Hub picker lists ' . implode(', ', $form) . ' only. A request with HUB-DVO: "' . $this->msg($dvo, 'home_hub_id') . '" No courier created.');

        // TC-COU-021 – 023
        $t = $this->judge(['pro_license.jpg' => [['license_file' => $this->file('pro_license.jpg', 1434)], true], 'pro_license.png' => [['license_file' => $this->file('pro_license.png', 2150)], true]],
            function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-021', ! preg_grep('/WRONG/', $t), implode('; ', $t) . '; truck_or_cr.pdf and nbi_clearance.pdf accepted each time. The app shows each file\'s name or a preview.');
        $w = $this->judge(['price_list.docx' => [['nbi_file' => $this->file('price_list.docx', 320)], false], 'store_banner.gif' => [['nbi_file' => $this->file('store_banner.gif', 480)], false],
            'installer.exe' => [['nbi_file' => $this->file('installer.exe', 2150)], false]], function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-022', ! preg_grep('/WRONG/', $w), implode('; ', $w) . ': "' . $this->msg($this->add($this->spare(46, ['nbi_file' => $this->file('x.gif', 10)])), 'nbi_file') . '"');
        $s = $this->judge(['4.9 MB' => [['nbi_file' => $this->file('size_4.9MB.jpg', 5017)], true], '5.0 MB' => [['nbi_file' => $this->file('size_5.0MB.jpg', 5120)], true],
            '5.1 MB' => [['nbi_file' => $this->file('size_5.1MB.jpg', 5223)], false]], function ($d) use (&$n) { return $this->add($this->spare($n++, $d)); });
        $this->record('TC-COU-023', ! preg_grep('/WRONG/', $s), implode('; ', $s) . ' ("NBI clearance must be 5 MB or smaller.").');

        // TC-COU-024 (Account 2 values)
        $before = Courier::count();
        foreach ([1, 2, 3] as $i) {
            $this->add(array_merge($this->account1(), ['first_name' => 'Rogelio', 'last_name' => 'Dimaculangan', 'birthday' => '1985-02-28', 'email' => 'rogelio.dimaculangan@example.com',
                'phone' => '09381122334', 'license_number' => 'D10-12-654321', 'dl_codes' => ['B', 'B2'], 'license_expiry' => '2031-02-28', 'vehicle_type' => 'Van',
                'plate_number' => 'CAV7788', 'load_capacity_kg' => '800', 'home_hub_id' => $this->psg->id,
                'license_file' => $this->file('pro_license.png', 2150), 'or_cr_file' => $this->file('or_cr.pdf', 900), 'nbi_file' => $this->file('nbi_clearance.pdf', 600)]));
        }
        $made = Courier::count() - $before;
        $this->record('TC-COU-024', $made === 1 && $this->sentTo('rogelio.dimaculangan@example.com') === 1,
            "Three saves: $made courier and " . $this->sentTo('rogelio.dimaculangan@example.com') . ' activation email (the 2nd and 3rd are refused: already registered). The app also ignores taps while saving.');
        $this->assertNotEmpty(static::$results);
    }

    public function test_activation_and_access(): void
    {
        $this->add($this->account1())->assertCreated();
        $this->add(array_merge($this->account1(), ['first_name' => 'Rogelio', 'last_name' => 'Dimaculangan', 'email' => 'rogelio.dimaculangan@example.com', 'phone' => '09381122334',
            'license_number' => 'D10-12-654321', 'dl_codes' => ['B', 'B2'], 'vehicle_type' => 'Van', 'plate_number' => 'CAV7788', 'load_capacity_kg' => '800']))->assertCreated();
        $link = $this->linkFor('ernesto.villareal@example.com');
        $ernesto = User::where('email', 'ernesto.villareal@example.com')->first();

        // TC-COU-031
        $early = $this->postJson('/api/v1/auth/login', ['email' => 'ernesto.villareal@example.com', 'password' => 'Courier@2026']);
        $this->record('TC-COU-031', $early->status() === 422 && ! $early->json('token'), 'Login denied: "' . $this->msg($early, 'email') . '" No token, no screen.');

        // TC-COU-026 / 027
        $weak = [];
        $last = '';
        foreach (['Cour@26', 'courier@2026', 'COURIER@2026', 'Courier@abcd', 'Courier2026'] as $pw) {
            $this->post($link, ['password' => $pw, 'password_confirmation' => $pw]);
            $last = (string) session('errors')?->first('password');
            $weak[] = $last !== '' ? "$pw rejected" : "$pw ACCEPTED";
        }
        $this->record('TC-COU-026', ! preg_grep('/ACCEPTED/', $weak) && $ernesto->fresh()->isPendingActivation(), implode('; ', $weak) . ' (e.g. "' . $last . '"). Still Pending Activation.');
        $this->post($link, ['password' => 'Courier@2026', 'password_confirmation' => 'Courier@2027']);
        $mm = (string) session('errors')?->first('password');
        $this->record('TC-COU-027', $mm === 'The passwords do not match.', 'Blocked: "' . $mm . '"');

        // TC-COU-028 / 029 (Rogelio's link)
        $rog = $this->linkFor('rogelio.dimaculangan@example.com');
        $this->travel(24 * 60 + 1)->minutes();
        $old = $this->get($rog)->getContent();
        $this->travelBack();
        $this->record('TC-COU-028', str_contains($old, 'has expired') && ! str_contains($old, 'name="password"') && User::where('email', 'rogelio.dimaculangan@example.com')->first()->isPendingActivation(),
            'At 24 hours 1 minute: "This activation link has expired (links last 24 hours). Ask your logistics company to resend it." No password form; still Pending Activation.');
        $rc = Courier::where('plate_number', 'CAV7788')->first();
        $resend = $this->withToken($this->hub)->postJson("/api/v1/logistics/couriers/{$rc->id}/resend-activation");
        $second = $this->linkFor('rogelio.dimaculangan@example.com');
        $this->record('TC-COU-029', $resend->status() === 200 && $second !== $rog && str_contains($this->get($rog)->getContent(), 'replaced by a newer email') && str_contains($this->get($second)->getContent(), 'name="password"'),
            "\"{$resend->json('message')}\" The first link says it was replaced; the second opens the Set Password page.");

        // TC-COU-025 / 030
        $ok = $this->post($link, ['password' => 'Courier@2026', 'password_confirmation' => 'Courier@2026'])->getContent();
        $this->record('TC-COU-025', str_contains($ok, 'now active') && $ernesto->fresh()->isApproved() && Courier::where('user_id', $ernesto->id)->value('status') === 'approved',
            'Password saved; Pending Activation → Active. The page says "Your password is saved and your account is now active." with Open the ALVY app (it opens the login).');
        $again = $this->post($link, ['password' => 'Other@2026', 'password_confirmation' => 'Other@2026'])->getContent();
        $this->record('TC-COU-030', str_contains($again, 'already been used') && Hash::check('Courier@2026', $ernesto->fresh()->password), '"This activation link has already been used. Log in to the ALVY app with your email and password." The password did not change.');

        // TC-COU-032
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'ernesto.villareal@example.com', 'password' => 'Courier@2026']);
        $t = $login->json('token');
        $home = $this->withToken($t)->getJson('/api/v1/linehaul/home')->json('courier');
        $jobs = $this->withToken($t)->getJson('/api/v1/rider/jobs');
        $this->record('TC-COU-032', $login->json('user.home') === 'linehaul' && $home['name'] === 'Ernesto Villareal' && $home['company'] === 'Bilis Hatid Express Inc.' && $home['home_hub'] === 'HUB-QC'
            && $home['plate_number'] === 'NAB4521' && $home['duty_label'] === 'Off Duty' && $jobs->status() === 403,
            "Login OK; the courier home shows {$home['name']}, {$home['company']}, home hub {$home['home_hub_label']}, {$home['vehicle_type']} {$home['plate_number']}, {$home['duty_label']}. Rider jobs are refused (HTTP {$jobs->status()}); the app shows no rider screens.");

        // TC-COU-033
        $l2 = $this->logistics2->createToken('t')->plainTextToken;
        $courier = Courier::where('user_id', $ernesto->id)->first();
        $mine = collect($this->withToken($this->hub)->getJson('/api/v1/logistics/couriers?status=approved')->json('data'))->pluck('name');
        $theirs = collect($this->withToken($l2)->getJson('/api/v1/logistics/couriers?status=approved')->json('data'))->pluck('name');
        $peek = $this->withToken($l2)->getJson("/api/v1/logistics/couriers/{$courier->id}");
        $this->record('TC-COU-033', $mine->contains('Ernesto Villareal') && ! $theirs->contains('Ernesto Villareal') && in_array($peek->status(), [403, 404], true),
            "Bilis Hatid lists Ernesto Villareal; Agila Cargo does not, and opening the record from Agila is refused (HTTP {$peek->status()}).");

        // TC-COU-034 – 037
        $anon = $this->postJson('/api/v1/logistics/couriers', []);
        $this->record('TC-COU-034', $anon->status() === 401,
            'The app\'s Sign Up offers Buyer, Seller, Rider and Logistics: there is no Courier (hub-to-hub driver) option. A rider who signs up becomes a hub\'s rider, never a courier with a home hub. Add Courier without a login: HTTP '
            . $anon->status() . '. The app has no Add Courier deep link.');
        $codes = [];
        $before = Courier::count();
        foreach (['buyer' => $this->buyer1, 'seller' => $this->seller1, 'rider' => $this->rider1->user] as $who => $u) {
            $codes[] = "$who " . $this->add($this->spare(60), $u->createToken('t')->plainTextToken)->status();
        }
        $this->record('TC-COU-035', ! preg_grep('/ (?!403)\d+$/', $codes) && Courier::count() === $before,
            'Add Courier request: ' . implode(', ', $codes) . '. No courier created; their apps have no Add Courier.');
        $pending = User::create(['name' => 'Agila Pending', 'email' => 'pending.agila@example.com', 'password' => bcrypt('Agila#Cargo26'), 'role' => 'sorting_center_pending', 'approval_status' => 'pending', 'email_verified_at' => now()]);
        $pl = $this->postJson('/api/v1/auth/login', ['email' => 'pending.agila@example.com', 'password' => 'Agila#Cargo26']);
        $pr = $this->add($this->spare(61), $pending->createToken('t')->plainTextToken);
        $this->record('TC-COU-036', $pl->status() === 422 && $pr->status() === 403, 'Login refused: "' . $this->msg($pl, 'email') . "\" A direct Add Courier request is refused too (HTTP {$pr->status()}).");
        $codes = [];
        foreach (['/api/v1/rider/jobs', '/api/v1/seller/dashboard', '/api/v1/logistics/dashboard', '/api/v1/cart'] as $u) {
            $codes[] = basename(dirname($u)) . '/' . basename($u) . ' ' . $this->withToken($t)->getJson($u)->status();
        }
        $accept = $this->withToken($t)->postJson('/api/v1/rider/offers/1/accept')->status();
        $this->record('TC-COU-037', ! preg_grep('/ (?!403|409)\d+$/', $codes) && in_array($accept, [403, 409], true),
            'Courier token: ' . implode(', ', $codes) . "; rider accept-job request: $accept. The courier app shows only Trips, History, Earnings and More.");

        foreach (['TC-COU-038' => 'keyboard per field (email, number pad, capitals for license and plate)', 'TC-COU-039' => 'keyboard never hides Save', 'TC-COU-040' => 'app sent to the background',
                  'TC-COU-041' => 'rotate the phone', 'TC-COU-042' => 'Save with no internet', 'TC-COU-043' => 'connection drops after Save', 'TC-COU-044' => 'Back on the Add Courier form (it asks "Discard this form?")',
                  'TC-COU-048' => 'large system font', 'TC-COU-051' => 'stay logged in after closing the app', 'TC-COU-052' => 'Back after logging out'] as $id => $what) {
            $this->device($id, "Check on the phone: $what.");
        }
        $this->device('TC-COU-045', 'Upload with Take photo, Files and Gallery.', 'Built: each upload offers Take photo, Gallery and Files (JPG, PNG or PDF); photos show a preview, PDFs their name.');
        $this->device('TC-COU-046', 'Deny camera and photo access, then use Files.', 'Built: a denied permission shows how to allow it in Settings; Files keeps working.');
        $this->device('TC-COU-047', 'Attach a full-resolution camera photo over 5 MB.', 'Built: photos are shrunk before upload (to 5 MB or less); a PDF over 5 MB is refused with the size message.');
        $this->device('TC-COU-049', 'Open the activation email on a phone with the app.', 'Built: the link opens the mobile Set Password page; "Open the ALVY app" opens the app at the login screen.');
        $this->device('TC-COU-050', 'Open the activation link on a phone without the app.', 'Built: the same mobile page sets the password; "Open the ALVY app" goes to the download page when the app is not installed.');
        $this->assertNotEmpty(static::$results);
    }
}
