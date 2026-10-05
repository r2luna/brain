<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Attributes\Sensitive;
use Brain\Task;

#[Sensitive('password')]
abstract class BaseSensitiveTask extends Task {}
