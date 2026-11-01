<?php

namespace Tests\Feature;

use App\Support\ApiJson;
use App\Support\ProtectedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** IDs, permits, proof of delivery, return and chat photos are private: only a signed link that expires shows them. */
class ProtectedFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_private_uploads_open_only_with_a_valid_signed_link(): void
    {
        Storage::disk('local')->put('seller-ids/abc.png', 'id-card');

        // The old public address does not have it any more.
        $this->assertFalse(Storage::disk('public')->exists('seller-ids/abc.png'));

        $url = ProtectedFile::url('seller-ids/abc.png');
        $this->assertStringContainsString('/files/seller-ids/abc.png?expires=', $url);
        $res = $this->get($url)->assertOk();
        $this->assertSame('id-card', $res->streamedContent() ?: file_get_contents($res->baseResponse->getFile()->getPathname()));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));

        $this->get('/files/seller-ids/abc.png')->assertForbidden();                                   // no signature
        $this->get(str_replace('abc.png', 'xyz.png', $url))->assertForbidden();                         // signature of another file
        $this->travel(ProtectedFile::HOURS)->hours();
        $this->travel(1)->minutes();
        $this->get($url)->assertForbidden();                                                            // expired
    }

    public function test_only_the_protected_folders_can_be_served_and_no_path_tricks(): void
    {
        Storage::disk('local')->put('payment-proofs/r.png', 'receipt');
        $this->assertSame(asset('storage/products/a.jpg'), ProtectedFile::url('products/a.jpg'));   // public uploads are unchanged

        $signed = fn (string $p) => url(\Illuminate\Support\Facades\URL::temporarySignedRoute('files.show', now()->addHour(), ['path' => $p], absolute: false));
        $this->get($signed('payment-proofs/r.png'))->assertNotFound();      // not one of the protected folders
        $this->get($signed('seller-ids/../../.env'))->assertNotFound();
        $this->assertFalse(ProtectedFile::isProtected('seller-ids/../x'));
    }

    public function test_files_not_moved_yet_still_open_and_the_api_gives_signed_links(): void
    {
        Storage::disk('public')->put('proof-of-delivery/old.jpg', 'pod');   // uploaded before the change
        $this->get(ProtectedFile::url('proof-of-delivery/old.jpg'))->assertOk();
        $this->assertStringContainsString('/files/proof-of-delivery/old.jpg?expires=', ApiJson::file('proof-of-delivery/old.jpg'));
        $this->assertSame(asset('storage/avatars/me.jpg'), ApiJson::file('avatars/me.jpg'));
    }

    public function test_new_sensitive_uploads_go_to_the_private_disk(): void
    {
        $path = UploadedFile::fake()->image('pod.jpg')->store('proof-of-delivery', ProtectedFile::DISK);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
}
