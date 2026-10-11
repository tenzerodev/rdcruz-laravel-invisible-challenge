# rdcruz-laravel-invisible-challenge

An **invisible, self-hosted captcha for Laravel.** No Google, no Cloudflare, nothing for your visitors to click, and nothing that tracks them. It keeps your Lighthouse score intact.

How it works:

1. When a visitor first taps or types on a page with a protected form, the browser quietly fetches a signed puzzle from your site.
2. It finds a number that makes `sha256("{puzzle}:{number}")` start with 15 zero bits, which takes about a second on a phone in the background.
3. The answer goes along with the form. Your server checks it in microseconds, and refuses it if it is wrong, forged, reused, older than 30 minutes, or **sent faster than a person can fill in a form**.

One spam message costs a bot a second of computing; ten thousand cost it hours. Combine it with Laravel's `throttle` middleware and a honeypot field for most small sites.

## Install

```sh
composer require tenzerodev/rdcruz-laravel-invisible-challenge
php artisan vendor:publish --tag=invisible-challenge-assets
```

Add this to your `composer.json` `repositories` until the package is on Packagist: `{ "type": "vcs", "url": "https://github.com/tenzerodev/rdcruz-laravel-invisible-challenge" }`.

## Use

Protect a route with the `challenge` middleware:

```php
Route::post('/contact', ContactController::class)->middleware(['throttle:5,1', 'challenge']);
```

Load the script once in your layout:

```blade
@invisibleChallengeScript
```

**Ordinary forms:** add `data-challenge`. The answer is added as hidden fields just before the form is sent.

```html
<form method="post" action="/contact" data-challenge>
  @csrf
  …
</form>
```

**fetch():** spread the headers in.

```js
await fetch('/contact', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, ...(await window.invisibleChallenge()) },
  body: JSON.stringify(data),
});
```

A failed check is a normal validation error on `_challenge` (422 JSON for fetch, or back to the form with `$errors`).

## Config

`php artisan vendor:publish --tag=invisible-challenge-config`

| Key | Default | |
|---|---|---|
| `enabled` | `true` | Set `INVISIBLE_CHALLENGE_ENABLED=false` in `phpunit.xml` so your other tests don't need it |
| `difficulty` | `15` | Zero bits to find. Each extra bit doubles the work |
| `min_seconds` | `3` | Answers sent sooner after the puzzle was issued are refused |
| `max_age_seconds` | `1800` | How long a puzzle is valid |
| `route` | `/invisible-challenge`, `throttle:30,1` | Where puzzles are handed out (`null` to register your own) |
| `cache_store` | default | Where used answers are remembered, so each works once |

Used answers are stored only as a hash, in your cache, until they expire. Nothing about the visitor is stored.

## Testing your own forms

```php
use Rdcruz\InvisibleChallenge\Challenge;

$issued = $this->getJson('/invisible-challenge')->json();
$this->travel(5)->seconds();
$this->postJson('/contact', $data, [
    'X-Challenge' => $issued['challenge'],
    'X-Challenge-Solution' => Challenge::solve($issued['challenge'], $issued['difficulty']),
])->assertOk();
```

## Tests

```sh
composer install && vendor/bin/phpunit
```

## License

[MIT](LICENSE)
