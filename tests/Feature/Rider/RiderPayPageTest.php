<?php

namespace Tests\Feature\Rider;

use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Rider Pay: today / week / month in Philippine time, period tabs, breakdown, day by day, pending pay. */
class RiderPayPageTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1);
        config(['app.business_timezone' => 'Asia/Manila']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function delivered(Carbon $at): ParcelDelivery
    {
        $d = $this->deliveryFor($this->rider1);
        // Stored in UTC, like everything the app saves.
        $d->forceFill(['status' => 'delivered', 'delivered_at' => $at->copy()->utc()])->saveQuietly();
        return $d;
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->rider1->user)->get(route('courier.rider.earnings', $query))->assertOk();
    }

    public function test_days_follow_philippine_time_not_utc(): void
    {
        // Wednesday 07:30 in Manila is still Tuesday 23:30 in UTC.
        Carbon::setTestNow(Carbon::parse('2026-10-28 07:30', 'Asia/Manila'));
        $earlyMorning = $this->delivered(Carbon::parse('2026-10-28 06:10', 'Asia/Manila'));   // = Tue 22:10 UTC
        $this->delivered(Carbon::parse('2026-10-27 18:00', 'Asia/Manila'));                     // yesterday in Manila

        $page = $this->page(['period' => 'today']);
        $page->assertSee('data-today-pay>₱50.00', false)                 // only this morning's delivery is "today"
            ->assertSee('6:10 AM')                                         // shown in Philippine time, not 10:10 PM UTC
            ->assertSee($earlyMorning->parcel->tracking_number);
        $page->assertSee('data-week-pay>₱100.00', false);
    }

    public function test_week_starts_at_monday_midnight_in_manila(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00', 'Asia/Manila'));   // Monday morning
        $this->delivered(Carbon::parse('2026-10-26 00:30', 'Asia/Manila'));     // Monday 00:30 Manila = Sunday 16:30 UTC
        $this->delivered(Carbon::parse('2026-10-25 23:30', 'Asia/Manila'));     // Sunday night: last week

        $this->page()->assertSee('data-week-pay>₱50.00', false);
        $this->page(['period' => 'last_week'])->assertSee('data-period-pay>₱50.00', false)->assertSee('Sun, Oct 25');
    }

    public function test_breakdown_pending_and_cod(): void
    {
        $pickup = $this->pickupFor($this->rider1);
        $pickup->forceFill(['status' => 'received', 'picked_up_at' => now()])->saveQuietly();
        $this->delivered(now())->forceFill(['cod_collected' => 500])->saveQuietly();
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'failed', 'failure_reason' => 'Buyer not home'])->saveQuietly();
        $this->deliveryFor($this->rider1);                                   // still assigned: pending ₱50
        $this->pickupFor($this->rider1);                                     // not collected yet: pending ₱30

        $this->page(['period' => 'today'])
            ->assertSee('data-period-pay>₱80.00', false)                    // ₱50 delivery + ₱30 pickup
            ->assertSee('1 × ₱50')->assertSee('1 × ₱30')->assertSee('1 × ₱0')
            ->assertSee('data-pending-pay>₱80.00', false)
            ->assertSee('₱500.00')->assertSee('not your pay');
    }

    public function test_periods_are_validated_and_empty_periods_say_so(): void
    {
        $this->page(['period' => 'month'])->assertSee('No jobs in this period.');
        $this->actingAs($this->rider1->user)->get(route('courier.rider.earnings', ['period' => 'forever']))->assertSessionHasErrors('period');
    }
}
