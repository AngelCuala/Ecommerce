<?php

namespace Tests\Feature\Logistics;

use App\Exceptions\ParcelOnHold;
use App\Models\ParcelDelivery;
use App\Models\ShipmentException;
use App\Models\User;
use App\Services\ShipmentWorkflow;
use App\Support\ProtectedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** A flagged parcel is on hold until an administrator resolves the flag; proof-of-pickup photos open through a signed link. */
class FlaggedParcelHoldTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_a_flagged_parcel_cannot_be_assigned_or_moved_until_resolved(): void
    {
        $parcel = $this->parcel('sorted');

        $this->actingAs($this->logistics1)->post(route('sc.tracking.exception', $parcel), ['type' => 'missing', 'details' => 'Not on the shelf.'])
            ->assertSessionHas('success');
        $this->assertNotNull($parcel->fresh()->openHold());

        // Assigning a rider is refused; nothing changes.
        $this->actingAs($this->logistics1)->from(route('sc.pickup-requests'))
            ->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'on hold'));
        $this->assertSame('sorted', $parcel->fresh()->status);
        $this->assertSame(0, ParcelDelivery::where('parcel_id', $parcel->id)->count());
        $this->assertFalse($this->dispatcher()->waiting($this->logistics1->id)->contains('id', $parcel->id));

        // Any other status change is refused too.
        try {
            $parcel->fresh()->update(['status' => 'assigned']);
            $this->fail('A flagged parcel was moved.');
        } catch (ParcelOnHold) {
        }

        // An administrator resolves it: the parcel can be processed again.
        $admin = User::create(['name' => 'Admin', 'email' => 'admin-hold@test.local', 'password' => 'secret-Pass1', 'role' => 'admin']);
        app(ShipmentWorkflow::class)->resolveException(ShipmentException::where('parcel_id', $parcel->id)->firstOrFail(), $admin, 'note', 'Found it behind the rack.');
        $this->assertNull($parcel->fresh()->openHold());
        $this->assertTrue($this->dispatcher()->waiting($this->logistics1->id)->contains('id', $parcel->id));
    }

    public function test_proof_of_pickup_photos_open_through_a_signed_link(): void
    {
        $this->assertTrue(ProtectedFile::isProtected('proof-of-pickup/photo.jpg'));
        $this->assertStringContainsString('/files/proof-of-pickup/photo.jpg', ProtectedFile::url('proof-of-pickup/photo.jpg'));
    }
}
