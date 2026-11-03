<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Profile photo saved on choosing it (every role), bulk product actions that explain themselves, and the earnings chart. */
class WebFixesNov3Test extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        Storage::fake('public');
    }

    // ── Profile photo ──

    public function test_any_signed_in_user_saves_a_profile_photo_right_away(): void
    {
        foreach ([$this->buyer1, $this->seller1] as $user) {
            $res = $this->actingAs($user)->postJson(route('account.photo'), ['avatar' => UploadedFile::fake()->image('me.jpg', 400, 400)])
                ->assertOk()->assertJsonPath('message', 'Profile photo updated.');
            $path = $user->fresh()->profile_photo_path;
            $this->assertStringStartsWith('avatars/', $path);
            Storage::disk('public')->assertExists($path);
            $this->assertSame(asset('storage/' . $path), $res->json('url'));
        }
    }

    public function test_a_new_photo_replaces_the_old_file_and_webp_works(): void
    {
        $this->actingAs($this->buyer1)->postJson(route('account.photo'), ['avatar' => UploadedFile::fake()->image('a.png')])->assertOk();
        $old = $this->buyer1->fresh()->profile_photo_path;

        $this->actingAs($this->buyer1)->postJson(route('account.photo'), ['avatar' => UploadedFile::fake()->image('b.webp')])->assertOk();
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($this->buyer1->fresh()->profile_photo_path);
    }

    public function test_a_wrong_file_is_refused_with_a_message(): void
    {
        $this->actingAs($this->buyer1)->postJson(route('account.photo'), ['avatar' => UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('avatar');
        $this->actingAs($this->buyer1)->postJson(route('account.photo'), ['avatar' => UploadedFile::fake()->image('big.jpg')->size(6000)])
            ->assertStatus(422)->assertJsonPath('errors.avatar.0', 'The profile photo can be at most 5 MB.');
    }

    public function test_signed_out_visitors_cannot_upload(): void
    {
        $this->postJson(route('account.photo'), ['avatar' => UploadedFile::fake()->image('me.jpg')])->assertUnauthorized();
    }

    public function test_the_account_pages_upload_the_photo_on_choosing_it(): void
    {
        $this->actingAs($this->buyer1)->get(route('profile.show'))->assertOk()
            ->assertSee('data-photo-upload', false)->assertSee(route('account.photo'), false)->assertSee('js/photo-upload.js', false);
        $this->actingAs($this->seller1)->get(route('seller.account'))->assertOk()
            ->assertSee('data-photo-upload', false)->assertSee('js/photo-upload.js', false);
    }

    public function test_the_photo_shows_in_the_header_of_other_pages(): void
    {
        $this->actingAs($this->buyer1)->get(route('home'))->assertOk()->assertSee('data-nav-avatar', false);   // no photo yet: the letter

        $this->actingAs($this->buyer1)->postJson(route('account.photo'), ['avatar' => UploadedFile::fake()->image('me.jpg')])->assertOk();
        $url = $this->buyer1->fresh()->profile_photo_url;
        $this->assertNotNull($url);

        $this->actingAs($this->buyer1->fresh())->get(route('home'))->assertOk()->assertSee('src="' . $url . '"', false);
        $this->actingAs($this->buyer1->fresh())->get(route('cart.index'))->assertOk()->assertSee('src="' . $url . '"', false);
    }

    // ── Bulk product actions ──

    public function test_bulk_action_without_an_action_or_products_says_what_is_missing(): void
    {
        $this->actingAs($this->seller1)->from(route('seller.books.index'))
            ->post(route('seller.books.bulk'), ['ids' => [$this->p02->id]])
            ->assertSessionHas('error', 'Choose an action first (Deactivate, Reactivate or Delete).');
        $this->actingAs($this->seller1)->from(route('seller.books.index'))
            ->post(route('seller.books.bulk'), ['action' => 'deactivate'])
            ->assertSessionHas('error', 'Tick at least one product.');

        $this->actingAs($this->seller1)->post(route('seller.books.bulk'), ['action' => 'deactivate', 'ids' => [$this->p02->id]])
            ->assertSessionHas('success');
        $this->assertTrue((bool) $this->p02->fresh()->is_archived);
    }

    public function test_the_seller_layout_shows_warnings(): void
    {
        $this->actingAs($this->seller1)->withSession(['warning' => 'Skipped "Rattan Basket": it has orders that are not finished yet.'])
            ->get(route('seller.books.index'))->assertOk()
            ->assertSee('Skipped &quot;Rattan Basket&quot;', false);
    }

    // ── Earnings chart ──

    public function test_the_chart_has_every_day_of_the_range_with_zero_days(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $order->forceFill(['created_at' => now()->subDays(2)])->save();

        $from = now()->subDays(4)->toDateString();
        $to = now()->toDateString();
        $res = $this->actingAs($this->seller1)->get(route('seller.reports', ['from' => $from, 'to' => $to]))->assertOk();
        $byDate = $res->viewData('byDate');

        $this->assertSame(5, $byDate->count());   // all 5 days, not just the day with a sale
        $this->assertSame([now()->subDays(4)->format('M d'), now()->format('M d')], [$byDate->keys()->first(), $byDate->keys()->last()]);
        $this->assertSame(0.0, $byDate[now()->subDays(4)->format('M d')]);
        $this->assertGreaterThan(0, $byDate[now()->subDays(2)->format('M d')]);
        $this->assertEqualsWithDelta($res->viewData('totalEarnings'), $byDate->sum(), 0.01);
        $this->assertSame('day', $res->viewData('byDateUnit'));
        $res->assertSee('data-earnings-chart', false);
    }

    public function test_long_ranges_are_shown_per_month_and_no_sales_stays_empty(): void
    {
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $res = $this->actingAs($this->seller1)->get(route('seller.reports', ['from' => now()->subMonths(5)->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))->assertOk();
        $this->assertSame('month', $res->viewData('byDateUnit'));
        $this->assertSame(6, $res->viewData('byDate')->count());
        $res->assertSee('Monthly net earnings');

        $empty = $this->actingAs($this->seller1)->get(route('seller.reports', ['from' => '2020-01-01', 'to' => '2020-01-31']))->assertOk();
        $this->assertTrue($empty->viewData('byDate')->isEmpty());
        $empty->assertSee('No sales in this period.');
    }
}
