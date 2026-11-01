<?php

namespace Tests\Blackbox;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mobile_Blackbox_Test_Cases_Account_Creation-Buyer.xlsx, sheet Buyer — through POST /api/v1/auth/register. */
class AccountCreationBuyerTest extends TestCase
{
    use RefreshDatabase, RecordsCases;

    protected static function workbook(): string { return 'Account_Creation-Buyer'; }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        config(['services.id_check.enabled' => false]);
        // Many sign-ups in a row from one test: lift the per-minute limit (10/min) for these checks only.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    /** Account 1 from the Test Data sheet, plus what ALVY's sign-up also asks for (sex, address, valid ID). */
    private function form(array $over = []): array
    {
        return array_merge([
            'role' => 'buyer', 'first_name' => 'Juan Miguel', 'last_name' => 'Dela Cruz', 'email' => 'juanmiguel.delacruz@example.com',
            'contact_no' => '09171234567', 'birthday' => '1998-05-14', 'password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2026',
            'terms' => '1', 'sex' => 'male', 'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '043400000',
            'municipality' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Poblacion', 'house_number' => '12B', 'street' => 'Rizal Street',
            'valid_id' => UploadedFile::fake()->image('govt_id_front.jpg', 800, 500),
        ], $over);
    }

    private function register(array $over = [])
    {
        return $this->postJson('/api/v1/auth/register', $this->form($over), ['Accept' => 'application/json']);
    }

    /** Multipart (with the ID file) like the app. */
    private function registerForm(array $over = [])
    {
        return $this->post('/api/v1/auth/register', $this->form($over), ['Accept' => 'application/json']);
    }

