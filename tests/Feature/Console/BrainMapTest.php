<?php

declare(strict_types=1);

use Brain\Console\BrainMap;

beforeEach(function (): void {
    $this->object = new BrainMap;
    $this->reflection = new ReflectionClass($this->object::class);
});

it('should throw exception when dir do not exists', function (): void {
    config()->set('brain.root', __DIR__.'/../Fixtures/Brain2');
    $brain = new BrainMap;
})->throws(Exception::class, 'Brain directory not found');

describe('load testsuite', function (): void {
    beforeEach(function (): void {
        config()->set('brain.root', __DIR__.'/../Fixtures/Brain');
    });

    it('should return a list of the entire set of domains', function (): void {
        $brain = new BrainMap;
        $basePath = dirname(__DIR__, 3); // Adjust according to the directory structure
        $data = [
            'Example' => [
                'domain' => 'Example',
                'path' => $basePath.'/tests/Feature/Console/../Fixtures/Brain/Example',
                'processes' => [
                    [
                        'name' => 'ExampleProcess',
                        'chain' => false,
                        'tasks' => [
                            [
                                'name' => 'ExampleTask4',
                                'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4::class,
                                'queue' => true,
                                'type' => 'task',
                                'properties' => [],
                            ],
                        ],
                    ],
                ],
                'tasks' => [
                    [
                        'name' => 'ExampleTask',
                        'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask::class,
                        'queue' => false,
                        'type' => 'task',
                        'properties' => [
                            ['name' => 'email', 'type' => 'string', 'direction' => 'output'],
                            ['name' => 'paymentId', 'type' => 'int', 'direction' => 'output'],
                        ],
                    ],
                    [
                        'name' => 'ExampleTask2',
                        'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask2::class,
                        'queue' => false,
                        'type' => 'task',
                        'properties' => [
                            ['name' => 'email', 'type' => 'string', 'direction' => 'output'],
                            ['name' => 'paymentId', 'type' => 'int', 'direction' => 'output'],
                            ['name' => 'id', 'type' => 'int', 'direction' => 'input'],
                        ],
                    ],
                    [
                        'name' => 'ExampleTask3',
                        'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask3::class,
                        'queue' => false,
                        'type' => 'task',
                        'properties' => [],
                    ],
                    [
                        'name' => 'ExampleTask4',
                        'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4::class,
                        'queue' => true,
                        'type' => 'task',
                        'properties' => [],
                    ],
                ],
                'queries' => [
                    [
                        'name' => 'ExampleQuery',
                        'fullName' => Tests\Feature\Fixtures\Brain\Example\Queries\ExampleQuery::class,
                        'properties' => [
                            ['name' => 'name', 'type' => 'string'],
                        ],
                    ],
                ],
            ],
            'Example2' => [
                'domain' => 'Example2',
                'path' => $basePath.'/tests/Feature/Console/../Fixtures/Brain/Example2',
                'processes' => [
                    [
                        'name' => 'ExampleProcess2',
                        'chain' => true,
                        'tasks' => [
                            [
                                'name' => 'ExampleTask4',
                                'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4::class,
                                'queue' => true,
                                'type' => 'task',
                                'properties' => [],
                            ],
                            [
                                'name' => 'ExampleProcess',
                                'fullName' => Tests\Feature\Fixtures\Brain\Example\Processes\ExampleProcess::class,
                                'queue' => false,
                                'type' => 'process',
                                'properties' => [],
                            ],
                        ],
                    ],
                ],
                'tasks' => [],
                'queries' => [
                    [
                        'name' => 'ExampleQuery',
                        'fullName' => Tests\Feature\Fixtures\Brain\Example2\Queries\ExampleQuery::class,
                        'properties' => [],
                    ],
                ],
            ],
            'Example3' => [
                'domain' => 'Example3',
                'path' => $basePath.'/tests/Feature/Console/../Fixtures/Brain/Example3',
                'processes' => [],
                'tasks' => [],
                'queries' => [],
            ],
        ];

        expect($brain->map->toArray())
            ->toBe($data);
    });
});

