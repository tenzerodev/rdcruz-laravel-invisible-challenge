<?php

declare(strict_types=1);

namespace Rdcruz\InvisibleChallenge;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rdcruz\InvisibleChallenge\Http\Controllers\IssueChallengeController;
use Rdcruz\InvisibleChallenge\Http\Middleware\RequireChallenge;

final class InvisibleChallengeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/invisible-challenge.php', 'invisible-challenge');
        $this->app->singleton(Challenge::class, fn (): Challenge => new Challenge((string) config('app.key')));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('challenge', RequireChallenge::class);

        if (($route = config('invisible-challenge.route')) !== null) {
            Route::get($route['uri'], IssueChallengeController::class)
                ->middleware($route['middleware'] ?? [])
                ->name('invisible-challenge.issue');
        }

        // <script> that solves puzzles for forms marked data-challenge, and for fetch() via window.invisibleChallenge()
        Blade::directive('invisibleChallengeScript', fn (): string => '<?php echo \'<script>window.invisibleChallengeUrl = \'.json_encode(route(\'invisible-challenge.issue\')).\';</script><script src="\'.e(asset(\'vendor/invisible-challenge/invisible-challenge.js\')).\'" defer></script>\'; ?>');

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/invisible-challenge.php' => config_path('invisible-challenge.php')], 'invisible-challenge-config');
            $this->publishes([__DIR__.'/../resources/js/invisible-challenge.js' => public_path('vendor/invisible-challenge/invisible-challenge.js')], 'invisible-challenge-assets');
        }
    }
}
