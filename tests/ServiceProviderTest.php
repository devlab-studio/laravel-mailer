<?php

use Devlab\LaravelMailer\LaravelMailerServiceProvider;
use Illuminate\Support\Arr;

it('registers the oauth mailers used by CustomMailChannel', function () {
    expect(config('mail.mailers.google'))->toBe(['transport' => 'array'])
        ->and(config('mail.mailers.microsoft'))->toBe(['transport' => 'array']);
});

it('keeps the oauth mailers already defined by the app', function () {
    config()->set('mail.mailers.google', ['transport' => 'log']);
    config()->set('mail.mailers', Arr::except(config('mail.mailers'), 'microsoft'));

    $provider = new LaravelMailerServiceProvider(app());
    (fn () => $this->registerOAuthMailers())->call($provider);

    expect(config('mail.mailers.google'))->toBe(['transport' => 'log'])
        ->and(config('mail.mailers.microsoft'))->toBe(['transport' => 'array']);
});
