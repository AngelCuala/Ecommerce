<?php

namespace Tests\Feature\Seller;

use App\Models\Message;
use App\Models\UserNotification;
use App\Support\SellerBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller Centre sidebar numbers: new notifications per section, cleared when the section is opened. */
class SellerSidebarBadgesTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    private function badge(string $html, string $key): ?string
    {
        return preg_match('/data-badge="' . $key . '"[^>]*>([^<]+)</', $html, $m) ? trim($m[1]) : null;
    }

    public function test_links_map_to_sidebar_sections(): void
    {
        $this->assertSame('orders', SellerBadges::sectionOf('http://127.0.0.1:8000/seller/orders/59'));
        $this->assertSame('orders', SellerBadges::sectionOf('/seller/orders'));
        $this->assertSame('products', SellerBadges::sectionOf(route('seller.books.edit', $this->p02)));
        $this->assertSame('dashboard', SellerBadges::sectionOf(route('seller.dashboard')));
        $this->assertNull(SellerBadges::sectionOf('/profile/orders'));
        $this->assertNull(SellerBadges::sectionOf(null));
    }

    public function test_new_orders_and_low_stock_show_numbers_until_the_section_is_opened(): void
    {
        // Two new orders for Seller 1 (the checkout tells the seller), and P-03 drops to sold out (an Inventory alert).
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p03)]);

        $html = $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertOk()->getContent();
        $this->assertSame('2', $this->badge($html, 'orders'));
        $this->assertSame('1', $this->badge($html, 'products'));
        $this->assertNull($this->badge($html, 'reviews'));   // Reviews never borrows the Messages count

        // Opening Order Management clears its number; Inventory keeps its own.
        $html = $this->actingAs($this->seller1)->get(route('seller.orders.index'))->assertOk()->getContent();
        $this->assertNull($this->badge($html, 'orders'));
        $this->assertSame('1', $this->badge($html, 'products'));
        $this->assertSame(0, UserNotification::where('user_id', $this->seller1->id)->whereNull('read_at')->where('link', 'like', '%/seller/orders%')->count());

        $html = $this->actingAs($this->seller1)->get(route('seller.books.index'))->getContent();
        $this->assertNull($this->badge($html, 'products'));
    }

    public function test_numbers_are_per_seller_and_show_9_plus(): void
    {
        foreach (range(1, 12) as $i) {
            UserNotification::create(['user_id' => $this->seller1->id, 'title' => "Update {$i}", 'body' => 'x', 'type' => 'info', 'link' => route('seller.orders.index')]);
        }
        $this->assertSame('9+', $this->badge($this->actingAs($this->seller1)->get(route('seller.reports'))->getContent(), 'orders'));
        $this->assertNull($this->badge($this->actingAs($this->seller2)->get(route('seller.reports'))->getContent(), 'orders'));
    }

    public function test_messages_badge_counts_unread_chats_only_on_messages(): void
    {
        Message::forceCreate(['sender_id' => $this->buyer1->id, 'receiver_id' => $this->seller1->id, 'body' => 'Hi, is this available?', 'is_read' => false]);
        $html = $this->actingAs($this->seller1)->get(route('seller.reports'))->getContent();
        $this->assertSame('1', $this->badge($html, 'messages'));
        $this->assertNull($this->badge($html, 'reviews'));
    }
}
