<?php

declare(strict_types=1);

namespace Rdcruz\InvisibleChallenge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Rdcruz\InvisibleChallenge\Challenge;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only with a solved challenge: in the X-Challenge and X-Challenge-Solution headers
 * (fetch), or the _challenge and _challenge_solution fields (an ordinary form post).
 */
final class RequireChallenge
{
    public function __construct(private readonly Challenge $challenge) {}

    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('invisible-challenge.enabled')) {
            return $next($request);
        }

        $problem = $this->challenge->problem(
            $request->header('X-Challenge') ?? $request->input('_challenge'),
            $request->header('X-Challenge-Solution') ?? $request->input('_challenge_solution'),
        );

        if ($problem !== null) {
            throw ValidationException::withMessages([
                '_challenge' => $problem === Challenge::TOO_FAST
                    ? __('That was quicker than we expected. Wait a few seconds and send it again.')
                    : __('We could not confirm you are a person. Reload the page and try again.'),
            ]);
        }

        return $next($request);
    }
}
