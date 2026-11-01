<?php

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\SellerApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's "Become a seller / courier" for a signed-in buyer: the website's applications, same rules. */
class BecomeSellerCourierApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedRiderTestData();
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();
        return $this->buyer1->createToken('t')->plainTextToken;
    }

    public function test_a_buyer_applies_to_sell_and_stays_a_buyer_until_approved(): void
    {
        $doc = fn (string $n) => UploadedFile::fake()->image($n . '.jpg');
        $data = [
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'phone' => '09179998888',
            'shop_name' => 'Juan Crafts', 'line_of_business' => \App\Support\SellerApplicationRules::categories()[0],
            'seller_type' => 'Individual', 'tin' => '123-456-789-000',
            'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Central',
            'street' => '12 Mabini St.', 'zip_code' => '1100', 'terms' => '1',
            'government_id' => $doc('id'), 'dti_certificate' => $doc('dti'), 'bir_2303' => $doc('bir'),
        ];

        // The account password confirms it is really the owner.
        $this->withToken($this->token())->post('/api/v1/become-seller', $data + ['password' => 'wrong', 'password_confirmation' => 'wrong'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('password');

        $this->withToken($this->token())->post('/api/v1/become-seller', $data + ['password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2026'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('message', 'Your seller application has been submitted! We will review it shortly.')
            ->assertJsonPath('user.role', 'buyer');
        $app = SellerApplication::where('user_id', $this->buyer1->id)->sole();
        $this->assertSame('pending', $app->status);
        Storage::disk('local')->assertExists($app->government_id_path);   // private, like the website

        $this->withToken($this->token())->post('/api/v1/become-seller', $data + ['password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2026'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', 'You already have an active or approved application.');
    }

    public function test_a_buyer_applies_to_deliver_and_becomes_a_pending_courier(): void
    {
        $data = [
            'first_name' => 'Juan', 'last_name' => 'Cruz', 'sex' => 'Male', 'contact_no' => '09179998887', 'birthday' => '1995-05-05',
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Central', 'street' => 'Mabini St.', 'house_number' => '12',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'ABC 1234', 'sorting_center_id' => $this->logistics1->id,
            'or_cr' => UploadedFile::fake()->image('orcr.jpg'), 'id_upload' => UploadedFile::fake()->image('id.jpg'),
        ];

        $this->withToken($this->token())->post('/api/v1/become-courier', ['vehicle_type' => 'Boat'] + $data, ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('vehicle_type');

        $this->withToken($this->token())->post('/api/v1/become-courier', $data, ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('user.role', 'courier_pending');   // the app opens the courier status screen
        $courier = Courier::where('user_id', $this->buyer1->id)->sole();
        $this->assertSame('pending', $courier->status);
        $this->assertSame($this->logistics1->id, (int) $courier->sorting_center_id);
    }
}
