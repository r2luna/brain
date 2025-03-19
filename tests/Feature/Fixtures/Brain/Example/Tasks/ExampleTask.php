<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Brain\Example\Tasks;

use Brain\Task;

/**
 * Task SendEmail
 *
 * @property-read string $email
 * @property-read int $paymentId
 */
class ExampleTask extends Task
{
    public function handle(): self
    {
        //

        return $this;
    }
}
