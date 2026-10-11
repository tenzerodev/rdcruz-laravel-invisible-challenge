<?php

declare(strict_types=1);

namespace Rdcruz\InvisibleChallenge;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * An invisible captcha that needs no third party. The server hands out a signed puzzle; the browser finds a
 * number that makes sha256("{challenge}:{number}") start with `difficulty` zero bits. That takes a person's
 * browser about a second, but makes sending thousands of spam forms expensive. An answer is also refused if it
 * comes back faster than a person can fill in a form, after it expires, or a second time.
 */
final class Challenge
{
    public const string MISSING = 'missing';

    public const string INVALID = 'invalid';

    public const string TOO_FAST = 'too_fast';

    public const string EXPIRED = 'expired';

    public const string UNSOLVED = 'unsolved';

    public const string USED = 'used';

    public function __construct(private readonly string $key) {}

    /** @return array{challenge: string, difficulty: int, min_seconds: int} */
    public function issue(): array
    {
        $difficulty = $this->difficulty();
        $body = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=').'.'.now()->getTimestamp().'.'.$difficulty;

        return ['challenge' => $body.'.'.$this->sign($body), 'difficulty' => $difficulty, 'min_seconds' => (int) config('invisible-challenge.min_seconds')];
    }

    /** Why the answer fails (one of the constants), or null when it passes. A passing answer is used up. */
    public function problem(?string $challenge, ?string $solution): ?string
    {
        if (! is_string($challenge) || ! is_string($solution) || preg_match('/^\d{1,12}$/', $solution) !== 1) {
            return self::MISSING;
        }
        $parts = explode('.', $challenge);
        if (count($parts) !== 4) {
            return self::INVALID;
        }
        [$nonce, $issuedAt, $difficulty, $signature] = $parts;
        if (! hash_equals($this->sign("{$nonce}.{$issuedAt}.{$difficulty}"), $signature) || (int) $difficulty < $this->difficulty()) {
            return self::INVALID;
        }
        $age = now()->getTimestamp() - (int) $issuedAt;
        if ($age < (int) config('invisible-challenge.min_seconds')) {
            return self::TOO_FAST;
        }
        $maxAge = (int) config('invisible-challenge.max_age_seconds', 1800);
        if ($age > $maxAge) {
            return self::EXPIRED;
        }
        if (! self::hasLeadingZeroBits(hash('sha256', "{$challenge}:{$solution}", true), (int) $difficulty)) {
            return self::UNSOLVED;
        }
        if (! $this->cache()->add('invisible-challenge:'.hash('sha256', $challenge), true, $maxAge)) {
            return self::USED;
        }

        return null;
    }

    public static function hasLeadingZeroBits(string $hash, int $bits): bool
    {
        for ($i = 0; $bits > 0; $i++, $bits -= 8) {
            $byte = ord($hash[$i]);
            if ($bits >= 8 ? $byte !== 0 : $byte >> (8 - $bits) !== 0) {
                return false;
            }
        }

        return true;
    }

    /** Finds an answer on the server: for tests and command-line clients. */
    public static function solve(string $challenge, int $difficulty): string
    {
        for ($n = 0; ; $n++) {
            if (self::hasLeadingZeroBits(hash('sha256', "{$challenge}:{$n}", true), $difficulty)) {
                return (string) $n;
            }
        }
    }

    private function difficulty(): int
    {
        return max(1, min(24, (int) config('invisible-challenge.difficulty')));
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', 'invisible-challenge|'.$body, $this->key);
    }

    private function cache(): Repository
    {
        return Cache::store(config('invisible-challenge.cache_store'));
    }
}