describe('loadProcessesFor testsuite', function (): void {
    it('should list all processes in a dir', function (): void {
        $method = $this->reflection->getMethod('loadProcessesFor');
        $path = __DIR__.'/../Fixtures/Brain/Example';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output)->toMatchArray([
                [
                    'name' => 'ExampleProcess',
                    'chain' => false,
                    'tasks' => [
                        ['name' => 'ExampleTask4', 'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4::class, 'queue' => true, 'properties' => [], 'type' => 'task'],
                    ],
                ],
            ]);
    });

    it('should return an empty array if dir doesnt exists', function (): void {
        $method = $this->reflection->getMethod('loadProcessesFor');
        $path = __DIR__.'/../Fixtures/Brain/Example3';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(0);
    });

    it('should check if the process is in chain', function (): void {
        $method = $this->reflection->getMethod('loadProcessesFor');
        $path = __DIR__.'/../Fixtures/Brain/Example2';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output[0])->toMatchArray([
                'name' => 'ExampleProcess2',
                'chain' => true,
            ]);
    });
});

describe('loadTasksFor testsuite', function (): void {
    it('should load tasks from a given path', function (): void {
        $method = $this->reflection->getMethod('loadTasksFor');
        $path = __DIR__.'/../Fixtures/Brain/Example';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(4)
            ->and($output)->toMatchArray([
                [
                    'name' => 'ExampleTask',
                    'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask::class,
                    'queue' => false,
                    'type' => 'task',
                    'properties' => [
                        ['name' => 'email', 'type' => 'string', 'direction' => 'output'],
                        ['name' => 'paymentId', 'type' => 'int', 'direction' => 'output'],
                    ],
                ],
                [
                    'name' => 'ExampleTask2',
                    'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask2::class,
                    'queue' => false,
                    'type' => 'task',
                    'properties' => [
                        ['name' => 'email', 'type' => 'string', 'direction' => 'output'],
                        ['name' => 'paymentId', 'type' => 'int', 'direction' => 'output'],
                        ['name' => 'id', 'type' => 'int', 'direction' => 'input'],
                    ],
                ],
                [
                    'name' => 'ExampleTask3',
                    'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask3::class,
                    'queue' => false,
                    'type' => 'task',
                    'properties' => [],
                ],
                [
                    'name' => 'ExampleTask4',
                    'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4::class,
                    'queue' => true,
                    'type' => 'task',
                    'properties' => [],
                ],
            ]);
    });

    it('should return an empty array if the directory does not exists', function (): void {
        $method = $this->reflection->getMethod('loadTasksFor');
        $path = __DIR__.'/../Fixtures/Brain/Example3';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(0);
    });

    it('should check if the task needs to run in a queue', function (): void {
        $method = $this->reflection->getMethod('loadTasksFor');
        $path = __DIR__.'/../Fixtures/Brain/Example';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(4)
            ->and($output[0])->toMatchArray([
                'name' => 'ExampleTask',
                'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask::class,
                'queue' => false,
            ])
            ->and($output[3])->toMatchArray([
                'name' => 'ExampleTask4',
                'fullName' => Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4::class,
                'queue' => true,
            ]);
    });
});

