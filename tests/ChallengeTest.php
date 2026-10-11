<?php

declare(strict_types=1);

namespace Rdcruz\InvisibleChallenge\Tests;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Rdcruz\InvisibleChallenge\Challenge;
use Rdcruz\InvisibleChallenge\InvisibleChallengeServiceProvider;

final class ChallengeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [InvisibleChallengeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('invisible-challenge.difficulty', 8);
    }

    protected function defineRoutes($router): void
    {
        Route::post('/contact', fn () => response()->json(['sent' => true]))->middleware('challenge');
    }

    /** @return array{X-Challenge: string, X-Challenge-Solution: string} */
    private function solved(): array
    {
        $issued = $this->getJson('/invisible-challenge')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->assertSame(8, $issued['difficulty']);

        return ['X-Challenge' => $issued['challenge'], 'X-Challenge-Solution' => Challenge::solve($issued['challenge'], $issued['difficulty'])];
    }

    public function test_a_solved_challenge_passes_once(): void
    {
        $headers = $this->solved();
        $this->travel(5)->seconds();

        $this->postJson('/contact', [], $headers)->assertOk()->assertJson(['sent' => true]);
        $this->postJson('/contact', [], $headers)->assertUnprocessable()->assertJsonValidationErrors('_challenge');
    }

    public function test_an_ordinary_form_post_can_send_the_answer_as_fields(): void
    {
        $headers = $this->solved();
        $this->travel(5)->seconds();

        $this->post('/contact', ['_challenge' => $headers['X-Challenge'], '_challenge_solution' => $headers['X-Challenge-Solution']])->assertOk();
    }

    public function test_it_refuses_missing_wrong_forged_fast_and_expired_answers(): void
    {
        $this->postJson('/contact')->assertUnprocessable();

        $headers = $this->solved();
        $this->postJson('/contact', [], $headers)->assertUnprocessable()->assertJsonPath('errors._challenge.0', 'That was quicker than we expected. Wait a few seconds and send it again.');

        $this->travel(5)->seconds();
        $this->postJson('/contact', [], [...$headers, 'X-Challenge-Solution' => '999999999999'])->assertUnprocessable();
        $this->postJson('/contact', [], [...$headers, 'X-Challenge' => 'abc.1700000000.8.forged'])->assertUnprocessable();

        $this->travel(31)->minutes();
        $this->postJson('/contact', [], $headers)->assertUnprocessable();
    }

    public function test_it_can_be_switched_off(): void
    {
        config(['invisible-challenge.enabled' => false]);
        $this->postJson('/contact')->assertOk();
    }

    public function test_leading_zero_bits(): void
    {
        $this->assertTrue(Challenge::hasLeadingZeroBits("\x00\x0f", 12));
        $this->assertFalse(Challenge::hasLeadingZeroBits("\x00\x1f", 12));
        $this->assertTrue(Challenge::hasLeadingZeroBits("\x00\x00\x01", 23));
    }
}
