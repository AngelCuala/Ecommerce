<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs the web's own controller actions for the app, so every rule, message and side effect is the
 * website's. A page gives back the data it would show; an action gives back its flash message, its
 * errors (422) or where the website would go next.
 */
trait CallsWeb
{
    /** Call a web controller action; route parameters go in $params by name. */
    protected function web(string $controller, string $method, array $params = []): mixed
    {
        // Web pages expect $errors (shared by the web middleware); some render right away (response()->view()).
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        try {
            return app()->call([app($controller), $method], $params);
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * The data a web page passes to its view. Anything else (a redirect, an error page) is turned
     * into the matching JSON answer and returned to the app straight away.
     */
    protected function pageData(mixed $response): array
    {
        if ($response instanceof View) {
            return $response->getData();
        }
        if ($response instanceof Response && ($response->original ?? null) instanceof View) {
            if ($response->getStatusCode() >= 400) {
                throw new HttpResponseException(response()->json(['message' => 'This page is not available.'], $response->getStatusCode()));
            }
            return $response->original->getData();
        }

        throw new HttpResponseException($this->actionJson($response));
    }

    /** What a web action did, as JSON: {message, warning, redirect} or 422 {message, errors}. */
    protected function actionJson(mixed $response, array $extra = []): JsonResponse
    {
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $session = request()->hasSession() ? request()->session() : null;
        $flashed = $session ? (array) $session->get('_flash.new', []) : [];
        $get = fn (string $key) => in_array($key, $flashed, true) ? $session->get($key) : null;

        $errors  = $get('errors');
        $error   = $get('error');
        $warning = $get('warning');
        $message = $get('success') ?? $get('info') ?? $warning;

        // This answer carries the messages: don't show them again on the next request.
        if ($session) {
            foreach ($flashed as $key) {
                $session->forget($key);
            }
            $session->put('_flash.new', []);
        }

        if ($errors instanceof ViewErrorBag && $errors->any()) {
            $bag = $errors->getBag('default')->toArray();
            return response()->json(['message' => collect($bag)->flatten()->first(), 'errors' => $bag], 422);
        }
        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        $status = $response instanceof Response ? $response->getStatusCode() : 200;
        if ($status >= 400) {
            return response()->json(['message' => $message ?? 'Something went wrong.'], $status);
        }

        return response()->json($extra + [
            'message'  => $message,
            'warning'  => $warning !== null,
            'redirect' => $response instanceof RedirectResponse ? $this->webPath($response->getTargetUrl()) : null,
        ]);
    }

    /** "/checkout?buy_now=1" from a full URL; external URLs (a payment page) are kept whole. */
    protected function webPath(string $url): string
    {
        $base = rtrim(url('/'), '/');
        return str_starts_with($url, $base) ? (substr($url, strlen($base)) ?: '/') : $url;
    }
}
