<?php

return [
    // Turn the check off (for example in tests) with INVISIBLE_CHALLENGE_ENABLED=false.
    'enabled' => (bool) env('INVISIBLE_CHALLENGE_ENABLED', true),

    // Leading zero bits the browser must find. Each extra bit doubles the work: 15 takes about a second on a phone.
    'difficulty' => (int) env('INVISIBLE_CHALLENGE_DIFFICULTY', 15),

    // A form sent sooner than this after the puzzle was issued is refused (people can't fill in a form that fast).
    'min_seconds' => (int) env('INVISIBLE_CHALLENGE_MIN_SECONDS', 3),

    // How long a puzzle stays valid.
    'max_age_seconds' => 1800,

    // The route that hands out puzzles. Set to null to register your own.
    'route' => [
        'uri' => 'invisible-challenge',
        'middleware' => ['throttle:30,1'],
    ],

    // Cache store that remembers used answers (null = the default store).
    'cache_store' => env('INVISIBLE_CHALLENGE_CACHE_STORE'),
];
