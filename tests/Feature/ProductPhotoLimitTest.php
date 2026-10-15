<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A product can have 1 to Product::MAX_IMAGES (9) photos in total (assumption A16). */
class ProductPhotoLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seller = User::create([
            'name' => 'Seller', 'email' => 'seller@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => 'seller', 'approval_status' => 'approved', 'email_verified_at' => now(),
        ]);
        $cat = Category::create(['name' => 'Pet Supplies']);
        $this->product = Product::create([
            'category_id' => $cat->id, 'seller_id' => $this->seller->id, 'title' => 'Dog Food',
            'price' => 100, 'stock' => 5, 'status' => 'active',
        ]);
    }

    private function photos(int $n): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->create("p{$i}.jpg", 50, 'image/jpeg'), range(1, $n));
    }

    private function withSavedPhotos(int $n): void
    {
        foreach (range(1, $n) as $i) {
            ProductImage::create(['product_id' => $this->product->id, 'path' => "products/gallery/{$i}.jpg", 'sort_order' => $i - 1]);
        }
    }

    private function save(array $extra = [])
    {
        return $this->actingAs($this->seller)
            ->from(route('seller.books.edit', $this->product))
            ->put(route('seller.books.update', $this->product), array_merge([
                'title' => 'Dog Food', 'category_id' => $this->product->category_id,
                'price' => 100, 'stock' => 5, 'status' => 'active', 'action' => 'publish', 'weight_kg' => 1, 'description' => 'A sturdy handmade item, made to last for years.',
                // The SQLite test schema still has these NOT NULL (MySQL relaxed them in a later migration).
                'language' => 'English', 'format' => 'Standard',
            ], $extra));
    }

    public function test_up_to_9_photos_can_be_uploaded_at_once(): void
    {
        $this->save(['images' => $this->photos(9)])->assertSessionHasNoErrors();
        $this->assertSame(9, $this->product->images()->count());
    }

    public function test_10_photos_at_once_are_rejected(): void
    {
        $this->save(['images' => $this->photos(10)])->assertSessionHasErrors('images');
        $this->assertSame(0, $this->product->images()->count());
    }

    public function test_saved_photos_count_toward_the_limit(): void
    {
        $this->withSavedPhotos(8);

        $this->save(['images' => $this->photos(2)])
            ->assertSessionHasErrors(['images' => 'A product can have up to 9 photos — this would make 10. Remove some first.']);
        $this->assertSame(8, $this->product->images()->count());

        $this->save(['images' => $this->photos(1)])->assertSessionHasNoErrors();
        $this->assertSame(9, $this->product->images()->count());
    }

    public function test_removing_photos_makes_room_for_new_ones(): void
    {
        $this->withSavedPhotos(9);
        $remove = $this->product->images()->take(2)->pluck('id')->all();

        $this->save(['images' => $this->photos(2), 'removed_images' => $remove])->assertSessionHasNoErrors();
        $this->assertSame(9, $this->product->images()->count());
    }

    public function test_saving_without_new_photos_is_fine_when_full(): void
    {
        $this->withSavedPhotos(9);
        $this->save()->assertSessionHasNoErrors();
        $this->assertSame(9, $this->product->images()->count());
    }

    public function test_edit_page_shows_the_photo_count_and_limit(): void
    {
        $this->withSavedPhotos(3);
        $this->actingAs($this->seller)->get(route('seller.books.edit', $this->product))
            ->assertOk()
            ->assertSee('<span id="photo-count">3</span> of 9', false);
    }
}
