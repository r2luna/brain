<?php

declare(strict_types=1);

arch()
    ->expect('src')
    ->toUseStrictTypes()
    ->not->toUse(['die', 'dd', 'dump', 'ds', 'xdebug_break']);
