<?php

namespace Tests\Feature\Buyer;

use App\Services\Assistant\AssistantModel;
use App\Services\Assistant\ClaudeAssistantModel;
use App\Services\Assistant\GeminiAssistantModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The ALVY Assistant on Gemini: the request it sends, the tool loop, refusals and failures (Gemini is faked). */
class GeminiAssistantModelTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        config(['services.assistant.provider' => 'gemini', 'services.assistant.gemini_key' => 'test-key',
            'services.assistant.model' => 'gemini-3.8-flash', 'services.assistant.enabled' => true]);
    }

    private function ask(string $message)
    {
        return $this->actingAs($this->buyer1)->postJson(route('assistant.ask'), ['message' => $message]);
    }

    private static function reply(array $parts, string $finish = 'STOP'): array
    {
        return ['candidates' => [['content' => ['role' => 'model', 'parts' => $parts], 'finishReason' => $finish]]];
    }

    public function test_gemini_is_the_default_and_needs_its_own_key(): void
    {
        $this->assertInstanceOf(GeminiAssistantModel::class, app(AssistantModel::class));
        $this->assertTrue(app(AssistantModel::class)->available());

        config(['services.assistant.gemini_key' => null]);
        $this->assertFalse(app(AssistantModel::class)->available());   // no key: Quick help only

        config(['services.assistant.provider' => 'claude']);
        $this->assertInstanceOf(ClaudeAssistantModel::class, app(AssistantModel::class));
    }

    public function test_a_tool_call_runs_and_its_result_goes_back_to_gemini(): void
    {
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(self::reply([['functionCall' => ['name' => 'search_products', 'args' => ['query' => 'tote bag']], 'thoughtSignature' => 'sig-123']]))
            ->push(self::reply([['text' => 'I found the Abaca Woven Tote Bag for you.']]));

        $res = $this->ask('Do you have tote bags?')->assertOk();
        $this->assertSame('I found the Abaca Woven Tote Bag for you.', $res->json('reply'));
        $this->assertSame('ai', $res->json('mode'));
        $this->assertNotEmpty($res->json('cards'));   // the product search really ran

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertCount(2, $sent);

        /** @var Request $first */
        $first = $sent[0];
        $this->assertStringEndsWith('/v1beta/models/gemini-3.8-flash:generateContent', $first->url());
        $this->assertSame('test-key', $first->header('x-goog-api-key')[0]);
        $this->assertStringContainsString('ALVY', $first['systemInstruction']['parts'][0]['text']);
        $this->assertSame('user', $first['contents'][0]['role']);
        $this->assertSame('Do you have tote bags?', $first['contents'][0]['parts'][0]['text']);
        $names = collect($first['tools'][0]['functionDeclarations'])->pluck('name');
        $this->assertTrue($names->contains('search_products'));
        $this->assertArrayHasKey('parametersJsonSchema', $first['tools'][0]['functionDeclarations'][0]);
        $this->assertSame('low', $first['generationConfig']['thinkingConfig']['thinkingLevel']);

        // Second request: the model's call is sent back as-is (with its thought signature), then the tool's result.
        $second = $sent[1];
        $model = $second['contents'][1];
        $this->assertSame('model', $model['role']);
        $this->assertSame('sig-123', $model['parts'][0]['thoughtSignature']);
        $this->assertSame('search_products', $model['parts'][0]['functionCall']['name']);
        $result = $second['contents'][2];
        $this->assertSame('user', $result['role']);
        $this->assertSame('search_products', $result['parts'][0]['functionResponse']['name']);
        $this->assertArrayHasKey('products', $result['parts'][0]['functionResponse']['response']);
    }

    public function test_a_busy_model_is_retried_then_answers(): void
    {
        config(['services.assistant.retry_waits_ms' => [0, 0]]);
        Http::fakeSequence('generativelanguage.googleapis.com/*')
            ->push(['error' => ['code' => 503, 'message' => 'This model is currently experiencing high demand.']], 503)
            ->push(self::reply([['text' => 'Hello! How can I help?']]));

        $res = $this->ask('hi, any vouchers?')->assertOk();
        $this->assertSame('ai', $res->json('mode'));
        $this->assertSame('Hello! How can I help?', $res->json('reply'));
        $this->assertCount(2, Http::recorded());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'gemini-3.8-flash'));
    }

    public function test_when_the_main_model_stays_busy_the_backup_model_answers_and_finishes_the_reply(): void
    {
        config(['services.assistant.retry_waits_ms' => [0, 0], 'services.assistant.fallback_models' => 'gemini-3.6-flash']);
        Http::fake(function (Request $r) {
            static $backupCalls = 0;
            if (str_contains($r->url(), 'gemini-3.8-flash')) {
                return Http::response(['error' => ['code' => 503, 'message' => 'high demand']], 503);
            }
            return ++$backupCalls === 1
                ? Http::response(self::reply([['functionCall' => ['name' => 'search_products', 'args' => ['query' => 'tote']]]]))
                : Http::response(self::reply([['text' => 'Here is a tote bag.']]));
        });

        $res = $this->ask('tote bags?')->assertOk();
        $this->assertSame('ai', $res->json('mode'));
        $this->assertSame('Here is a tote bag.', $res->json('reply'));

        $urls = Http::recorded()->map(fn ($pair) => $pair[0]->url())->values();
        $this->assertSame(3, $urls->filter(fn ($u) => str_contains($u, 'gemini-3.8-flash'))->count());   // 1 try + 2 retries
        $this->assertSame(2, $urls->filter(fn ($u) => str_contains($u, 'gemini-3.6-flash'))->count());   // the reply stays on the back-up
        $this->assertStringContainsString('gemini-3.6-flash', $urls->last());
    }

    public function test_a_main_model_that_never_answers_hands_over_to_the_backup_without_retrying(): void
    {
        config(['services.assistant.retry_waits_ms' => [0, 0], 'services.assistant.fallback_models' => 'gemini-3.6-flash,gemini-3.5-flash']);
        $mainTries = 0;
        Http::fake(function (Request $r) use (&$mainTries) {
            if (str_contains($r->url(), 'gemini-3.8-flash')) {
                $mainTries++;
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 15000 milliseconds with 0 bytes received');
            }
            return Http::response(self::reply([['text' => 'Hi! I can help you shop.']]));
        });

        $res = $this->ask('hello')->assertOk();
        $this->assertSame('ai', $res->json('mode'));
        $this->assertSame('Hi! I can help you shop.', $res->json('reply'));
        $urls = Http::recorded()->map(fn ($pair) => $pair[0]->url())->values();
        $this->assertSame(1, $mainTries);   // no second 15 s wait
        $this->assertStringContainsString('gemini-3.6-flash', $urls->last());
    }

    public function test_a_model_at_its_limit_is_not_retried_and_is_skipped_for_a_minute(): void
    {
        config(['services.assistant.retry_waits_ms' => [0, 0], 'services.assistant.fallback_models' => 'gemini-3.6-flash']);
        Http::fake(fn (Request $r) => str_contains($r->url(), 'gemini-3.8-flash')
            ? Http::response(['error' => ['code' => 429, 'message' => 'You exceeded your current quota']], 429)
            : Http::response(self::reply([['text' => 'Hi! I can help you shop.']])));
        $mainTries = fn () => Http::recorded()->filter(fn ($pair) => str_contains($pair[0]->url(), 'gemini-3.8-flash'))->count();

        $this->ask('hello')->assertOk()->assertJsonPath('mode', 'ai');
        $this->assertSame(1, $mainTries());   // no retries: they would only use up more of the limit

        $this->ask('any vouchers?')->assertOk()->assertJsonPath('mode', 'ai');
        $this->assertSame(1, $mainTries());   // skipped while cooling down

        $this->travel(61)->seconds();
        $this->ask('thanks')->assertOk();
        $this->assertSame(2, $mainTries());   // tried again after a minute
    }

    public function test_a_model_that_hits_its_limit_mid_reply_hands_the_reply_to_a_backup(): void
    {
        config(['services.assistant.retry_waits_ms' => [0, 0], 'services.assistant.fallback_models' => 'gemini-3.5-flash-lite']);
        Http::fake(function (Request $r) {
            static $mainCalls = 0;
            if (str_contains($r->url(), 'gemini-3.8-flash')) {
                return ++$mainCalls === 1
                    ? Http::response(self::reply([['functionCall' => ['name' => 'search_products', 'args' => ['query' => 'tote']], 'thoughtSignature' => 'sig-main']]))
                    : Http::response(['error' => ['code' => 429, 'message' => 'You exceeded your current quota']], 429);
            }
            return Http::response(self::reply([['text' => 'Here is a tote bag.']]));
        });

        $res = $this->ask('tote bags?')->assertOk();
        $this->assertSame('ai', $res->json('mode'));   // not Quick help
        $this->assertSame('Here is a tote bag.', $res->json('reply'));

        // The back-up got the main model's call with Google's stand-in signature (the real one only works on its own model).
        $backup = Http::recorded()->map(fn ($pair) => $pair[0])->first(fn (Request $r) => str_contains($r->url(), 'gemini-3.5-flash-lite'));
        $call = $backup['contents'][1]['parts'][0];
        $this->assertSame('search_products', $call['functionCall']['name']);
        $this->assertSame('skip_thought_signature_validator', $call['thoughtSignature']);
        $this->assertArrayNotHasKey('_model', $call);
        $this->assertStringNotContainsString('_model', json_encode($backup->data()));
    }

    public function test_a_bad_key_does_not_try_other_models(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 400, 'message' => 'API key not valid.']], 400)]);

        $this->assertSame('quick', $this->ask('hello there, where is my order?')->assertOk()->json('mode'));
        $this->assertCount(1, Http::recorded());
    }

    public function test_a_blocked_reply_is_a_polite_refusal(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY'], 'candidates' => []])]);

        $res = $this->ask('something unsafe')->assertOk();
        $this->assertStringContainsString("Sorry, I can't help with that.", $res->json('reply'));
    }

    public function test_a_gemini_error_falls_back_to_quick_help(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 404, 'message' => 'models/gemini-3.8-flash is not found']], 404)]);

        $res = $this->ask('How do I track my order?')->assertOk();
        $this->assertSame('quick', $res->json('mode'));
        $this->assertNotEmpty($res->json('reply'));
    }

    public function test_check_command_reports_whether_the_model_exists(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['models' => [
            ['name' => 'models/gemini-3.8-flash', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/gemini-2.5-flash', 'supportedGenerationMethods' => ['generateContent']],
        ]])]);
        $this->artisan('assistant:check')->expectsOutputToContain('"gemini-3.8-flash" is available')->assertSuccessful();

        config(['services.assistant.model' => 'gemini-9-flash']);
        $this->artisan('assistant:check')->expectsOutputToContain('is not available')->expectsOutputToContain('gemini-2.5-flash')->assertFailed();
    }
}
