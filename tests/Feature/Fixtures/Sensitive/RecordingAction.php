<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Action;

class RecordingAction extends Action
{
    public static array $keys = [];

    public function handle(): self
    {
        self::$keys = static::getSensitiveKeys();

        return $this;
    }
}
