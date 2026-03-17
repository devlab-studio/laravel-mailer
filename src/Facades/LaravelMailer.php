<?php

namespace Devlab\LaravelMailer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Devlab\LaravelMailer\LaravelMailer
 */
class LaravelMailer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Devlab\LaravelMailer\LaravelMailer::class;
    }
}
