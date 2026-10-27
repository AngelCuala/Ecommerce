<?php

namespace App\Http\Controllers;

use App\Services\Assistant\ShopAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/** ALVY Assistant chat (JSON) for guests and buyers: history, ask, confirm a prepared action, start over. */
class AssistantController extends Controller
{
    public function __construct(private ShopAssistant $assistant) {}

    public function history(): JsonResponse
    {
        abort_unless($this->allowed(), 404);

        return response()->json(['messages' => $this->assistant->history(auth()->user()), 'mode' => $this->assistant->available() ? 'ai' : 'quick']);
    }

    public function ask(Request $request): JsonResponse
    {
        abort_unless($this->allowed(), 404);
        $data = $request->validate(['message' => 'required|string|max:' . ShopAssistant::MAX_INPUT], [
            'message.required' => 'Type a question first.',
            'message.max'      => 'Please keep your question under ' . ShopAssistant::MAX_INPUT . ' characters.',
        ]);

        $who = auth()->id() ?? $request->ip();
        // A daily allowance per visitor, on top of the per-minute route limit.
        $key = 'assistant-day:' . $who;
        if (RateLimiter::tooManyAttempts($key, (int) config('services.assistant.daily_limit', 60))) {
            return response()->json(['message' => "You've reached today's limit for the assistant. Please try again tomorrow, or message the seller from your order page."], 429);
        }
        // One question at a time per visitor (double clicks, two tabs).
        $lock = Cache::lock('assistant-ask:' . $who, 90);
        if (! $lock->get()) {
            return response()->json(['message' => "I'm still answering your previous question. One moment please."], 429);
        }
        RateLimiter::hit($key, 86400);

        try {
            return response()->json($this->assistant->ask($data['message'], auth()->user()));
        } catch (\Throwable $e) {
            Log::warning('ALVY Assistant could not answer: ' . get_class($e));   // never logs the question or answer
            return response()->json(['message' => 'The assistant is busy right now. Please try again in a moment.'], 503);
        } finally {
            $lock->release();
        }
    }

    /** The buyer pressed Confirm on a prepared action (add to cart, cancel an order, contact support). */
    public function confirm(string $token): JsonResponse
    {
        abort_unless($this->allowed() && auth()->check(), 404);
        abort_unless(preg_match('/^[A-Za-z0-9]{32}$/', $token) === 1, 404);

        $result = $this->assistant->confirm($token, auth()->user());

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function reset(): JsonResponse
    {
        abort_unless($this->allowed(), 404);
        $this->assistant->reset();

        return response()->json(['ok' => true]);
    }

    /** Guests and buyers (sellers, riders and staff have their own portals). Without an AI key it answers in Quick help mode. */
    private function allowed(): bool
    {
        $user = auth()->user();
        return ! $user || $user->role === 'buyer';
    }
}
