<?php

namespace Tests\Feature\Fixtures\Brain\Example\Tasks;


use Brain\Task;

/**
 * Task ExampleTask2
 *
 * @property-read string $email
 * @property-read int $paymentId
 *
 * @property int $id
 */
class ExampleTask2 extends Task
{
    public function handle(): self
    {
        //

        return $this;
    }
}
