<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\BrainMixed\Actions;

use Brain\Action;

class RealAction extends Action
{
    public function handle(): self
    {
        return $this;
    }
}
