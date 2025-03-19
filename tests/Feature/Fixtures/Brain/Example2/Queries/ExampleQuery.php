<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures\Brain\Example2\Queries;

use Brain\Query;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use stdClass;

class ExampleQuery extends Query
{
    public function handle(): Collection|stdClass
    {
        return Model::query()
            ->getQuery()
            ->get();
    }
}
