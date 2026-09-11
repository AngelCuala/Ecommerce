# Registering the `admin` middleware

Laravel 11 registers middleware aliases in `bootstrap/app.php`. Add this inside the
`->withMiddleware()` callback:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ]);
})
```
