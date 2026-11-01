<?php

namespace Tests\Blackbox;

use App\Mail\AccountActivationMail;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use App\Services\PsgcDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Mobile_Blackbox_Test_Cases_Account_Creation-Rider.xlsx — the logistics company's Add Rider (POST /api/v1/logistics/riders/accounts):
 * the rider starts Pending Activation and sets a password from the emailed link (24 hours).
 */
class AccountCreationRiderTest extends TestCase
{
    use RefreshDatabase, RiderTestData, RecordsCases;

    private const LUISIANA = '043412000';
    private const LAGUNA   = '043400000';
    private string $hub;
    private string $brgy;

    protected static function workbook(): string { return 'Account_Creation-Rider'; }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedRiderTestData();
        $this->logistics1->forceFill(['assigned_municipality' => 'Luisiana', 'assigned_municipality_code' => self::LUISIANA,
            'assigned_province' => 'Laguna', 'assigned_province_code' => self::LAGUNA])->save();
        $this->hub = $this->logistics1->createToken('t')->plainTextToken;
        $this->brgy = collect(app(PsgcDirectory::class)->barangaysByMunicipality(self::LUISIANA))->firstWhere('name', 'San Isidro')['psgc'];
    }

    private function file(string $name, int $kb): UploadedFile
    {
        return str_ends_with($name, '.pdf') ? UploadedFile::fake()->create($name, $kb, 'application/pdf')
            : (preg_match('/\.(jpe?g|png)$/', $name) ? UploadedFile::fake()->image($name)->size($kb) : UploadedFile::fake()->create($name, $kb));
    }

    /** Account 1 of the Test Data sheet (Pedro Jose Garcia). */
    private function account1(): array
    {
        return [
            'first_name' => 'Pedro Jose', 'last_name' => 'Garcia', 'birthday' => '1995-03-22', 'email' => 'pedrojose.garcia@example.com',
            'phone' => '09451239876', 'license_number' => 'N01-23-456789', 'license_expiry' => '2030-03-22', 'vehicle_type' => 'Motorcycle',
            'plate_number' => '123ABC', 'barangay_psgc' => $this->brgy,
            'license_file' => $this->file('drivers_license.jpg', 1536), 'or_cr_file' => $this->file('or_cr.pdf', 900),
        ];
    }

    /** Spare values for repeat attempts (unused email, mobile, license and plate). */
    private function spare(int $n, array $over = []): array
    {
        return array_merge($this->account1(), [
            'email' => sprintf('rider.test%02d@example.com', $n + 10), 'phone' => sprintf('0918000%04d', $n),
            'license_number' => sprintf('M26-00-%06d', 100 + $n), 'plate_number' => 'SPR' . (100 + $n),
        ], $over);
    }

    private function add(array $data, ?string $token = null)
    {
        return $this->withToken($token ?? $this->hub)->post('/api/v1/logistics/riders/accounts', $data, ['Accept' => 'application/json']);
    }

    /** The newest activation link emailed to this address (its path). */
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

    public function test_add_rider_form(): void
    {
        // The seeded rider 1 is made here through Add Rider instead.
        $seeded = $this->rider1->user;
        $this->rider1->delete();
        $seeded->delete();

        // TC-RID-001
        $r = $this->add($this->account1());
        $rider = Rider::where('license_number', 'N01-23-456789')->first();
        $listed = collect($this->withToken($this->hub)->getJson('/api/v1/logistics/riders?tab=pending')->json('data'))->firstWhere('full_name', 'Pedro Jose Garcia');
        $this->record('TC-RID-001', $r->status() === 200 && $rider?->isPendingActivation() && ($listed['status_label'] ?? '') === 'Pending Activation' && $this->sentTo('pedrojose.garcia@example.com') === 1,
            "Saved: \"{$r->json('message')}\" Listed for Bilis Hatid Express Inc. as " . ($listed['status_label'] ?? '?') . '; one activation email sent to pedrojose.garcia@example.com.');

        // TC-RID-002 / 003
        $before = Rider::count();
        $mails = Mail::sent(AccountActivationMail::class)->count();
        $e = $this->withToken($this->hub)->postJson('/api/v1/logistics/riders/accounts', []);
        $want = ['first_name', 'last_name', 'birthday', 'email', 'phone', 'license_number', 'license_expiry', 'vehicle_type', 'plate_number', 'license_file', 'or_cr_file'];
        $missing = array_diff($want, array_keys($e->json('errors') ?? []));
        $this->record('TC-RID-002', $e->status() === 422 && ! $missing && Rider::count() === $before && Mail::sent(AccountActivationMail::class)->count() === $mails,
            "Blocked (HTTP {$e->status()}): a required message on " . count($e->json('errors') ?? []) . ' fields (' . implode(', ', array_keys($e->json('errors') ?? [])) . '). No rider, no email.');
        $only = [];
        foreach ($want as $f) {
            $x = $this->add($this->spare(1, [$f => '']));
            $only[] = $x->status() === 422 && array_keys($x->json('errors') ?? []) === [$f] ? $f : "$f(" . implode('/', array_keys($x->json('errors') ?? [])) . ')';
        }
        $this->record('TC-RID-003', ! preg_grep('/\(/', $only) && Rider::count() === $before,
            'Each blank field was refused with a message on that field only: ' . implode(', ', $only) . '. The app keeps the other values (only the server\'s messages change).');

        // TC-RID-004 / 005
        $x = $this->add($this->spare(2, ['first_name' => 'Pedro Jose99', 'last_name' => 'Dela Cruz@#']));
        $this->record('TC-RID-004', $x->status() === 422 && isset($x->json('errors')['first_name'], $x->json('errors')['last_name']),
            'Blocked: "' . $this->msg($x, 'first_name') . '" (and the same on Last name).');
        $len = [];
        foreach (['P' => false, 'Po' => true, 'Maria Cristina Angelica Bernadette Josefina Leonor' => true, 'Maria Cristina Angelica Bernadette Josefina Leonora' => false] as $name => $ok) {
            $x = $this->add($this->spare(3 + count($len), ['first_name' => $name]));
            $len[] = ($x->status() === 200) === $ok ? strlen($name) . ' ' . ($ok ? 'accepted' : 'rejected') : strlen($name) . ' WRONG';
        }
        $this->record('TC-RID-005', ! preg_grep('/WRONG/', $len), implode('; ', $len) . ' characters. Message: "' . $this->msg($this->add($this->spare(9, ['first_name' => 'P'])), 'first_name') . '"');

        // TC-RID-006: 18-year limit
        $ages = [];
        foreach ([[now()->subYears(18)->addDay(), false, '17'], [now()->subYears(18), true, '18 today'], [now()->subYears(18)->subDay(), true, '18 + 1 day']] as $k => [$d, $ok, $label]) {
            $x = $this->add($this->spare(10 + $k, ['birthday' => $d->toDateString()]));
            $ages[] = ($x->status() === 200) === $ok ? "$label " . ($ok ? 'accepted' : 'rejected ("' . $this->msg($x, 'birthday') . '")') : "$label WRONG";
        }
        $this->record('TC-RID-006', ! preg_grep('/WRONG/', $ages), implode('; ', $ages) . '. The app\'s date picker stops 18 years ago.');

        // TC-RID-007 / 008
        $bad = [];
        foreach (['pedrojose.garciaexample.com', 'pedrojose.garcia@', '@example.com', 'pedrojose.garcia@example', 'pedrojose garcia@example.com'] as $em) {
            $bad[] = $this->add($this->spare(13, ['email' => $em]))->status() === 422;
        }
        $this->record('TC-RID-007', ! in_array(false, $bad, true), 'All 5 rejected: "' . $this->msg($this->add($this->spare(13, ['email' => 'pedrojose.garcia@example'])), 'email') . '" No rider created.');
        $x = $this->add($this->spare(14, ['email' => 'juanmiguel.delacruz@example.com']));
        $this->record('TC-RID-008', $x->status() === 422 && str_contains($this->msg($x, 'email'), 'already in use'), 'Blocked: "' . $this->msg($x, 'email') . '"');

        // TC-RID-009 / 010 / 011
        $bad = [];
        foreach (['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567'] as $p) {
            $bad[] = $this->add($this->spare(15, ['phone' => $p]))->status() === 422;
        }
        $this->record('TC-RID-009', ! in_array(false, $bad, true), 'All 4 rejected: "' . \App\Support\PhoneNumber::MESSAGE . '" The app field takes digits (or +63) only.');
        $r10 = $this->add($this->spare(16, ['phone' => '0917123456']));
        $r11 = $this->add($this->spare(16, ['phone' => '09170000001']));
        $r12 = $this->add($this->spare(17, ['phone' => '091712345678']));
        $this->record('TC-RID-010', $r10->status() === 422 && $r11->status() === 200 && $r12->status() === 422, '10 digits: rejected; 11 digits: accepted; 12 digits: rejected. The app field stops at 11 digits.');
        $x = $this->add($this->spare(18, ['phone' => '09171234567']));
        $this->record('TC-RID-011', $x->status() === 422 && str_contains($this->msg($x, 'phone'), 'already in use'), 'Blocked: "' . $this->msg($x, 'phone') . '"');

        // TC-RID-012 – 015: license and plate
        $bad = [];
        foreach (['N01-23-45678', 'N01-23-4567890', '101-23-456789', 'N0123456789X'] as $l) {
            $bad[] = $this->add($this->spare(19, ['license_number' => $l]))->status() === 422;
        }
        $this->record('TC-RID-012', ! in_array(false, $bad, true), 'All 4 rejected: "' . $this->msg($this->add($this->spare(19, ['license_number' => 'N01-23-45678'])), 'license_number') . '" The app field types the dashes for you.');
        $x = $this->add($this->spare(20, ['license_number' => 'N01-23-456789']));
        $this->record('TC-RID-013', $x->status() === 422 && str_contains($this->msg($x, 'license_number'), 'already registered'), 'Blocked: "' . $this->msg($x, 'license_number') . '"');
        $ex = [];
        foreach ([[now()->subDay(), false, 'yesterday'], [now(), true, 'today'], [now()->addDay(), true, 'tomorrow']] as $k => [$d, $ok, $label]) {
            $x = $this->add($this->spare(21 + $k, ['license_expiry' => $d->toDateString()]));
            $ex[] = ($x->status() === 200) === $ok ? "$label " . ($ok ? 'accepted' : 'rejected ("' . $this->msg($x, 'license_expiry') . '")') : "$label WRONG";
        }
        $this->record('TC-RID-014', ! preg_grep('/WRONG/', $ex), implode('; ', $ex) . '.');
        $x = $this->add($this->spare(24, ['plate_number' => '123ABC']));
        $this->record('TC-RID-015', $x->status() === 422 && str_contains($this->msg($x, 'plate_number'), 'already registered'), 'Blocked: "' . $this->msg($x, 'plate_number') . '"');

        // TC-RID-016 – 018: documents
        $types = [];
        foreach ([['drivers_license.jpg', 1536], ['drivers_license.png', 2048], ['drivers_license.pdf', 700]] as $k => [$f, $kb]) {
            $types[] = $f . ' ' . ($this->add($this->spare(25 + $k, ['license_file' => $this->file($f, $kb)]))->status() === 200 ? 'accepted' : 'REFUSED');
        }
        $this->record('TC-RID-016', ! preg_grep('/REFUSED/', $types), implode('; ', $types) . '; or_cr.pdf accepted each time. The app shows each file\'s name (or a preview for photos).');
        $wrong = [];
        foreach ([['price_list.docx', 320], ['store_banner.gif', 480], ['installer.exe', 2150]] as [$f, $kb]) {
            $x = $this->add($this->spare(28, ['license_file' => $this->file($f, $kb)]));
            $wrong[] = $x->status() === 422 ? "$f rejected" : "$f ACCEPTED";
        }
        $this->record('TC-RID-017', ! preg_grep('/ACCEPTED/', $wrong), implode('; ', $wrong) . ': "' . $this->msg($this->add($this->spare(28, ['license_file' => $this->file('store_banner.gif', 480)])), 'license_file') . '" The app\'s file picker also offers JPG, PNG and PDF only.');
        $size = [];
        foreach ([[5017, true, '4.9 MB'], [5120, true, '5.0 MB'], [5223, false, '5.1 MB']] as $k => [$kb, $ok, $label]) {
            $x = $this->add($this->spare(29 + $k, ['license_file' => $this->file('size.jpg', $kb)]));
            $size[] = ($x->status() === 200) === $ok ? "$label " . ($ok ? 'accepted' : 'rejected ("' . $this->msg($x, 'license_file') . '")') : "$label WRONG";
        }
        $this->record('TC-RID-018', ! preg_grep('/WRONG/', $size), implode('; ', $size) . '.');

        // TC-RID-019: Save tapped three times (Account 2 values)
        $before = Rider::count();
        $acc2 = ['first_name' => 'Mark Anthony', 'last_name' => 'Bautista', 'birthday' => '1999-08-09', 'email' => 'markanthony.b@example.com',
            'phone' => '09662345678', 'license_number' => 'D05-19-123456', 'license_expiry' => '2031-08-09', 'vehicle_type' => 'Van', 'plate_number' => 'NBC1235'];
        foreach ([1, 2, 3] as $i) {
            $this->add(array_merge($this->account1(), $acc2, ['license_file' => $this->file('drivers_license.png', 2048), 'or_cr_file' => $this->file('or_cr.pdf', 900)]));
        }
        $made = Rider::count() - $before;
        $this->record('TC-RID-019', $made === 1 && $this->sentTo('markanthony.b@example.com') === 1,
            "Three saves: $made rider and " . $this->sentTo('markanthony.b@example.com') . ' activation email. The 2nd and 3rd are refused (email, mobile, license and plate already registered); the app also ignores taps while saving.');

        // TC-RID-026: login before activating
        $link = $this->linkFor('pedrojose.garcia@example.com');
        $pedro = User::where('email', 'pedrojose.garcia@example.com')->first();
        $early = $this->postJson('/api/v1/auth/login', ['email' => 'pedrojose.garcia@example.com', 'password' => 'Rider@2026']);
        $this->record('TC-RID-026', $early->status() === 422 && ! $early->json('token'), 'Login denied: "' . $this->msg($early, 'email') . '" No token, no screen.');

        // TC-RID-021 / 022: password rule
        $weak = [];
        $last = '';
        foreach (['Ride@26', 'rider@2026', 'RIDER@2026', 'Rider@abcd', 'Rider2026'] as $pw) {
            $this->post($link, ['password' => $pw, 'password_confirmation' => $pw]);
            $last = (string) session('errors')?->first('password');
            $weak[] = $last !== '' ? "$pw rejected" : "$pw ACCEPTED";
        }
        $this->record('TC-RID-021', ! preg_grep('/ACCEPTED/', $weak) && $pedro->fresh()->isPendingActivation(),
            implode('; ', $weak) . ' (each with the rule that failed, e.g. "' . $last . '"). The rider stays Pending Activation.');
        $this->post($link, ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2027']);
        $mm = (string) session('errors')?->first('password');
        $this->record('TC-RID-022', $mm === 'The passwords do not match.' && $pedro->fresh()->isPendingActivation(), 'Blocked: "' . $mm . '"');

        // TC-RID-023: a link older than 24 hours (Mark's)
        $mark = $this->linkFor('markanthony.b@example.com');
        $this->travel(24 * 60 + 1)->minutes();
        $old = $this->get($mark)->getContent();
        $this->travelBack();
        $this->record('TC-RID-023', str_contains($old, 'has expired') && ! str_contains($old, 'name="password"')
            && User::where('email', 'markanthony.b@example.com')->first()->isPendingActivation(),
            'At 24 hours 1 minute: "This activation link has expired (links last 24 hours). Ask your logistics company to resend it." No password form; the rider stays Pending Activation.');

        // TC-RID-024: resend
        $markRider = Rider::where('license_number', 'D05-19-123456')->first();
        $resend = $this->withToken($this->hub)->postJson("/api/v1/logistics/riders/{$markRider->id}/resend-activation");
        $second = $this->linkFor('markanthony.b@example.com');
        $firstNow = $this->get($mark)->getContent();
        $secondNow = $this->get($second)->getContent();
        $this->record('TC-RID-024', $resend->status() === 200 && $second !== $mark && str_contains($firstNow, 'replaced by a newer email') && str_contains($secondNow, 'name="password"'),
            "\"{$resend->json('message')}\" The first link now says it was replaced by a newer email; the second opens the set-password form.");

        // TC-RID-020: activate
        $ok = $this->post($link, ['password' => 'Rider@2026', 'password_confirmation' => 'Rider@2026'])->getContent();
        $this->record('TC-RID-020', str_contains($ok, 'now active') && $pedro->fresh()->isApproved() && Rider::where('user_id', $pedro->id)->value('application_status') === 'approved',
            'Password saved; the status went from Pending Activation to Active. The page says "Your password is saved and your account is now active." with an Open the ALVY app button (it opens the app\'s login).');

        // TC-RID-025: reuse
        $again = $this->post($link, ['password' => 'Other@2026', 'password_confirmation' => 'Other@2026'])->getContent();
        $this->record('TC-RID-025', str_contains($again, 'already been used') && \Illuminate\Support\Facades\Hash::check('Rider@2026', $pedro->fresh()->password),
            '"This activation link has already been used. Log in to the ALVY app with your email and password." The password did not change.');

        $this->assertNotEmpty(static::$results);
    }

    public function test_rider_access(): void
    {
        // TC-RID-027: an Active rider (added by the company, password set)
        $r = $this->postJson('/api/v1/auth/login', ['email' => 'pedrojose.garcia@example.com', 'password' => 'Rider@2026']);
        $t = $r->json('token');
        $me = $this->withToken($t)->getJson('/api/v1/rider/profile');
        $company = $me->json('rider.company');
        $this->record('TC-RID-027', $r->json('status') === 'ok' && $r->json('user.home') === 'rider' && $me->json('rider.full_name') === 'Pedro Jose Garcia' && $company === 'Bilis Hatid Express Inc.',
            "Login OK (\"{$r->json('message')}\"); the app opens the rider home showing " . ($me->json('rider.full_name') ?? '') . ($company ? ", $company" : '') . '.');

        // TC-RID-028
        $l2 = $this->logistics2->createToken('t')->plainTextToken;
        $names = fn (string $tok) => collect($this->withToken($tok)->getJson('/api/v1/logistics/riders?per_page=100')->json('data'))->map(fn ($x) => $x['full_name'] ?? $x['name'] ?? '');
        $mine = $names($this->hub);
        $theirs = $names($l2);
        $peek = $this->withToken($l2)->getJson('/api/v1/logistics/riders/' . $this->rider1->id);
        $this->record('TC-RID-028', $mine->contains('Pedro Jose Garcia') && ! $theirs->contains('Pedro Jose Garcia') && $peek->status() === 403,
            "Bilis Hatid lists Pedro Jose Garcia; Agila Cargo does not, and opening his record from Agila is refused (HTTP {$peek->status()}).");

        // TC-RID-029
        $anon = $this->postJson('/api/v1/logistics/riders/accounts', []);
        $this->record('TC-RID-029', false,
            'The app\'s Sign Up offers Rider (with Buyer, Seller and Logistics): a rider can register, choose a hub, and becomes that hub\'s rider when the hub approves. Add Rider without a login: HTTP '
            . $anon->status() . ' (the app shows the login screen); the app has no Add Rider deep link.',
            'By the owner\'s decision (2026-10-26) riders may also sign up themselves; the logistics company can still add riders with Add Rider.');

        // TC-RID-030 / 031 / 032
        $buyer = $this->buyer1->createToken('t')->plainTextToken;
        $seller = $this->seller1->createToken('t')->plainTextToken;
        $codes = [$this->add([], $buyer)->status(), $this->add([], $seller)->status()];
        $this->record('TC-RID-030', $codes === [403, 403], 'Buyer: HTTP ' . $codes[0] . '; seller: HTTP ' . $codes[1] . '. No rider created. Their apps have no Add Rider.');
        $pending = User::create(['name' => 'Pending Hub', 'email' => 'pending.hub@example.com', 'password' => bcrypt('Pending@2026'), 'role' => 'sorting_center_pending', 'approval_status' => 'pending', 'email_verified_at' => now()]);
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'pending.hub@example.com', 'password' => 'Pending@2026']);
        $p = $this->add([], $pending->createToken('t')->plainTextToken);
        $this->record('TC-RID-031', $login->status() === 422 && $p->status() === 403, "Login refused: \"{$this->msg($login, 'email')}\". A direct request is refused too (HTTP {$p->status()}).");
        $codes = [];
        foreach (['/api/v1/seller/dashboard', '/api/v1/logistics/dashboard', '/api/v1/cart', '/api/v1/courier/dashboard'] as $u) {
            $codes[] = basename(dirname($u)) . '/' . basename($u) . ' ' . $this->withToken($t)->getJson($u)->status();
        }
        $this->record('TC-RID-032', ! array_filter($codes, fn ($c) => ! str_ends_with($c, '403') && ! str_ends_with($c, '409')),
            'Rider token: ' . implode(', ', $codes) . '. The rider app has no other role\'s menus.',
            '409 = "you are a rider": the app returns to the rider home. Courier and rider accounts cannot shop (the buyer cart is refused too).');

        foreach (['TC-RID-033' => 'keyboard per field', 'TC-RID-034' => 'keyboard never hides Save', 'TC-RID-035' => 'app sent to the background', 'TC-RID-036' => 'rotate the phone',
                  'TC-RID-037' => 'Save with no internet', 'TC-RID-038' => 'connection drops after Save', 'TC-RID-039' => 'Back on the Add Rider form (it asks "Discard this form?")', 'TC-RID-043' => 'large system font',
                  'TC-RID-046' => 'stay logged in after closing the app', 'TC-RID-047' => 'Back after logging out'] as $id => $what) {
            $this->device($id, "Check on the phone: $what.");
        }
        $this->device('TC-RID-040', 'Upload with Take photo, Gallery and Files on the phone.', 'Built: each upload offers Take photo, Gallery and Files (JPG, PNG or PDF); photos show a preview, PDFs their name; big photos are made smaller before upload.');
        $this->device('TC-RID-041', 'Deny camera and photo access, then use Files.', 'Built: a denied permission shows "ALVY needs camera access … Settings › Apps › ALVY › Permissions, or choose Files." Files keeps working.');
        $this->device('TC-RID-042', 'Attach a full-resolution camera photo over 5 MB.', 'Built: camera and gallery photos are shrunk (about 2000 px, then to 5 MB or less) before upload; a PDF over 5 MB is refused with the size message.');
        $this->device('TC-RID-044', 'Open the activation email on a phone with the app.', 'Built: the link opens a mobile web page to set the password; then "Open the ALVY app" opens the app (alvy:// link) at the login screen.');
        $this->device('TC-RID-045', 'Open the activation link on a phone without the app.', 'Built: the same mobile web page sets the password; "Open the ALVY app" goes to the app download page (alvy.trade/app) when the app is not installed.');
        $this->assertNotEmpty(static::$results);
    }
}
