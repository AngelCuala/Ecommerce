<?php

namespace Tests\Feature;

use App\Mail\PasswordChangeMail;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Every account's password change waits for a 6-digit code emailed to the account (web and app). */
class PasswordChangeCodeTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedRiderTestData();
    }

    /** Each app request starts fresh: in a test the guards and default headers would carry over. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $app = str_contains($uri, '/api/v1');
        if ($app) {
            $this->app['auth']->forgetGuards();
        }
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        if ($app) {
            $this->defaultHeaders = [];
        }
        return $response;
    }

    private function lastCode(User $user): string
    {
        $code = null;
        Mail::assertSent(PasswordChangeMail::class, function (PasswordChangeMail $m) use ($user, &$code) {
            if ($m->hasTo($user->email)) {
                $code = $m->code;
            }
            return true;
        });
        $this->assertNotNull($code, 'no code was emailed to ' . $user->email);
        return $code;
    }

    private function courier(): User
    {
        $user = User::create(['name' => 'Mario Santos', 'email' => 'mario.courier@example.com', 'password' => Hash::make('Kurier@2026'), 'role' => 'courier', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        Courier::create(['user_id' => $user->id, 'last_name' => 'Santos', 'first_name' => 'Mario', 'sex' => 'Male', 'contact_no' => '09181234568',
            'birthday' => '1995-05-20', 'age' => 31, 'province' => 'Laguna', 'municipality' => 'Magdalena', 'barangay' => 'Poblacion',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'ABC1234', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'approved']);
        return $user;
    }

    public function test_web_code_wrong_code_resend_and_cancel(): void
    {
        $page = route('seller.account.security');
        $change = fn () => $this->actingAs($this->seller1)->from($page)->put(route('seller.account.password'),
            ['current_password' => 'Tindahan@2026', 'password' => 'Tindahan@2027', 'password_confirmation' => 'Tindahan@2027']);

        $change()->assertRedirect($page)->assertSessionHas('success', 'We sent a 6-digit code to mariaclara.santos@example.com. Enter it to confirm your new password.');
        $this->assertTrue(Hash::check('Tindahan@2026', $this->seller1->fresh()->password));   // the old password still works
        $this->actingAs($this->seller1)->get($page)->assertSee('Confirm your new password')->assertSee('6-digit code');
        $code = $this->lastCode($this->seller1);

        // A wrong code, then an expired one.
        $verify = fn ($c) => $this->actingAs($this->seller1)->from($page)->post(route('password.change.verify'), ['code' => $c]);
        $verify($code === '111111' ? '222222' : '111111')->assertSessionHasErrors(['code' => 'That code is not correct.']);
        $verify('12ab')->assertSessionHasErrors(['code' => 'Enter the 6-digit code we sent you.']);
        $this->travel(11)->minutes();
        $verify($code)->assertSessionHasErrors(['code' => 'This code has expired. Send a new one.']);
        $this->assertTrue(Hash::check('Tindahan@2026', $this->seller1->fresh()->password));

        // A new code works; the old one doesn't.
        $this->actingAs($this->seller1)->from($page)->post(route('password.change.resend'))->assertSessionHas('success');
        $new = $this->lastCode($this->seller1);
        if ($new !== $code) {
            $verify($code)->assertSessionHasErrors('code');
        }
        $verify($new)->assertSessionHasNoErrors()->assertSessionHas('success', 'Your password has been changed successfully.');
        $user = $this->seller1->fresh();
        $this->assertTrue(Hash::check('Tindahan@2027', $user->password));
        $this->assertNull($user->pending_password);
        $this->actingAs($this->seller1)->get($page)->assertDontSee('Confirm your new password');

        // Cancelling keeps the current password.
        $this->actingAs($this->seller1)->from($page)->put(route('seller.account.password'),
            ['current_password' => 'Tindahan@2027', 'password' => 'Tindahan@2028', 'password_confirmation' => 'Tindahan@2028'])->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->from($page)->post(route('password.change.cancel'))->assertSessionHas('success', 'Password change cancelled. Your password was not changed.');
        $this->assertTrue(Hash::check('Tindahan@2027', $this->seller1->fresh()->password));
        $verify('123456')->assertSessionHas('error', 'There is no password change waiting for a code.');
    }

    /** With SMS set up, the code can go to the mobile number instead (web and app); without it, email only. */
    public function test_code_by_text_message_when_sms_is_set_up(): void
    {
        $page = route('seller.account.security');
        $change = fn (array $extra = []) => $this->actingAs($this->seller1)->from($page)->put(route('seller.account.password'),
            ['current_password' => 'Tindahan@2026', 'password' => 'Tindahan@2027', 'password_confirmation' => 'Tindahan@2027'] + $extra);
        $this->seller1->forceFill(['phone' => '09171234567', 'contact_no' => '09171234567'])->save();

        // No SMS key: no choice on the page, and code_via=sms still emails.
        config(['services.semaphore.key' => null]);
        $this->actingAs($this->seller1)->get($page)->assertDontSee('Send the confirmation code to');
        $change(['code_via' => 'sms'])->assertSessionHas('success', fn ($m) => str_contains($m, 'mariaclara.santos@example.com'));
        $this->actingAs($this->seller1)->post(route('password.change.cancel'));

        // With a key: the choice shows; a text goes to the number, and the code in it works.
        config(['services.semaphore.key' => 'test-key', 'services.semaphore.sender' => 'ALVY']);
        $texts = [];
        $refuse = false;
        \Illuminate\Support\Facades\Http::fake(['api.semaphore.co/*' => function ($request) use (&$texts, &$refuse) {
            if ($refuse) {
                return \Illuminate\Support\Facades\Http::response(['error' => 'Invalid key'], 401);
            }
            $texts[] = $request->data();
            return \Illuminate\Support\Facades\Http::response([['message_id' => 1, 'status' => 'Pending']], 200);
        }]);
        $this->actingAs($this->seller1)->get($page)->assertSee('Send the confirmation code to')->assertSee('0917*****67');
        $change(['code_via' => 'sms'])->assertSessionHas('success', 'We sent a 6-digit code to 0917*****67. Enter it to confirm your new password.');
        $this->assertCount(1, $texts);
        $this->assertSame(['test-key', '09171234567', 'ALVY'], [$texts[0]['apikey'], $texts[0]['number'], $texts[0]['sendername']]);
        $this->assertMatchesRegularExpression('/^ALVY: (\d{6}) is your code to change your password\./', $texts[0]['message']);
        preg_match('/(\d{6})/', $texts[0]['message'], $m);
        $this->actingAs($this->seller1)->get($page)->assertSee('by text')->assertSee('0917*****67');

        // Resend goes by text again; then the code changes the password.
        $this->actingAs($this->seller1)->from($page)->post(route('password.change.resend'))->assertSessionHas('success');
        $this->assertCount(2, $texts);
        preg_match('/(\d{6})/', $texts[1]['message'], $m);
        $this->actingAs($this->seller1)->from($page)->post(route('password.change.verify'), ['code' => $m[1]])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Tindahan@2027', $this->seller1->fresh()->password));

        // The gateway refusing it: a clear message, and the password stays.
        $refuse = true;
        $this->actingAs($this->seller1)->from($page)->put(route('seller.account.password'),
            ['current_password' => 'Tindahan@2027', 'password' => 'Tindahan@2028', 'password_confirmation' => 'Tindahan@2028', 'code_via' => 'sms'])
            ->assertSessionHas('error', 'We could not text the code right now. Try again, or choose email.');
        $this->assertTrue(Hash::check('Tindahan@2027', $this->seller1->fresh()->password));

        // The app: the choice, then code_via=sms with the password.
        $refuse = false;
        $token = $this->seller1->createToken('t')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/account/password/cancel')->assertOk();
        $this->withToken($token)->getJson('/api/v1/account/password/code')->assertJsonPath('sms', '0917*****67')->assertJsonPath('pending', false);
        $this->withToken($token)->putJson('/api/v1/seller/account/password', ['current_password' => 'Tindahan@2027', 'password' => 'Tindahan@2028',
            'password_confirmation' => 'Tindahan@2028', 'code_via' => 'sms'])->assertOk()
            ->assertJsonPath('code_sent', true)->assertJsonPath('sent_to.via', 'sms')->assertJsonPath('sent_to.to', '0917*****67');

        // No mobile number: email only.
        $this->buyer2->forceFill(['phone' => null, 'contact_no' => null])->save();
        $this->withToken($this->buyer2->createToken('t')->plainTextToken)->getJson('/api/v1/account/password/code')->assertJsonPath('sms', null);
    }

    public function test_too_many_wrong_codes(): void
    {
        $this->actingAs($this->seller1)->put(route('seller.account.password'),
            ['current_password' => 'Tindahan@2026', 'password' => 'Tindahan@2027', 'password_confirmation' => 'Tindahan@2027']);
        $code = $this->lastCode($this->seller1);
        $wrong = $code === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->seller1)->post(route('password.change.verify'), ['code' => $wrong]);
        }
        $this->actingAs($this->seller1)->post(route('password.change.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Too many wrong codes. Send a new one.']);
        $this->assertTrue(Hash::check('Tindahan@2026', $this->seller1->fresh()->password));
    }

    public function test_seller_courier_and_logistics_on_the_web(): void
    {
        $courier = $this->courier();
        $cases = [
            [$this->seller1, 'Tindahan@2026', fn ($d) => $this->put(route('seller.account.password'), $d), route('seller.account.security')],
            [$courier, 'Kurier@2026', fn ($d) => $this->put(route('courier.account.password'), $d), route('courier.account.security')],
            [$this->logistics1, 'Hatid@2026', fn ($d) => $this->put(route('sc.account.update'), $d + ['name' => $this->logistics1->name, 'email' => $this->logistics1->email]), route('sc.account')],
        ];
        foreach ($cases as [$user, $old, $send, $page]) {
            $this->actingAs($user)->from($page);
            $r = $send(['current_password' => $old, 'password' => 'Bago@2026x', 'password_confirmation' => 'Bago@2026x']);
            $r->assertSessionHasNoErrors()
                ->assertSessionHas('success', fn ($m) => str_contains($m, 'We sent a 6-digit code to ' . $user->email));
            $this->assertTrue(Hash::check($old, $user->fresh()->password), $user->email);
            $this->actingAs($user)->get($page)->assertOk()->assertSee('Confirm your new password');
            $this->actingAs($user)->from($page)->post(route('password.change.verify'), ['code' => $this->lastCode($user)])->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check('Bago@2026x', $user->fresh()->password), $user->email);
        }
    }

    /** The courier's contact number is 11 digits starting with 09 (+63 is turned into 09). */
    public function test_courier_contact_number_is_11_digits(): void
    {
        $courier = $this->courier();
        $save = fn ($n) => $this->actingAs($courier)->from(route('courier.account'))->put(route('courier.account.update'), ['contact_no' => $n]);
        foreach (['0918123456', '091812345678', 'abc'] as $bad) {
            $save($bad)->assertSessionHasErrors(['contact_no' => 'Mobile numbers are 11 digits starting with 09 (e.g. 09171234567).']);
        }
        $save('+639181234500')->assertSessionHasNoErrors();
        $this->assertSame('09181234500', $courier->fresh()->phone);
    }

    public function test_the_app_buyer_seller_courier_and_logistics(): void
    {
        $courier = $this->courier();
        $cases = [
            [$this->buyer1, 'Mamimili@2026', 'PATCH', '/api/v1/profile/password'],
            [$this->seller1, 'Tindahan@2026', 'PUT', '/api/v1/seller/account/password'],
            [$courier, 'Kurier@2026', 'PUT', '/api/v1/courier/account/password'],
        ];
        foreach ($cases as [$user, $old, $method, $uri]) {
            $token = $user->createToken('t')->plainTextToken;
            $r = $this->withToken($token)->json($method, $uri, ['current_password' => $old, 'password' => 'Bago@2026x!A', 'password_confirmation' => 'Bago@2026x!A']);
            $r->assertOk()->assertJsonPath('code_sent', true);
            $this->assertTrue(Hash::check($old, $user->fresh()->password), $user->email);
            $this->withToken($token)->getJson('/api/v1/account/password/code')->assertOk()->assertJsonPath('pending', true);

            $this->withToken($token)->postJson('/api/v1/account/password/verify', ['code' => '000000' === $this->lastCode($user) ? '999999' : '000000'])
                ->assertStatus(422)->assertJsonValidationErrors(['code' => 'That code is not correct.']);
            $this->withToken($token)->postJson('/api/v1/account/password/verify', ['code' => $this->lastCode($user)])
                ->assertOk()->assertJsonPath('message', 'Your password has been changed successfully.');
            $this->assertTrue(Hash::check('Bago@2026x!A', $user->fresh()->password), $user->email);
            $this->withToken($token)->getJson('/api/v1/account/password/code')->assertJsonPath('pending', false);
        }

        // Logistics: the account answer says a code is waiting; cancel keeps the old password.
        $token = $this->logistics1->createToken('t')->plainTextToken;
        $this->withToken($token)->putJson('/api/v1/logistics/account', ['name' => $this->logistics1->name, 'email' => $this->logistics1->email,
            'current_password' => 'Hatid@2026', 'password' => 'Bago@2026x', 'password_confirmation' => 'Bago@2026x'])
            ->assertOk()->assertJsonPath('password_code_pending', true);
        $this->withToken($token)->postJson('/api/v1/account/password/cancel')->assertOk();
        $this->assertTrue(Hash::check('Hatid@2026', $this->logistics1->fresh()->password));

        // Nothing waiting: a wrong-password change sends no code.
        $this->withToken($token)->putJson('/api/v1/logistics/account', ['name' => $this->logistics1->name, 'email' => $this->logistics1->email,
            'current_password' => 'nope', 'password' => 'Bago@2026x', 'password_confirmation' => 'Bago@2026x'])->assertStatus(422);
        $this->assertNull($this->logistics1->fresh()->pending_password);
    }
}
