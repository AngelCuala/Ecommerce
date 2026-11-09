<?php

namespace Tests\Feature\Linehaul;

use App\Models\User;
use Database\Seeders\DemoAccountsAndProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** New delivery: the receiving sorting center is picked automatically (and can be changed); the list is nearest first. */
class TripDestinationSuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_nearest_sorting_center_is_picked(): void
    {
        $this->seed(DemoAccountsAndProductsSeeder::class);
        $html = $this->actingAs(User::where('email', 'logistics6@alvy.test')->first())->get(route('sc.trips'))->assertOk()
            ->assertSee('Picked automatically: <b>HUB-LUI</b>', false)->assertSee('the nearest sorting center to HUB-SCZ', false)
            ->getContent();

        // HUB-LUI is selected, marked suggested, and listed first (nearest); every option shows its distance.
        preg_match_all('/<option value="(\d+)"\s*(selected)?\s*>([^<]*)<\/option>/', explode('id="lt-dest"', $html)[1] ?? '', $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('HUB-LUI', $m[0][3]);
        $this->assertStringContainsString('(suggested)', $m[0][3]);
        $this->assertSame('selected', $m[0][2]);
        foreach ($m as $opt) {
            $this->assertMatchesRegularExpression('/ km/', $opt[3]);
        }
        $this->assertCount(1, array_filter($m, fn ($o) => $o[2] !== ''));
    }
}
