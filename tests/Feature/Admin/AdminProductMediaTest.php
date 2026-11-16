<?php

namespace Tests\Feature\Admin;

use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Admins see every photo and the video of a seller's products (to check a report); the product picture fits the screen. */
class AdminProductMediaTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'),
            'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        $this->p02->update(['image' => 'products/front.jpg', 'video_path' => 'products/videos/clip.mp4']);
        ProductImage::create(['product_id' => $this->p02->id, 'path' => 'products/side.jpg', 'sort_order' => 1]);
    }

    public function test_the_seller_page_shows_every_photo_and_the_video(): void
    {
        $this->actingAs($this->admin)->get(route('admin.sellers.show', $this->seller1->id))->assertOk()
            ->assertSee('data-product-media="' . $this->p02->id . '"', false)->assertSee('📷 2')->assertSee('▶ Video')
            ->assertSee(asset('storage/products/side.jpg'), false)
            ->assertSee(asset('storage/products/videos/clip.mp4'), false)->assertSee('data-admin-video', false);
    }

    public function test_reports_and_complaints_show_them_too(): void
    {
        $this->actingAs($this->admin)->get(route('admin.compliance.show', $this->seller1->id))->assertOk()
            ->assertSee('data-product-media="' . $this->p02->id . '"', false)
            ->assertSee(asset('storage/products/videos/clip.mp4'), false);
    }

    public function test_a_draft_or_rejected_product_is_still_shown_to_the_admin(): void
    {
        $this->p02->update(['status' => 'draft']);
        $this->actingAs($this->admin)->get(route('admin.sellers.show', ['id' => $this->seller1->id, 'products' => 'all']))->assertOk()
            ->assertSee('data-product-media="' . $this->p02->id . '"', false);
    }

    public function test_the_product_picture_fits_the_screen(): void
    {
        $this->p02->update(['status' => 'active']);
        $this->get(route('products.show', $this->p02->slug))->assertOk()->assertSee('max-width:min(480px,70vh);', false);
    }
}
