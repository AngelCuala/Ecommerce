<?php

namespace Tests\Feature;

use App\Models\HomeBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The admin's event slides on the home page's big banner (website and app). */
class HomeBannersTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'), 'role' => 'admin', 'email_verified_at' => now()]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_the_admin_adds_an_event_banner_and_it_leads_the_home_page_and_the_app(): void
    {
        $this->actingAs($this->admin())->post(route('admin.banners.store'), [
            'image' => UploadedFile::fake()->image('sale.jpg', 1600, 640),
            'eyebrow' => '11.11 Sale', 'title' => 'Big savings this', 'highlight' => '11.11', 'subtitle' => 'Up to 50% off.',
            'button_label' => 'Shop the sale', 'category_slug' => '', 'focus' => 'center', 'is_active' => '1',
        ])->assertRedirect(route('admin.banners.index'));

        $banner = HomeBanner::sole();
        Storage::disk('public')->assertExists($banner->image);

        auth()->logout();
        $this->get('/')->assertOk()->assertSeeInOrder(['Big savings this', 'Shop the sale', 'Bringing more to your']);

        $banners = $this->getJson('/api/v1/home')->assertOk()->json('banners');
        $this->assertSame('Big savings this', $banners[0]['title']);
        $this->assertSame('11.11', $banners[0]['highlight']);
        $this->assertCount(5, $banners);   // the event slide + ALVY's 4
    }

    public function test_a_scheduled_or_ended_or_switched_off_banner_does_not_show(): void
    {
        foreach ([['starts_at' => now()->addDay()], ['ends_at' => now()->subHour()], ['is_active' => false]] as $i => $when) {
            HomeBanner::create(['image' => 'banners/x.jpg', 'title' => "Hidden $i", 'focus' => 'center'] + $when);
        }

        $this->get('/')->assertOk()->assertDontSee('Hidden 0')->assertDontSee('Hidden 1')->assertDontSee('Hidden 2');
        $this->assertCount(4, $this->getJson('/api/v1/home')->json('banners'));
    }

    public function test_show_only_my_banners_hides_alvys_slides_while_one_is_live(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.banners.settings'), ['hide_defaults' => '1'])->assertRedirect();

        // Nothing of the admin's is live: ALVY's slides still show, so the banner is never empty.
        $this->assertCount(4, $this->getJson('/api/v1/home')->json('banners'));

        HomeBanner::create(['image' => 'banners/x.jpg', 'title' => 'Christmas sale', 'focus' => 'center']);
        $banners = $this->getJson('/api/v1/home')->json('banners');
        $this->assertCount(1, $banners);
        $this->assertSame('Christmas sale', $banners[0]['title']);
    }

    public function test_only_the_admin_manages_banners_and_the_photo_must_be_wide_enough(): void
    {
        $buyer = User::forceCreate(['name' => 'Ana', 'email' => 'ana@alvy.test', 'password' => Hash::make('x'), 'role' => 'customer', 'email_verified_at' => now()]);
        $res = $this->actingAs($buyer)->get(route('admin.banners.index'));
        $this->assertTrue(in_array($res->status(), [302, 403], true));

        $this->actingAs($this->admin())->post(route('admin.banners.store'), [
            'image' => UploadedFile::fake()->image('tiny.jpg', 200, 100), 'title' => 'Too small', 'focus' => 'center',
        ])->assertSessionHasErrors('image');
        $this->assertSame(0, HomeBanner::count());
    }
}
