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

    public function test_nobody_can_message_the_admin_and_its_messages_are_read_only(): void
    {
        $seller = $this->user('seller', 'seller@alvy.test');
        $key = Message::threadKey($this->admin->id, $seller->id);

        // The admin isn't a contact, and writing to the admin is refused (widget / app, full page, new chat).
        $this->assertNotContains($this->admin->name, array_column($this->actingAs($seller)->getJson(url('/seller/chat/threads'))->json('threads'), 'label'));
        $this->actingAs($seller)->postJson(url('/seller/chat/send/direct/' . $key), ['body' => 'Hi admin'])
            ->assertStatus(422)->assertJsonPath('message', Message::NO_ADMIN);
        $this->actingAs($seller)->post(route('seller.messages.direct.store', $key), ['body' => 'Hi admin'])->assertSessionHas('error', Message::NO_ADMIN);
        $this->actingAs($seller)->post(route('seller.messages.new'), ['receiver_id' => $this->admin->id])->assertSessionHas('error', Message::NO_ADMIN);
        $this->assertSame(0, Message::count());

        // The admin writes (an announcement): the seller reads it, with no reply box.
        Message::create(['thread_key' => $key, 'sender_id' => $this->admin->id, 'receiver_id' => $seller->id, 'body' => 'Maintenance tonight at 10 PM.']);
        $this->actingAs($seller)->getJson(url('/seller/chat/threads'))
            ->assertJsonFragment(['label' => $this->admin->name, 'position' => 'Announcements · read only']);
        $this->actingAs($seller)->getJson(url('/seller/chat/thread/direct/' . $key))->assertOk()
            ->assertJsonPath('read_only', true)->assertJsonPath('read_only_note', Message::READ_ONLY)
            ->assertJsonFragment(['body' => 'Maintenance tonight at 10 PM.', 'mine' => false]);
        $this->actingAs($seller)->get(route('seller.messages.direct', $key))->assertOk()
            ->assertSee('Maintenance tonight at 10 PM.')->assertSee('data-read-only', false)->assertDontSee('name="body"', false);
        $this->actingAs($seller)->postJson(url('/seller/chat/send/direct/' . $key), ['body' => 'Thanks!'])->assertStatus(422);
        $this->assertSame(1, Message::count());

        // Even a direct write to the database layer is refused.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Message::create(['thread_key' => $key, 'sender_id' => $seller->id, 'receiver_id' => $this->admin->id, 'body' => 'sneaky']);
    }

    public function test_an_announcement_lands_in_everyones_messages_read_only(): void
    {
        $seller = $this->user('seller', 'seller@alvy.test');
        $buyer = $this->user('buyer', 'buyer@alvy.test');
        $sc = $this->user('sorting_center', 'hub@alvy.test');

        $this->actingAs($this->admin)->post(route('admin.settings.announcements.store'), [
            'title' => 'Holiday schedule', 'body' => 'No pickups on Nov 30.', 'audience' => 'all', 'type' => 'info',
        ])->assertSessionHas('success');

        foreach ([$seller, $buyer, $sc] as $u) {
            $m = Message::where('receiver_id', $u->id)->sole();
            $this->assertSame([$this->admin->id, Message::threadKey($this->admin->id, $u->id)], [(int) $m->sender_id, $m->thread_key]);
            $this->assertStringContainsString('No pickups on Nov 30.', $m->body);
        }
        // The hub's own chat shows it, read only.
        $this->actingAs($sc)->get(route('sc.chat', ['contact_id' => $this->admin->id]))->assertOk()
            ->assertSee('No pickups on Nov 30.')->assertSee('data-read-only', false)->assertDontSee('id="chat-form"', false);
        $this->actingAs($sc)->post(route('sc.chat.send'), ['receiver_id' => $this->admin->id, 'body' => 'Noted'])->assertSessionHas('error', Message::NO_ADMIN);
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
