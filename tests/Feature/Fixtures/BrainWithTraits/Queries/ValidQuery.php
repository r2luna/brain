<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\BrainWithTraits\Queries;

use Brain\Query;

class ValidQuery extends Query
{
    public function handle(): string
    {
        return 'result';
    }
}
