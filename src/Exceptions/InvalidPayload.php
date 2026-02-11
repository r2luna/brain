<?php

declare(strict_types=1);

namespace Brain\Exceptions;

use Exception;

/** Exception thrown when a task receives an invalid payload. */
final class InvalidPayload extends Exception {}