describe('getPropertiesFor testsuite', function (): void {
    it('should get properties from a dockblock', function (): void {
        $reflection = new ReflectionClass(Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask::class);

        $method = $this->reflection->getMethod('getPropertiesFor');
        $output = $method->invokeArgs($this->object, [$reflection]);

        expect($output)->toHaveCount(2)
            ->and($output)
            ->toMatchArray([
                [
                    'name' => 'email',
                    'type' => 'string',
                    'direction' => 'output',
                ],
                [
                    'name' => 'paymentId',
                    'type' => 'int',
                    'direction' => 'output',
                ],
            ]);
    });

    it('directions should be based on the read and non read properties', function (): void {
        $reflection = new ReflectionClass(Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask2::class);

        $method = $this->reflection->getMethod('getPropertiesFor');
        $output = $method->invokeArgs($this->object, [$reflection]);

        expect($output)->toHaveCount(3)
            ->and($output)
            ->toMatchArray([
                [
                    'name' => 'email',
                    'type' => 'string',
                    'direction' => 'output',
                ],
                [
                    'name' => 'paymentId',
                    'type' => 'int',
                    'direction' => 'output',
                ],
                [
                    'name' => 'id',
                    'type' => 'int',
                    'direction' => 'input',
                ],
            ]);
    });

    it('should return null if there is any tag that is not property-read or property', function (): void {
        $reflection = new ReflectionClass(Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask3::class);

        $method = $this->reflection->getMethod('getPropertiesFor');
        $output = $method->invokeArgs($this->object, [$reflection]);

        expect($output)->toHaveCount(0)
            ->and($output)
            ->toMatchArray([]);
    });

    it('should return null if there is no docblock', function (): void {
        $reflection = new ReflectionClass(Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask4::class);

        $method = $this->reflection->getMethod('getPropertiesFor');
        $output = $method->invokeArgs($this->object, [$reflection]);

        expect($output)->toBeEmpty();
    });
});

describe('loadQueriesFor testsuite', function (): void {
    it('should load queries from a given path', function (): void {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__.'/../Fixtures/Brain/Example';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output[0])
            ->toMatchArray([
                'name' => 'ExampleQuery',
                'fullName' => Tests\Feature\Fixtures\Brain\Example\Queries\ExampleQuery::class,
            ]);
    });

    it('should load all properties', function (): void {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__.'/../Fixtures/Brain/Example';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output[0])->toMatchArray([
                'name' => 'ExampleQuery',
                'fullName' => Tests\Feature\Fixtures\Brain\Example\Queries\ExampleQuery::class,
                'properties' => [
                    [
                        'name' => 'name',
                        'type' => 'string',
                    ],
                ],
            ]);
    });

    it('should return an empty array when there is no construct', function (): void {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__.'/../Fixtures/Brain/Example2';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output[0])->toMatchArray([
                'name' => 'ExampleQuery',
                'fullName' => Tests\Feature\Fixtures\Brain\Example2\Queries\ExampleQuery::class,
                'properties' => [],
            ]);
    });

    it('should return an empty array if the directory does not exists', function (): void {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__.'/../Fixtures/Brain/Example3';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(0);
    });
});

describe('getReflectionClass testsuite', function (): void {
    it('should create a reflection class from a given string', function (): void {
        $method = $this->reflection->getMethod('getReflectionClass');
        $path = Tests\Feature\Fixtures\QueuedTask::class;
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toBeInstanceOf(ReflectionClass::class);
    });

    it('should create a reflection class from a SplFileInfo', function (): void {
        $method = $this->reflection->getMethod('getReflectionClass');
        $path = new SplFileInfo(__DIR__.'/../Fixtures/QueuedTask.php');
        $output = $method->invokeArgs($this->object, [$path]);
        expect($output)->toBeInstanceOf(ReflectionClass::class);
    });
});

describe('getClassFullNameFromFile testsuite', function (): void {

    it('should get the full name from a file', function (): void {
        $content = <<<'PHP'
<?php

namespace MyApp\Services;

use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask;

class TestClass
{
    //
}
PHP;

        // Create a temporary file
        $filePath = tempnam(sys_get_temp_dir(), 'TestFile');
        file_put_contents($filePath, $content);

        $method = $this->reflection->getMethod('getClassFullNameFromFile');
        $method->setAccessible(true);

        $output = $method->invokeArgs($this->object, [$filePath]);

        unlink($filePath);

        expect($output)->toBe('\MyApp\Services\TestClass');
    });
});
