# Routing Architecture

This project doesn't use Laravel's default controller-based routing. If you're experienced with stock Laravel, here's what's different and why.

## Actions replace Controllers

There is no `app/Http/Controllers/{Model}Controller.php` per resource. Business operations live in `app/{Domain}/Actions/`, one class per use case, using [`lorisleiva/laravel-actions`](https://laravelactions.com)'s `AsAction` trait.

An Action class is registered directly as a route handler:

```php
// routes/api/health.php
Route::get('/health', CheckServiceHealth::class);
```

`laravel-actions` makes the class invokable as a controller via `asController()`. The same class can also expose `asCommand()` (Artisan), `asJob()`, or `asListener()` — one class, multiple entry points, sharing one `handle()` method. See `App\HealthCheck\Actions\CheckServiceHealth` for an Action that is simultaneously an HTTP controller and an Artisan command.

## Route files are per-domain, not flat

`routes/api.php` is an index, not where routes are defined:

```php
require base_path('routes/api/health.php');
```

Each domain registers its own routes in `routes/api/{domain}.php`. Adding a domain means adding a new route file and a `require` line — never appending routes to a shared flat file.

## Global API middleware lives in `bootstrap/app.php`

Laravel 11+ removed `app/Http/Kernel.php`. Middleware applied to every API route (`ApiVersion`, `ForceJsonResponse`) is registered in `bootstrap/app.php`'s `withMiddleware()` callback, not a Kernel class.

## Where to look next

- Root `CLAUDE.md` — Domain Architecture section, for the full `app/{Domain}/` layout
- `app/{Domain}/CLAUDE.md` — per-domain purpose, endpoints, and how to extend it (e.g. `app/HealthCheck/CLAUDE.md`)
