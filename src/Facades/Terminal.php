<?php

declare(strict_types=1);

namespace Brain\Facades;

use Illuminate\Support\Facades\Facade;
use Laravel\Prompts\Terminal as PromptsTerminal;

class Terminal extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PromptsTerminal::class;
    }
}
