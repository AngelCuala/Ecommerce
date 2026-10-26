<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One per request, so the logistics rates and zones it loads are read once and reset after a change.
        $this->app->scoped(\App\Services\ShippingService::class);

        // ALVY Assistant's language model (tests bind a scripted fake instead).
        $this->app->bind(\App\Services\Assistant\AssistantModel::class, \App\Services\Assistant\ClaudeAssistantModel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The web guard keeps a "remember me" cookie per portal (App\Support\Portal).
        \Illuminate\Support\Facades\Auth::extend('session', function ($app, string $name, array $config) {
            $guard = new \App\Auth\PortalSessionGuard(
                $name,
                \Illuminate\Support\Facades\Auth::createUserProvider($config['provider'] ?? null),
                $app['session.store'],
                rehashOnLogin: $app['config']->get('hashing.rehash_on_login', true),
                timeboxDuration: $app['config']->get('auth.timebox_duration', 200000),
                hashKey: $app['config']->get('app.key'),
            );
            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
            if (isset($config['remember'])) {
                $guard->setRememberDuration($config['remember']);
            }
            return $guard;
        });

        // Mail over TLS with a custom list of trusted certificate authorities (MAIL_CA_FILE).
        // Needed on machines where antivirus software (e.g. Avast Mail Shield) re-signs the
        // connection to the mail server; leave MAIL_CA_FILE empty to use PHP's defaults.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, function () {
            $ca = config('mail.ca_file');
            if (! $ca) {
                return;
            }
            $path = str_starts_with($ca, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $ca) ? $ca : base_path($ca);
            $transport = app('mailer')->getSymfonyTransport();
            if (is_file($path) && $transport instanceof \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport
                && ($stream = $transport->getStream()) instanceof \Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream) {
                $stream->setStreamOptions(array_replace_recursive($stream->getStreamOptions(), ['ssl' => ['cafile' => $path]]));
            }
        });

        // The login time limit counts from each sign-in (password, code, Google or remember-me).
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function () {
            if (request()->hasSession()) {
                request()->session()->put(\App\Http\Middleware\EnforceLoginTimeLimit::STARTED, now()->getTimestamp());
                request()->session()->put(\App\Http\Middleware\EnforceLoginTimeLimit::SEEN, now()->getTimestamp());
            }
        });

        // A rider who logs out is Offline and gets no more job offers.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, function ($event) {
            if ($event->user) {
                \App\Models\Rider::where('user_id', $event->user->id)->update(['is_online' => false]);
            }
        });
    }
}
