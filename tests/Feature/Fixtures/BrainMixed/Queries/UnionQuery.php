<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\BrainMixed\Queries;

use Brain\Query;

class UnionQuery extends Query
{
    public function __construct(
        protected int|string $id,
    ) {
        //
    }

    public function handle(): mixed
    {
        return null;
    }
}
