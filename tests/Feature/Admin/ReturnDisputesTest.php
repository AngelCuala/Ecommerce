<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** A return the seller rejected and the buyer escalated reaches the admin, who decides it: refund the buyer or close it. */
class ReturnDisputesTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        $this->admin = $this->account('admin', 'admin@alvy.example.com', 'ALVY Admin');
    }

    /** One COD order of 2 × P-02, delivered, returned, rejected by the seller and escalated. */
    private function disputed(): array
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first(), Order::DELIVERED);
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.', 'evidence' => $this->jpg('crushed.jpg', 800),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->from(route('seller.orders.show', $order->id))
            ->post(route('seller.orders.return', $order->id), ['decision' => 'reject', 'seller_note' => 'It was fine when it left the shop.']);
        $this->actingAs($this->buyer1)->post(route('orders.escalate', $order->id), ['dispute_note' => 'It was crushed on arrival, see the photo.'])
            ->assertSessionHas('success');

        return [$order->fresh(), ReturnRequest::sole()];
    }

    private function decide(ReturnRequest $return, array $data)
    {
        return $this->actingAs($this->admin)->from(route('admin.disputes.show', $return))->post(route('admin.disputes.decide', $return), $data);
    }

    public function test_the_admin_sees_the_case_and_refunds_the_buyer(): void
    {
        [$order, $return] = $this->disputed();
        $this->assertSame([Order::IN_DISPUTE, 'disputed'], [$order->status, $return->status]);

        // Every admin is told, with a link to the case; the menu shows one waiting.
        $note = UserNotification::where('user_id', $this->admin->id)->where('title', 'Return dispute to decide')->sole();
        $this->assertStringContainsString('/admin/disputes/' . $return->id, $note->link);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Return Disputes');

        // The list and the case: the buyer's reason and photo, the seller's rejection, the buyer's dispute note.
        $this->actingAs($this->admin)->get(route('admin.disputes.index'))->assertOk()
            ->assertSee('data-dispute="' . $return->id . '"', false)->assertSee($order->number())->assertSee('Damaged item');
        $this->actingAs($this->admin)->get(route('admin.disputes.show', $return))->assertOk()
            ->assertSee('The basket arrived crushed.')->assertSee('It was fine when it left the shop.')
            ->assertSee('It was crushed on arrival, see the photo.')->assertSee('data-evidence', false)
            ->assertSee('Refund the buyer ₱' . number_format((float) $return->refund_amount, 2))->assertSee('data-decide-form', false);
        // The order page sends the admin to the case.
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order->id))->assertSee('data-open-dispute', false);

        // A reason is required; then the refund overrides the seller.
        $this->decide($return, ['decision' => 'refund'])->assertSessionHasErrors('reason');
        $this->decide($return, ['decision' => 'refund', 'reason' => 'The photo shows the basket crushed on arrival.'])
            ->assertRedirect(route('admin.disputes.show', $return))->assertSessionHas('success');

        $return->refresh();
        $order->refresh();
        $this->assertSame(['approved', 'refund', $this->admin->id], [$return->status, $return->admin_decision, $return->admin_decided_by]);
        $this->assertSame(Order::REFUNDED, $order->status);
        $this->assertEquals((float) $return->refund_amount, (float) $order->refunded_amount);   // COD: recorded as refunded to the buyer
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'ALVY decided your dispute: refund approved')
            ->where('body', 'like', '%The photo shows the basket crushed on arrival.%')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Return refunded')->exists());
        // The rejection had cancelled the pickup; with the refund the item is collected after all.
        $this->assertSame('requested', ReturnShipment::where('return_request_id', $return->id)->sole()->status);

        // Decided: shown as such, and it can't be decided twice.
        $this->actingAs($this->admin)->get(route('admin.disputes.show', $return))->assertSee('Decided: refunded')->assertDontSee('data-decide-form', false);
        $this->decide($return, ['decision' => 'close', 'reason' => 'Changing my mind about this one.'])->assertSessionHas('error', fn ($m) => str_contains($m, 'already decided'));
        $this->actingAs($this->admin)->get(route('admin.disputes.index', ['tab' => 'decided']))->assertSee('data-dispute="' . $return->id . '"', false);
        $this->actingAs($this->admin)->get(route('admin.disputes.index'))->assertDontSee('data-dispute="' . $return->id . '"', false);
    }

    public function test_the_admin_closes_it_without_a_refund(): void
    {
        [$order, $return] = $this->disputed();

        $this->decide($return, ['decision' => 'close', 'reason' => 'No photo of the damage on arrival; the seller was right.'])->assertSessionHas('success');

        $this->assertSame(['closed', 'close'], [$return->fresh()->status, $return->fresh()->admin_decision]);
        $this->assertSame(Order::COMPLETED, $order->fresh()->status);
        $this->assertEquals(0, (float) $order->fresh()->refunded_amount);
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'ALVY decided your dispute: no refund')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Dispute decided: no refund')->exists());
        $this->assertSame('cancelled', ReturnShipment::where('return_request_id', $return->id)->sole()->status);
    }

    public function test_only_admins_can_open_or_decide_a_dispute(): void
    {
        [, $return] = $this->disputed();

        foreach ([$this->buyer1, $this->seller1] as $user) {
            $this->assertNotSame(200, $this->actingAs($user)->get(route('admin.disputes.show', $return))->status());
            $this->actingAs($user)->post(route('admin.disputes.decide', $return), ['decision' => 'refund', 'reason' => 'Trying to refund myself.']);
        }
        $this->assertSame('disputed', $return->fresh()->status);
    }
}
