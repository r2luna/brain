<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Attributes\Sensitive;
use Brain\Task;

#[Sensitive('password')]
class ValidatedSecretTask extends Task
{
    public function handle(): self
    {
        return $this;
    }

    protected function rules(): array
    {
        return [
            'email' => 'required',
            'password' => 'string|min:6',
        ];
    }
}
