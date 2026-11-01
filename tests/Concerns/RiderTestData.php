<?php

namespace Tests\Concerns;

use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Services\RiderDispatch;

/**
 * The Rider Modules (R1–R7) test data: Logistics 1 (Bilis Hatid Express) with Riders 1–3
 * (Rider 3 suspended), Logistics 2 (Agila Cargo) with its own rider, and parcels from
 * Seller 1's orders. Builds on the seller/buyer test data.
 */
trait RiderTestData
{
    use SellerTestData;

    protected User $logistics1, $logistics2;
    protected Rider $rider1, $rider2, $rider3, $agilaRider;
    protected DeliveryArea $central;

    protected function seedRiderTestData(): void
    {
        $this->seedSellerTestData();

        $this->logistics1 = $this->company('operations@bilishatid.example.com', 'Carlos Antonio Mendoza', 'Bilis Hatid Express Inc.', 'Hatid@2026');
        $this->logistics2 = $this->company('admin@agilacargo.example.com', 'Liza Mae Fernandez', 'Agila Cargo Movers Corp.', 'Agila#Cargo26');
        $this->central = DeliveryArea::create(['sorting_center_id' => $this->logistics1->id, 'name' => 'Central', 'code' => 'QC-CENTRAL']);

        $this->rider1 = $this->riderAccount($this->logistics1, 'pedrojose.garcia@example.com', 'Pedro Jose Garcia', 'Rider@2026', 'Motorcycle', '123ABC',
            ['license_number' => 'N01-23-456789', 'license_expiry' => '2030-03-22', 'license_path' => 'rider-documents/pedro-license.jpg', 'or_cr_path' => 'rider-documents/pedro-orcr.pdf']);
        $this->rider2 = $this->riderAccount($this->logistics1, 'markanthony.bautista@example.com', 'Mark Anthony Bautista', 'Hatid#Mark26', 'Van', 'NBC1234');
        $this->rider3 = $this->riderAccount($this->logistics1, 'rider.test01@example.com', 'Ramon Cruz', 'Rider@2026', 'Motorcycle', '456DEF', ['is_active' => false]);
        $this->agilaRider = $this->riderAccount($this->logistics2, 'rider.agila@example.com', 'Agila Rider', 'Rider@2026', 'Motorcycle', '999ZZZ');

        \Illuminate\Support\Facades\Storage::disk('local')->put('rider-documents/pedro-license.jpg', 'license');
        \Illuminate\Support\Facades\Storage::disk('local')->put('rider-documents/pedro-orcr.pdf', '%PDF-1.4');
    }

    private function company(string $email, string $name, string $business, string $password): User
    {
        $u = User::create(['name' => $name, 'email' => $email, 'password' => \Illuminate\Support\Facades\Hash::make($password), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
        SortingCenterApplication::forceCreate(['user_id' => $u->id, 'business_name' => $business, 'status' => 'approved',
            'last_name' => $name, 'first_name' => $name, 'sex' => 'Male', 'birthday' => '1985-01-01', 'age' => 41, 'contact_no' => '09170000010',
            'municipality' => 'Quezon City', 'municipality_code' => '137404000', 'barangay' => 'Central', 'street' => 'EDSA', 'house_number' => '1',
            'address' => 'Quezon City', 'government_id_path' => 'ids/x.jpg', 'business_permit_path' => 'permits/x.pdf']);
        return $u;
    }

    private function riderAccount(User $company, string $email, string $name, string $password, string $vehicle, string $plate, array $extra = []): Rider
    {
        $user = $this->account('courier', $email, $name, $password);
        return Rider::create($extra + ['user_id' => $user->id, 'sorting_center_id' => $company->id, 'full_name' => $name, 'phone' => '09170000011',
            'vehicle_type' => $vehicle, 'plate_number' => $plate, 'area_id' => $this->central->id ?? null,
            'application_status' => 'approved', 'approved_at' => now(), 'is_active' => true]);
    }

    protected function online(Rider ...$riders): void
    {
        foreach ($riders as $r) {
            $r->forceFill(['is_online' => true, 'last_seen_at' => now()])->save();
        }
    }

    /** Seller 1's COD order for P-02 to ADDR-1 (₱450 + ₱50 shipping = ₱500.00), as a parcel at Logistics 1. */
    protected function parcel(string $status = 'pickup_approved', array $extra = [], ?Order $order = null, ?User $company = null): Parcel
    {
        $order ??= $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $order->update(['status' => Order::PROCESSING, 'accepted_at' => now(), 'packed_at' => now()]);

        return Parcel::create($extra + [
            'tracking_number' => Parcel::generateTracking(), 'order_id' => $order->id, 'seller_id' => $this->seller1->id,
            'pickup_address' => '12 Tandang Sora Ave., Commonwealth, Quezon City', 'dropoff_address' => $order->fullAddress(),
            'destination_municipality' => 'Quezon City', 'destination_province' => 'Metro Manila',
            'receiver_name' => $order->full_name, 'receiver_phone' => $order->phone, 'weight_kg' => 0.8,
            'current_sorting_center_id' => ($company ?? $this->logistics1)->id, 'area_id' => $this->central->id, 'status' => $status,
        ]);
    }

    /** A delivery job already with the rider (status assigned or out for delivery). */
    protected function deliveryFor(Rider $rider, string $status = 'assigned', ?Parcel $parcel = null): ParcelDelivery
    {
        $parcel ??= $this->parcel('sorted');
        $order = $parcel->order;
        $d = ParcelDelivery::create(['parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'area_id' => $parcel->area_id, 'status' => 'assigned',
            'attempt' => $parcel->deliveries()->count() + 1,
            'cod_amount' => strtoupper($order->payment_method) === 'COD' && $order->payment_status !== 'Paid' ? $order->total_price : 0]);
        $parcel->update(['status' => 'assigned']);
        if ($status === 'out_for_delivery') {
            $this->actingAs($rider->user)->post(route('courier.rider.deliveries.start', $d->id))->assertSessionHas('success');
        }
        return $d->fresh();
    }

    protected function pickupFor(Rider $rider, ?Parcel $parcel = null): ParcelPickup
    {
        $parcel ??= $this->parcel();
        return ParcelPickup::create(['parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'sorting_center_id' => $parcel->current_sorting_center_id, 'status' => 'assigned']);
    }

    protected function dispatcher(): RiderDispatch
    {
        return app(RiderDispatch::class);
    }
}
