<?php

namespace Tests\Feature\Admin;

use App\Models\DeliveryArea;
use App\Models\LogisticsHub;
use App\Models\Rider;
use App\Models\SortingCenterApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin › Accounts › Sorting centers: every logistics company with its status, coverage and riders. */
class SortingCenterDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin', 'admin@alvy.test', 'Admin');
    }

    private function user(string $role, string $email, string $name, array $extra = []): User
    {
        return User::forceCreate($extra + ['name' => $name, 'email' => $email, 'password' => Hash::make('x'), 'role' => $role, 'approval_status' => 'approved']);
    }

    private function center(string $role, string $email, string $business, string $appStatus, array $extra = []): User
    {
        $user = $this->user($role, $email, 'Contact ' . $business, $extra);
        SortingCenterApplication::forceCreate([
            'user_id' => $user->id, 'business_name' => $business, 'status' => $appStatus,
            'first_name' => 'Ana', 'last_name' => 'Cruz', 'sex' => 'Female', 'birthday' => '1990-01-01', 'age' => 36, 'contact_no' => '09170000000',
            'municipality' => 'Calamba', 'municipality_code' => '043405000', 'barangay' => 'Uno', 'street' => 'Rizal St', 'house_number' => '1',
            'address' => '1 Rizal St', 'government_id_path' => 'ids/x.jpg', 'business_permit_path' => 'permits/x.pdf',
        ]);

        return $user;
    }

    public function test_lists_each_sorting_center_with_its_status_hubs_barangays_and_riders(): void
    {
        $active = $this->center('sorting_center', 'cal@alvy.test', 'Calamba Express', 'approved', ['assigned_municipality' => 'City of Calamba']);
        $this->center('sorting_center_pending', 'new@alvy.test', 'New Freight', 'pending');
        $this->center('sorting_center_pending', 'no@alvy.test', 'Refused Cargo', 'rejected');
        $this->center('suspended', 'off@alvy.test', 'Paused Movers', 'approved');
        $this->user('buyer', 'buyer@alvy.test', 'Just A Buyer');

        LogisticsHub::create(['sorting_center_id' => $active->id, 'code' => 'HUB-CAL', 'name' => 'Calamba Hub']);
        foreach (['CAL-01', 'CAL-02'] as $code) {
            DeliveryArea::create(['sorting_center_id' => $active->id, 'name' => $code, 'code' => $code]);
        }
        $rider = $this->user('courier', 'rider@alvy.test', 'Rider One');
        Rider::forceCreate(['user_id' => $rider->id, 'full_name' => 'Rider One', 'sorting_center_id' => $active->id, 'application_status' => 'approved', 'is_active' => true]);

        $page = $this->actingAs($this->admin)->get(route('admin.sorting-centers.index'))->assertOk();
        $page->assertSee('Calamba Express')->assertSee('New Freight')->assertSee('Refused Cargo')->assertSee('Paused Movers')
            ->assertDontSee('Just A Buyer')
            ->assertSee('City of Calamba')
            ->assertSee('2 barangays')
            ->assertSee(route('admin.riders.index', ['center' => $active->id]), false);

        $this->assertSame(['Calamba Express'], $this->namesFor('active'));
        $this->assertSame(['New Freight'], $this->namesFor('pending'));
        $this->assertSame(['Refused Cargo'], $this->namesFor('rejected'));
        $this->assertSame(['Paused Movers'], $this->namesFor('suspended'));
        $this->assertSame(['Calamba Express'], $this->namesFor(null, 'calamba'));
    }

    public function test_the_tab_shows_on_every_account_page_and_only_admins_can_open_it(): void
    {
        foreach (['admin.customers.index', 'admin.sellers.index', 'admin.riders.index', 'admin.users.index', 'admin.sorting-centers.index'] as $page) {
            $this->actingAs($this->admin)->get(route($page))->assertOk()
                ->assertSee(route('admin.sorting-centers.index'), false)->assertSee('Sorting centers');
        }

        $this->actingAs($this->user('buyer', 'b2@alvy.test', 'Buyer'))->get(route('admin.sorting-centers.index'))->assertForbidden();
    }

    private function namesFor(?string $status, ?string $search = null): array
    {
        $centers = $this->actingAs($this->admin)
            ->get(route('admin.sorting-centers.index', array_filter(['status' => $status, 'search' => $search])))
            ->assertOk()->viewData('centers');

        return collect($centers->items())->pluck('business_name')->sort()->values()->all();
    }
}
