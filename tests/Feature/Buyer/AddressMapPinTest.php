<?php

namespace Tests\Feature\Buyer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Address form "pin on the map": the pin is saved, and a pin is turned into PSGC picks. */
class AddressMapPinTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
    }

    /** Same valid address as B01AccountAndAddressTest (Makati, NCR). */
    private function addressForm(array $over = []): array
    {
        return $over + ['label' => 'Office', 'full_name' => 'Juan Miguel Dela Cruz', 'phone' => '09171234567',
            'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'City of Makati', 'barangay' => 'San Lorenzo',
            'address_line' => '12F Pacific Tower, 6750 Ayala Ave.', 'zip' => '1223',
            'municipality_code' => '137602000', 'barangay_code' => '137602025'];
    }

    public function test_pin_is_saved_with_the_address(): void
    {
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm(['latitude' => '14.5547', 'longitude' => '121.0244']))
            ->assertSessionHasNoErrors();
        $a = $this->buyer2->addresses()->latest('id')->first();
        $this->assertEqualsWithDelta(14.5547, $a->latitude, 0.0001);
        $this->assertEqualsWithDelta(121.0244, $a->longitude, 0.0001);
    }

    public function test_pin_is_optional_but_must_be_in_the_philippines(): void
    {
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm())->assertSessionHasNoErrors();
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm(['latitude' => '51.5', 'longitude' => '-0.12']))
            ->assertSessionHasErrors(['latitude', 'longitude']);
        $this->actingAs($this->buyer2)->post(route('addresses.store'), $this->addressForm(['latitude' => '14.55']))
            ->assertSessionHasErrors('longitude');
    }

    public function test_pin_is_matched_to_region_province_city_and_barangay(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            'display_name' => 'Elejorde Street, Barangay 5, Calamba, Laguna, Calabarzon, 4027, Philippines',
            'address' => [
                'road' => 'Elejorde Street', 'quarter' => 'Barangay 5', 'city' => 'Calamba',
                'state' => 'Laguna', 'region' => 'Calabarzon', 'postcode' => '4027', 'country_code' => 'ph',
            ],
        ])]);

        $this->getJson(route('geo.reverse', ['lat' => 14.2117, 'lon' => 121.1653]))->assertOk()
            ->assertJsonPath('province.name', 'Laguna')
            ->assertJsonPath('city.name', 'City of Calamba')
            ->assertJsonPath('barangay.name', 'Barangay 5')
            ->assertJsonPath('zip', '4027')
            ->assertJsonPath('street', 'Elejorde Street');
    }

    public function test_pin_outside_the_philippines_is_refused(): void
    {
        $this->getJson(route('geo.reverse', ['lat' => 35.68, 'lon' => 139.69]))->assertStatus(422);
    }
}
