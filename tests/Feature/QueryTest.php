<?php

declare(strict_types=1);

use Brain\Query;

test('run method executes handle method', function (): void {
    class TestQuery extends Query
    {
        public function __construct(private $value) {}

        public function handle(): mixed
        {
            return $this->value;
        }
    }

    $result = TestQuery::run('testValue');
    expect($result)->toBe('testValue');
});

test('run method accepts multiple arguments', function (): void {
    class MultiArgQuery extends Query
    {
        public function __construct(private $arg1, private $arg2) {}

        public function handle(): mixed
        {
            return $this->arg1.' '.$this->arg2;
        }
    }

    $result = MultiArgQuery::run('Hello', 'World');
    expect($result)->toBe('Hello World');
});
