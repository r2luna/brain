<?php

declare(strict_types=1);

namespace Brain\Facades;

use Illuminate\Support\Facades\Facade;
use Laravel\Prompts\Terminal as PromptsTerminal;

/** Facade for the Laravel Prompts Terminal. */
class Terminal extends Facade
{
    /** Get the registered name of the component. */
    protected static function getFacadeAccessor()
    {
        return PromptsTerminal::class;
    }
}
