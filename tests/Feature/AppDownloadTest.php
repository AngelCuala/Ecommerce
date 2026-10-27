<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Get the app": the Android app downloads from the website (one server for the site and the app). */
class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $apk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apk = storage_path('framework/testing/ALVY-test.apk');
        @mkdir(dirname($this->apk), 0777, true);
        @unlink($this->apk);
        config(['app.apk_path' => $this->apk]);
    }

    protected function tearDown(): void
    {
        @unlink($this->apk);
        parent::tearDown();
    }

    public function test_page_says_when_the_app_is_not_available_yet(): void
    {
        $this->get(route('app.page'))->assertOk()->assertSee('The app is not available for download yet')->assertDontSee('Download for Android');
        $this->get(route('app.download'))->assertNotFound();
    }

    public function test_the_apk_downloads_as_an_android_app(): void
    {
        file_put_contents($this->apk, str_repeat('A', 2 * 1048576));

        $this->get(route('app.page'))->assertOk()->assertSee('Download for Android')->assertSee('2.0 MB')->assertSee(route('app.download'), false);

        $res = $this->get(route('app.download'))->assertOk();
        $this->assertSame('application/vnd.android.package-archive', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=ALVY.apk', $res->headers->get('Content-Disposition'));
    }

    public function test_the_footer_links_to_it(): void
    {
        $this->get(route('policies.show', 'about_us'))->assertOk()->assertSee(route('app.page'), false)->assertSee('Get the app');
    }
}
