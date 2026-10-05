<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Action;

class CancelingAction extends Action
{
    public function handle(): self
    {
        $this->cancelWorkflow();

        return $this;
    }
}