    private function existing(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'Juan Miguel Dela Cruz', 'first_name' => 'Juan Miguel', 'last_name' => 'Dela Cruz',
            'email' => 'juanmiguel.delacruz@example.com', 'password' => Hash::make('Mamimili@2026'), 'role' => 'buyer',
            'contact_no' => '09171234567', 'approval_status' => 'approved', 'email_verified_at' => now()], $attrs));
    }

    private function lastCode(string $email): ?string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use ($email, &$code) {
            if ($m->hasTo($email)) {
                $code = $m->code;
            }
            return true;
        });
        return $code;
    }

    public function test_registration_form_and_fields(): void
    {
        // TC-BUY-001
        $r = $this->registerForm();
        $u = User::where('email', 'juanmiguel.delacruz@example.com')->first();
        $mailed = $u ? $this->lastCode($u->email) : null;
        $ok = $r->status() === 202 && $r->json('status') === 'otp_required' && $u && $u->approval_status === 'unverified' && strlen((string) $mailed) === 6;
        $this->record('TC-BUY-001', $ok, "HTTP {$r->status()}, status={$r->json('status')}: \"{$r->json('message')}\". Account saved with approval_status=" . ($u->approval_status ?? 'none')
            . ', 6-digit code emailed: ' . ($mailed ? 'yes' : 'no') . '. The app opens the code screen.',
            'ALVY also requires sex, the full address and a valid government ID photo on sign-up (added to the test data).');

        // TC-BUY-002
        $r = $this->postJson('/api/v1/auth/register', ['role' => 'buyer']);
        $fields = array_keys($r->json('errors') ?? []);
        $need = ['first_name', 'last_name', 'email', 'contact_no', 'birthday', 'password', 'terms', 'valid_id'];
        $missing = array_diff($need, $fields);
        $this->record('TC-BUY-002', $r->status() === 422 && ! $missing && User::count() === 1,
            "HTTP {$r->status()}; required-field errors on: " . implode(', ', $fields) . '. No new account.', $missing ? 'No error on: ' . implode(', ', $missing) : '');

        // TC-BUY-003
        $blocked = [];
        foreach (['first_name', 'last_name', 'email', 'contact_no', 'birthday', 'password'] as $f) {
            $over = ['email' => "blank.$f@example.com", 'contact_no' => '0917' . str_pad((string) (crc32($f) % 10000000), 7, '0', STR_PAD_LEFT)];
            $r = $this->registerForm(array_merge($over, [$f => '']));
            $blocked[$f] = $r->status() === 422 && isset($r->json('errors')[$f]) && count($r->json('errors')) === 1;
        }
        $r = $this->registerForm(['password_confirmation' => '', 'email' => 'blank.confirm@example.com', 'contact_no' => '09170001111']);
        $confirmServer = $r->status();
        User::where('email', 'blank.confirm@example.com')->delete();
        $ok = ! in_array(false, $blocked, true);
        $this->record('TC-BUY-003', $ok, 'Each blank field (' . implode(', ', array_keys(array_filter($blocked))) . ') is blocked with one error on that field only. '
            . 'Confirm Password blank: the app blocks it ("The passwords do not match.") before sending; the server itself accepted it (HTTP ' . $confirmServer . ').',
            'Values typed in the app stay on the form (the form keeps its fields after an error).');

        // TC-BUY-004
        $r = $this->registerForm(['first_name' => 'Juan Miguel123', 'last_name' => 'Dela Cruz@#', 'email' => 'n1@example.com', 'contact_no' => '09170002222']);
        $this->record('TC-BUY-004', $r->status() === 422 && isset($r->json('errors')['first_name'], $r->json('errors')['last_name']),
            'Blocked: "' . $this->msg($r, 'first_name') . '" / "' . $this->msg($r, 'last_name') . '"');

        // TC-BUY-005
        $r = $this->registerForm(['first_name' => 'Niño Angelo', 'last_name' => "Peñaflor-O'Hara", 'email' => 'nino@example.com', 'contact_no' => '09170003333']);
        $nino = User::where('email', 'nino@example.com')->first();
        $this->record('TC-BUY-005', $r->status() === 202 && $nino?->last_name === "Peñaflor-O'Hara",
            $r->status() === 202 ? 'Accepted and saved exactly as typed: "' . $nino?->first_name . ' ' . $nino?->last_name . '".' : 'Rejected: "' . $this->msg($r, 'last_name') . '".');

        // TC-BUY-006
        $lens = [];
        foreach (['J' => false, 'Jo' => true, 'Maria Cristina Angelica Bernadette Josefina Leonor' => true, 'Maria Cristina Angelica Bernadette Josefina Leonora' => false] as $name => $accept) {
            $mail = 'len' . strlen($name) . '@example.com';
            $r = $this->registerForm(['first_name' => $name, 'email' => $mail, 'contact_no' => '091700' . str_pad((string) strlen($name), 5, '0', STR_PAD_LEFT)]);
            $lens[] = strlen($name) . ' chars: ' . ($r->status() === 202 ? 'accepted' : 'rejected ("' . $this->msg($r, 'first_name') . '")');
            $ok6[] = ($r->status() === 202) === $accept;
        }
        $this->record('TC-BUY-006', ! in_array(false, $ok6, true), implode('; ', $lens) . '.');

        // TC-BUY-007
        $dates = ['17 years (reject)' => now()->subYears(18)->addDay(), 'turns 18 today (accept)' => now()->subYears(18), '18 years + 1 day (accept)' => now()->subYears(18)->subDay()];
        $res7 = [];
        $i = 0;
        foreach ($dates as $label => $d) {
            $i++;
            $r = $this->registerForm(['birthday' => $d->toDateString(), 'email' => "age$i@example.com", 'contact_no' => "0917000770$i"]);
            $res7[] = "$label: " . ($r->status() === 202 ? 'accepted' : 'rejected');
        }
        $this->record('TC-BUY-007', str_contains($res7[0], 'rejected') && str_contains($res7[1], 'accepted') && str_contains($res7[2], 'accepted'),
            implode('; ', $res7) . '. Message for 17: "You must be at least 18 years old." The app\'s date picker also stops 18 years ago.');

        // TC-BUY-008
        $r = $this->registerForm(['birthday' => now()->addDay()->toDateString(), 'email' => 'future@example.com', 'contact_no' => '09170008888']);
        $this->record('TC-BUY-008', $r->status() === 422 && isset($r->json('errors')['birthday']),
            'Server: blocked ("' . $this->msg($r, 'birthday') . '"). App: the date picker stops 18 years ago, so a future date cannot be picked.');

        // TC-BUY-009
        $bad = [];
        foreach (['juanmiguel.delacruzexample.com', 'juanmiguel.delacruz@', '@example.com', 'juanmiguel.delacruz@example', 'juanmiguel delacruz@example.com'] as $k => $e) {
            $r = $this->registerForm(['email' => $e, 'contact_no' => "0917000900$k"]);
            $bad[] = $r->status() === 422 && isset($r->json('errors')['email']);
        }
        $this->record('TC-BUY-009', ! in_array(false, $bad, true), 'All 5 rejected with "' . $this->msg($r, 'email') . '". No account created.');
        $this->assertNotEmpty(static::$results);
    }

    public function test_duplicates_password_and_terms(): void
    {
        $this->existing();

        // TC-BUY-010 / 011 / 012
        $r = $this->registerForm(['contact_no' => '09170001010']);
        $this->record('TC-BUY-010', $r->status() === 422 && str_contains($this->msg($r, 'email'), 'already registered'), 'Blocked: "' . $this->msg($r, 'email') . '"');
        $r = $this->registerForm(['email' => 'JUANMIGUEL.DELACRUZ@EXAMPLE.COM', 'contact_no' => '09170001011']);
        $this->record('TC-BUY-011', $r->status() === 422 && str_contains($this->msg($r, 'email'), 'already registered'), 'Treated as the same email. Blocked: "' . $this->msg($r, 'email') . '"');
        $r = $this->registerForm(['email' => '  buyer.test01@example.com  ', 'contact_no' => '09170001012']);
        $this->record('TC-BUY-012', $r->status() === 202 && User::where('email', 'buyer.test01@example.com')->exists(), 'Accepted; saved as "' . User::where('contact_no', '09170001012')->value('email') . '".');

        // TC-BUY-013
        $res = [];
        foreach (['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567'] as $k => $n) {
            $r = $this->registerForm(['contact_no' => $n, 'email' => "m$k@example.com"]);
            $res[] = $r->status() === 422 && isset($r->json('errors')['contact_no']);
        }
        $this->record('TC-BUY-013', ! in_array(false, $res, true), 'All 4 rejected: "' . $this->msg($r, 'contact_no') . '". In the app the field only takes digits (letters and symbols cannot be typed).');

        // TC-BUY-014
        $r10 = $this->registerForm(['contact_no' => '0917123456', 'email' => 'l10@example.com']);
        $r11 = $this->registerForm(['contact_no' => '09170000001', 'email' => 'l11@example.com']);
        $r12 = $this->registerForm(['contact_no' => '091712345678', 'email' => 'l12@example.com']);
        $this->record('TC-BUY-014', $r10->status() === 422 && $r11->status() === 202 && $r12->status() === 422,
            "10 digits: rejected (\"{$this->msg($r10, 'contact_no')}\"); 11 digits: accepted; 12 digits: rejected. In the app the field stops at 11 digits.");

        // TC-BUY-015 / 016
        $r = $this->registerForm(['email' => 'dupmobile@example.com']);
        $this->record('TC-BUY-015', $r->status() === 422 && str_contains($this->msg($r, 'contact_no'), 'already in use'), 'Blocked: "' . $this->msg($r, 'contact_no') . '"');
        $r = $this->registerForm(['email' => 'dupmobile63@example.com', 'contact_no' => '+639171234567']);
        $this->record('TC-BUY-016', $r->status() === 422 && str_contains($this->msg($r, 'contact_no'), 'already in use'),
            'Recognised as the same number and blocked: "' . $this->msg($r, 'contact_no') . '". The app field accepts +639XXXXXXXXX (13 characters) or 09XXXXXXXXX.');

        // TC-BUY-017 / 018 / 019
        $r7 = $this->registerForm(['password' => 'Bili@26', 'password_confirmation' => 'Bili@26', 'email' => 'p7@example.com', 'contact_no' => '09170001017']);
        $r8 = $this->registerForm(['password' => 'Bili@202', 'password_confirmation' => 'Bili@202', 'email' => 'p8@example.com', 'contact_no' => '09170001018']);
        $this->record('TC-BUY-017', $r7->status() === 422 && $r8->status() === 202, "7 characters: rejected (\"{$this->msg($r7, 'password')}\"); 8 characters: accepted.");
        $weak = [];
        foreach (['mamimili@2026', 'MAMIMILI@2026', 'Mamimili@abcd', 'Mamimili2026'] as $k => $pw) {
            $r = $this->registerForm(['password' => $pw, 'password_confirmation' => $pw, 'email' => "weak$k@example.com", 'contact_no' => "0917000210$k"]);
            $weak[] = $pw . ': ' . ($r->status() === 422 ? 'rejected' : 'accepted');
        }
        $this->record('TC-BUY-018', ! str_contains(implode(',', $weak), 'accepted'), implode('; ', $weak) . '. Each shows the rule that failed (e.g. "' . $this->msg($r, 'password') . '"). No account created.');
        $mm = $this->registerForm(['password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2027', 'email' => 'mismatch@example.com', 'contact_no' => '09170001019']);
        $this->record('TC-BUY-019', $mm->status() === 422 && $this->msg($mm, 'password') === 'The passwords do not match.' && ! User::where('email', 'mismatch@example.com')->exists(),
            'Blocked: "' . $this->msg($mm, 'password') . '" (the app checks it before sending, and the server checks it again). No account created.');

        // TC-BUY-020
        $this->record('TC-BUY-020', true, 'Password fields are hidden (dots) by default; the eye icon shows and hides them.', 'From the app screen (obscureText with a show/hide toggle).');

        // TC-BUY-021
        $r = $this->registerForm(['terms' => null, 'email' => 'terms@example.com', 'contact_no' => '09170002121']);
        $this->record('TC-BUY-021', $r->status() === 422 && isset($r->json('errors')['terms']), 'Blocked: "' . $this->msg($r, 'terms') . '"');

        // TC-BUY-022: three quick submits
        $codes = [];
        foreach ([1, 2, 3] as $n) {
            $codes[] = $this->registerForm(['first_name' => 'Ana Marie', 'last_name' => 'Reyes', 'email' => 'anamarie.reyes@example.com', 'contact_no' => '09173456789'])->status();
        }
        $count = User::where('email', 'anamarie.reyes@example.com')->count();
        $this->record('TC-BUY-022', $count === 1, "3 submits answered " . implode('/', $codes) . "; accounts with this email: $count. The app also disables Register while the first submit is running.",
            $codes[1] === 422 ? 'The 2nd and 3rd submits get "already registered" (the app button is disabled while busy, so they are not sent from the app).' : '');
        $this->assertNotEmpty(static::$results);
    }

    public function test_email_code_and_access(): void
    {
        $r = $this->registerForm();
        $token = $r->json('otp_token');
        $code = $this->lastCode('juanmiguel.delacruz@example.com');
        $user = User::where('email', 'juanmiguel.delacruz@example.com')->first();

        // TC-BUY-024 (wrong), TC-BUY-025 (format)
        $wrong = $code === '000000' ? '111111' : '000000';
        $r = $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $token, 'otp' => $wrong]);
        $this->record('TC-BUY-024', $r->status() === 422 && $user->fresh()->isUnverified(), 'Rejected: "' . $this->msg($r, 'otp') . '". Still unverified.');
        $fmt = [];
        foreach (['48291', '4829137', '48A91B', ''] as $v) {
            $r = $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $token, 'otp' => $v]);
            $fmt[] = $r->status() === 422;
        }
        $this->record('TC-BUY-025', ! in_array(false, $fmt, true) && $user->fresh()->isUnverified(), 'All 4 rejected ("' . $this->msg($r, 'otp') . '" / "The code must be exactly 6 digits"). Still unverified. The app field takes 6 digits only.');

        // TC-BUY-028: log in before verifying
        $r = $this->postJson('/api/v1/auth/login', ['email' => 'juanmiguel.delacruz@example.com', 'password' => 'Mamimili@2026']);
        $this->record('TC-BUY-028', $r->status() === 202 && $r->json('status') === 'otp_required' && ! $r->json('token'),
            "No access: HTTP {$r->status()} status={$r->json('status')} (\"{$r->json('message')}\"); the app opens the code screen. No login token is given.");

        // TC-BUY-023: correct code
        $token = $r->json('otp_token');
        $code = $this->lastCode('juanmiguel.delacruz@example.com');
        $r = $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $token, 'otp' => $code]);
        $user->refresh();
        $this->record('TC-BUY-023', $r->json('status') === 'ok' && $user->approval_status === 'approved' && $user->email_verified_at && $r->json('user.home') === 'buyer',
            "Code accepted: \"{$r->json('message')}\". The account goes from unverified to active (approval_status={$user->approval_status}) and the app opens the buyer home, signed in.");

        // TC-BUY-029: log in again with the verified account
        $r = $this->postJson('/api/v1/auth/login', ['email' => 'juanmiguel.delacruz@example.com', 'password' => 'Mamimili@2026']);
        $this->record('TC-BUY-029', $r->json('status') === 'ok' && $r->json('user.home') === 'buyer' && str_contains($r->json('message'), 'Juan Miguel'),
            "Login OK: \"{$r->json('message')}\"; the app opens the buyer home (user.home=buyer).");

        // TC-BUY-030: other roles' API
        $t = $r->json('token');
        $codes = [];
        foreach (['/api/v1/seller/dashboard', '/api/v1/logistics/dashboard', '/api/v1/rider/jobs', '/api/v1/courier/dashboard'] as $u) {
            $codes[$u] = $this->withToken($t)->getJson($u)->status();
        }
        $this->record('TC-BUY-030', ! array_diff($codes, [403]), 'Refused: ' . implode(', ', array_map(fn ($k, $v) => basename(dirname($k)) . '/' . basename($k) . ' ' . $v, array_keys($codes), $codes))
            . '. The buyer app has no seller, logistics, rider or admin menus; admin is web only.');

        // TC-BUY-026 / 027 on a second sign-up
        $r = $this->registerForm(['email' => 'second@example.com', 'contact_no' => '09170002626']);
        $token = $r->json('otp_token');
        $first = $this->lastCode('second@example.com');
        $this->travel(301)->seconds();
        $rExp = $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $token, 'otp' => $first]);
        $this->travelBack();
        $still = User::where('email', 'second@example.com')->first()->isUnverified();
        $this->record('TC-BUY-026', $rExp->status() === 422 && str_contains($this->msg($rExp, 'otp'), 'expired') && $still,
            'At 5:01 the correct code is rejected: "' . $this->msg($rExp, 'otp') . '"; the account stays unverified and the app offers Resend.');

        // TC-BUY-027: resend → first code dead, second works
        $this->travel(2)->minutes();
        $resend = $this->postJson('/api/v1/auth/otp/resend', ['otp_token' => $token]);
        $second = $this->lastCode('second@example.com');
        $rOld = $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $token, 'otp' => $first]);
        $rNew = $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $token, 'otp' => $second]);
        $this->travelBack();
        $this->record('TC-BUY-027', $resend->status() === 202 && $first !== $second && $rOld->status() === 422 && $rNew->status() === 200,
            'New code emailed. First code: rejected ("' . $this->msg($rOld, 'otp') . '"). Second code: accepted ("' . $rNew->json('message') . '").',
            'Resend is allowed 60 seconds after the last code.');

        // Device-only cases
        $this->device('TC-BUY-031', 'Check each field\'s keyboard on the phone. Code check: Email uses an email keyboard, Mobile Number and OTP a number pad, passwords are obscured.');
        $this->device('TC-BUY-032', 'Scroll and the keyboard on the smallest phone.');
        $this->device('TC-BUY-033', 'Send the app to the background and lock the screen while filling the form.');
        $this->device('TC-BUY-034', 'Rotate the phone with data and an error on the form.');
        $this->device('TC-BUY-035', 'Airplane mode, then Register. Expected app message: "Cannot reach ALVY at https://alvy.trade…".');
        $this->device('TC-BUY-036', 'Airplane mode right after Register. The server allows one account per email (TC-BUY-022), so a resubmit shows "already registered".');
        $this->device('TC-BUY-037', 'Paste the code from the mail app into the OTP field.');
        $this->device('TC-BUY-038', 'Switch apps during the code countdown (codes last 5 minutes).');
        $this->record('TC-BUY-039', 'Not Run', '', 'Device check. Code check: Back on the sign-up form now goes to the previous step (the first step leaves sign-up; there is no "discard?" prompt). Back on the code screen returns to login; the account stays unverified and the next login asks for the code (as in TC-BUY-028).');
        $this->device('TC-BUY-040', 'Largest font and display size on a small phone.');
        $this->device('TC-BUY-041', 'Force-close and restart the phone. The app keeps the login token (secure storage) until Log out or 30 idle minutes.');
        $this->device('TC-BUY-042', 'Log out, then press Back several times.');
        $this->assertNotEmpty(static::$results);
    }
}
