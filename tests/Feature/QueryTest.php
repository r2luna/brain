<?php

declare(strict_types=1);

use Brain\Query;

test('run method executes handle method', function () {
    class TestQuery extends Query
    {
        private $value;

        public function __construct($value)
        {
            $this->value = $value;
        }

        public function handle(): mixed
        {
            return $this->value;
        }
    }

    $result = TestQuery::run('testValue');
    expect($result)->toBe('testValue');
});

test('run method accepts multiple arguments', function () {
    class MultiArgQuery extends Query
    {
        private $arg1;

        private $arg2;

        public function __construct($arg1, $arg2)
        {
            $this->arg1 = $arg1;
            $this->arg2 = $arg2;
        }

        public function handle(): mixed
        {
            return $this->arg1.' '.$this->arg2;
        }
    }

    $result = MultiArgQuery::run('Hello', 'World');
    expect($result)->toBe('Hello World');
});
