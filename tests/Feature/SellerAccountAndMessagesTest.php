<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\SellerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SellerAccountAndMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private SellerApplication $application;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::create([
            'name' => 'Maria Santos', 'email' => 'maria@shop.test', 'password' => Hash::make('Secret@123'),
            'role' => 'seller', 'approval_status' => 'approved', 'email_verified_at' => now(),
            'contact_no' => '09189876543',
        ]);
        $this->application = SellerApplication::create([
            'user_id' => $this->seller->id, 'full_name' => 'Maria Santos', 'shop_name' => 'Maria Crafts',
            'phone' => '09189876543', 'address' => 'Quezon City', 'government_id_path' => 'seller-ids/id.jpg',
            'bir_2303_path' => 'seller-bir/bir.pdf', 'seller_type' => 'Sole Proprietorship', 'tin' => '123-456-789-000',
            'status' => 'approved',
        ]);
    }

    private function update(array $data = [])
    {
        return $this->actingAs($this->seller)->from(route('seller.account'))
            ->patch(route('seller.account.update'), array_merge(['name' => 'Maria Santos', 'email' => 'maria@shop.test'], $data));
    }

    public function test_account_page_shows_contact_number_shop_details_and_all_documents(): void
    {
        $this->actingAs($this->seller)->get(route('seller.account'))->assertOk()
            ->assertSee('value="09189876543"', false)       // contact_no shown when phone is empty
            ->assertSee('Sole Proprietorship')
            ->assertSee('123-456-789-000')
            ->assertSee('BIR Form 2303')
            ->assertSee('Government-issued ID')
            ->assertDontSee('>Logout<', false);              // the broken nested logout form is gone
    }

    public function test_phone_is_normalised_validated_and_saved_to_both_fields(): void
    {
        $this->update(['phone' => '+63 917 111 2222'])->assertSessionHasNoErrors();
        $this->seller->refresh();
        $this->assertSame('09171112222', $this->seller->phone);
        $this->assertSame('09171112222', $this->seller->contact_no);

        $this->update(['phone' => '12345'])->assertSessionHasErrors('phone');

        User::create(['name' => 'Other', 'email' => 'o@x.test', 'password' => 'x', 'role' => 'buyer', 'contact_no' => '09170000000']);
        $this->update(['phone' => '09170000000'])->assertSessionHasErrors(['phone' => 'This mobile number is already in use.']);
    }

    public function test_shop_name_follows_the_sign_up_rules(): void
    {
        $this->update(['shop_name' => 'AB'])->assertSessionHasErrors('shop_name');
        $this->update(['shop_name' => str_repeat('a', 31)])->assertSessionHasErrors('shop_name');

        $other = User::create(['name' => 'O', 'email' => 'o2@x.test', 'password' => 'x', 'role' => 'seller']);
        SellerApplication::create(['user_id' => $other->id, 'full_name' => 'O', 'shop_name' => 'Taken Shop',
            'phone' => '0', 'address' => 'x', 'government_id_path' => 'x', 'status' => 'approved']);
        $this->update(['shop_name' => 'taken shop'])->assertSessionHasErrors(['shop_name' => 'This shop name is already taken. Please choose another.']);

        // Keeping your own name is fine.
        $this->update(['shop_name' => 'Maria Crafts'])->assertSessionHasNoErrors();
        $this->update(['shop_name' => 'Maria Handicrafts'])->assertSessionHasNoErrors();
        $this->assertSame('Maria Handicrafts', $this->application->fresh()->shop_name);
    }

    public function test_shop_name_change_counter_resets_in_a_new_month_even_across_years(): void
    {
        $this->application->update(['shop_name_changes_this_month' => 3, 'shop_name_last_changed_at' => now()->subYear()]);
        $this->assertSame(3, $this->application->fresh()->shopNameChangesRemaining());
    }

    public function test_conversation_json_includes_day_and_time(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.test', 'password' => 'x', 'role' => 'admin', 'approval_status' => 'approved']);
        $key = Message::threadKey($this->seller->id, $admin->id);
        Message::create(['thread_key' => $key, 'sender_id' => $admin->id, 'receiver_id' => $this->seller->id, 'body' => 'Hello']);

        $this->actingAs($this->seller)->get(route('messages.inbox'))->assertOk()->assertSee('1 unread message');

        $this->actingAs($this->seller)->getJson(route('chat.thread', ['type' => 'direct', 'id' => $key]))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Hello')
            ->assertJsonPath('messages.0.day', 'Today')
            ->assertJsonStructure(['title', 'messages' => [['body', 'mine', 'name', 'at', 'day', 'time']]]);

        // Opening the conversation marks it read.
        $this->actingAs($this->seller)->get(route('messages.inbox'))->assertOk()->assertSee("You're all caught up.", false);
    }
}
