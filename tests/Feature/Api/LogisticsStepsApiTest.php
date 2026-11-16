<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's copies of the web's sidebar counts and the parcel page's step card. */
class LogisticsStepsApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedRiderTestData();
        $this->token = $this->logistics1->createToken('test')->plainTextToken;
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $app = str_contains($uri, '/api/v1');
        if ($app) {
            $this->app['auth']->forgetGuards();
        }
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        if ($app) {
            $this->defaultHeaders = [];
        }
        return $response;
    }

    public function test_badges_match_the_web_sidebar(): void
    {
        $this->parcel('pending_pickup');
        $this->parcel('picked_up', ['area_id' => null, 'received_at' => now()]);

        $b = $this->withToken($this->token)->getJson('/api/v1/logistics/badges')->assertOk();
        $this->assertSame(\App\Support\SortingCenterBadges::for($this->logistics1->id), $b->json());
        $this->assertSame([1, 1], [$b->json('pickups'), $b->json('sorting')]);
    }

    public function test_the_parcel_page_has_the_step_card(): void
    {
        $p = $this->parcel('picked_up', ['area_id' => null, 'received_at' => now()]);

        $s = $this->withToken($this->token)->getJson("/api/v1/logistics/tracking/{$p->id}")->assertOk()->json('parcel.next_steps');
        $this->assertCount(4, $s['steps']);
        $this->assertSame(['Received at hub', 'done'], [$s['steps'][0]['label'], $s['steps'][0]['state']]);
        $this->assertContains($s['next']['kind'], ['sort_area', 'sort_center']);

        // Not at this hub (out for delivery): no step card.
        \App\Models\Parcel::whereKey($p->id)->update(['status' => 'out_for_delivery']);
        $this->assertNull($this->withToken($this->token)->getJson("/api/v1/logistics/tracking/{$p->id}")->assertOk()->json('parcel.next_steps'));
    }
}
