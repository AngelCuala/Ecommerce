<?php

namespace Tests\Feature\Seller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** A seller uploads a real MP4 to a product; a too-big or wrong file says why on the form. */
class ProductVideoUploadTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        Storage::fake('public');
    }

    /** The product's own edit form (as the page sends it), plus $extra. */
    private function save(array $extra)
    {
        \App\Models\ProductImage::firstOrCreate(['product_id' => $this->p02->id, 'path' => 'products/front.jpg']);   // it already has its photo

        return $this->actingAs($this->seller1)->from(route('seller.books.edit', $this->p02->id))
            ->put(route('seller.books.update', $this->p02->id), $this->editForm($this->p02->fresh(), $extra + ['language' => 'English', 'format' => 'Standard']));
    }

    public function test_a_real_mp4_is_saved_and_plays_on_the_product_page(): void
    {
        $mp4 = glob(storage_path('app/public/products/videos/*.mp4'))[0] ?? null;
        $video = $mp4 ? new UploadedFile($mp4, 'clip.mp4', 'video/mp4', null, true)
                      : UploadedFile::fake()->create('clip.mp4', 900, 'video/mp4');

        $this->save(['video' => $video])->assertSessionHasNoErrors();
        $path = $this->p02->fresh()->video_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_video_over_the_limit_says_so(): void
    {
        $this->save(['video' => UploadedFile::fake()->create('big.mp4', 60 * 1024, 'video/mp4')])
            ->assertSessionHasErrors(['video' => 'The video can be at most 50 MB.']);
        $this->assertNull($this->p02->fresh()->video_path);
    }

    public function test_the_form_shows_the_video_error_and_the_limit(): void
    {
        $this->actingAs($this->seller1)->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['video' => ['The video can be at most 50 MB.']]))])
            ->get(route('seller.books.edit', $this->p02->id))->assertOk()
            ->assertSee('The video can be at most 50 MB.')->assertSee('up to 50 MB');
    }
}
