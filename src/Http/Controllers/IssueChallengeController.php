<?php

declare(strict_types=1);

namespace Rdcruz\InvisibleChallenge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Rdcruz\InvisibleChallenge\Challenge;

final class IssueChallengeController
{
    public function __invoke(Challenge $challenge): JsonResponse
    {
        return response()->json($challenge->issue())->header('Cache-Control', 'no-store');
    }
}
