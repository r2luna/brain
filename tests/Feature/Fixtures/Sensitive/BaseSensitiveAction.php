<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Sensitive;

use Brain\Action;
use Brain\Attributes\Sensitive;

#[Sensitive('password')]
abstract class BaseSensitiveAction extends Action {}
