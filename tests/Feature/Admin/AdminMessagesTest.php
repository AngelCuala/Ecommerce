<?php

namespace Tests\Feature\Admin;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin → Messages: list, open, send and receive, with every kind of account. (Tests run with one login for the whole site.) */
class AdminMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin', 'admin@alvy.test');
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    public function test_admin_can_message_every_kind_of_account_and_read_replies(): void
    {
        foreach (['seller', 'buyer', 'sorting_center', 'courier'] as $role) {
            $other = $this->user($role, $role . '@alvy.test');
            $key   = Message::threadKey($this->admin->id, $other->id);

            $this->actingAs($this->admin)->get(route('admin.messages.inbox'))->assertOk()->assertSee('chat/threads', false);
            $this->actingAs($this->admin)->getJson(route('admin.chat.threads'))->assertOk()
                ->assertJsonFragment(['key' => 'direct:' . $key, 'label' => $other->name]);

            // Admin sends from the inbox.
            $this->actingAs($this->admin)->postJson(url('/admin/chat/send/direct/' . $key), ['body' => "Hello $role"])
                ->assertOk()->assertJson(['ok' => true]);
            $this->assertDatabaseHas('messages', ['sender_id' => $this->admin->id, 'receiver_id' => $other->id, 'body' => "Hello $role", 'thread_key' => $key]);

            // The thread opens with the message in it.
            $this->actingAs($this->admin)->getJson(url('/admin/chat/thread/direct/' . $key))->assertOk()
                ->assertJsonPath('title', $other->name)->assertJsonFragment(['body' => "Hello $role", 'mine' => true]);
        }
    }

    public function test_replies_reach_the_admin_unread_then_read(): void
    {
        $seller = $this->user('seller', 'seller@alvy.test');
        $key = Message::threadKey($this->admin->id, $seller->id);

        $this->actingAs($seller)->postJson(url('/seller/chat/send/direct/' . $key), ['body' => 'Hi admin'])->assertOk();

        $this->actingAs($this->admin)->getJson(route('admin.chat.threads'))->assertOk()
            ->assertJsonPath('unread', 1)->assertJsonFragment(['last_body' => 'Hi admin', 'unread' => 1]);
        $this->actingAs($this->admin)->getJson(url('/admin/chat/thread/direct/' . $key))->assertOk()
            ->assertJsonFragment(['body' => 'Hi admin', 'mine' => false]);
        $this->actingAs($this->admin)->getJson(route('admin.chat.threads'))->assertJsonPath('unread', 0);
    }

    public function test_full_page_thread_and_compose_work_for_the_admin(): void
    {
        $sc  = $this->user('sorting_center', 'hub@alvy.test');
        $key = Message::threadKey($this->admin->id, $sc->id);

        $this->actingAs($this->admin)->post(route('admin.messages.new'), ['receiver_id' => $sc->id])
            ->assertRedirectContains('messages/direct/' . $key);
        $this->actingAs($this->admin)->post(route('admin.messages.direct.store', $key), ['body' => 'Check parcel 12'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('admin.messages.direct', $key))->assertOk()->assertSee('Check parcel 12');

        // The hub sees it in its own chat.
        $this->actingAs($sc)->get(route('sc.chat', ['contact_id' => $this->admin->id]))->assertOk()->assertSee('Check parcel 12');
    }
}
