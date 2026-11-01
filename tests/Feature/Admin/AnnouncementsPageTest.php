<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin › Platform Settings › Announcements: summary, states, reach and posting. */
class AnnouncementsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'), 'role' => 'admin', 'approval_status' => 'approved']);
        foreach (['buyer' => 3, 'seller' => 2, 'courier' => 1] as $role => $n) {
            for ($i = 1; $i <= $n; $i++) {
                User::forceCreate(['name' => "$role $i", 'email' => "$role$i@alvy.test", 'password' => Hash::make('x'), 'role' => $role, 'approval_status' => 'approved']);
            }
        }
    }

    private function announcement(array $data): Announcement
    {
        return Announcement::create($data + ['admin_id' => $this->admin->id, 'title' => 'Notice', 'body' => 'Hello', 'audience' => 'all',
            'type' => 'info', 'is_active' => true, 'published_at' => now()]);
    }

    public function test_page_shows_states_reach_and_the_compose_preview(): void
    {
        $this->announcement(['title' => 'Live one']);
        $this->announcement(['title' => 'Old one', 'expires_at' => now()->subDay()]);
        $this->announcement(['title' => 'Paused one', 'is_active' => false, 'type' => 'maintenance']);

        $page = $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertOk()
            ->assertSee('data-announcement-stats', false)
            ->assertSee('data-state="active"', false)->assertSee('data-state="expired"', false)->assertSee('data-state="inactive"', false)
            ->assertSee('data-filter="expired"', false)
            ->assertSee('data-reach="6"', false)      // all: 3 buyers + 2 sellers + 1 rider (admins excluded)
            ->assertSee('data-reach="3"', false)      // buyers
            ->assertSee('data-reach="2"', false)      // sellers
            ->assertSee('data-preview-title', false)
            ->assertSee('data-confirm-title="Delete this announcement?"', false)
            ->assertSee('Maintenance');

        // An expired announcement can only be deleted, not re-activated.
        $html = $page->getContent();
        $expired = substr($html, strpos($html, 'data-state="expired"'), 3000);
        $this->assertStringNotContainsString('Activate', substr($expired, 0, strpos($expired, 'data-announcement ') ?: 3000));
    }

    public function test_posting_still_reaches_the_audience(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.announcements.store'), [
            'title' => 'Sale tonight', 'body' => 'Big discounts', 'audience' => 'buyers', 'type' => 'promo',
        ])->assertSessionHas('success', 'Announcement posted and sent to 3 users.');

        $this->assertSame(3, UserNotification::where('title', 'Sale tonight')->count());
        $this->actingAs($this->admin)->get(route('admin.settings.index'))->assertSee('Sale tonight')->assertSee('Promotion');
    }
}
