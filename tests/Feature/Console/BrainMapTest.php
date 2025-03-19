<?php

declare(strict_types=1);

use Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask;

beforeEach(function () {
    $this->object = new Brain\Console\BrainMap;
    $this->reflection = new ReflectionClass(get_class($this->object));
});

describe('getPropertiesFor testsuite', function () {
    it('should get properties from a dockblock', function () {
        $reflection = new ReflectionClass('\Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask');

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

    it('directions should be based on the read and non read properties', function () {
        $reflection = new ReflectionClass('\Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask2');

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
                ]
            ]);
    });

    it('should return null if there is any tag that is not property-read or property', function () {
        $reflection = new ReflectionClass('\Tests\Feature\Fixtures\Brain\Example\Tasks\ExampleTask3');

        $method = $this->reflection->getMethod('getPropertiesFor');
        $output = $method->invokeArgs($this->object, [$reflection]);

        expect($output)->toHaveCount(0)
            ->and($output)
            ->toMatchArray([]);
    });
});

describe('loadQueriesFor testsuite', function () {
    it('should load queries from a given path', function () {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__ . '/../Fixtures/Brain/Example';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output[0])
            ->toMatchArray([
                'name' => 'ExampleQuery',
                'fullName' => 'Tests\Feature\Fixtures\Brain\Example\Queries\ExampleQuery',
            ]);
    });

    it('should load all properties', function () {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__ . '/../Fixtures/Brain/Example';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output[0])->toMatchArray([
                'name' => 'ExampleQuery',
                'fullName' => 'Tests\Feature\Fixtures\Brain\Example\Queries\ExampleQuery',
                'properties' => [
                    [
                        'name' => 'name',
                        'type' => 'string',
                    ]
                ],
            ]);
    });

    it('should return an empty array when there is no construct', function () {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__ . '/../Fixtures/Brain/Example2';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(1)
            ->and($output[0])->toMatchArray([
                'name' => 'ExampleQuery',
                'fullName' => 'Tests\Feature\Fixtures\Brain\Example2\Queries\ExampleQuery',
                'properties' => [],
            ]);
    });

    it('should return an empty array if the directory does not exists', function () {
        $method = $this->reflection->getMethod('loadQueriesFor');
        $path = __DIR__ . '/../Fixtures/Brain/Example3';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toHaveCount(0);
    });
});

describe('getReflectionClass testsuite', function () {
    it('should create a reflection class from a given string', function () {
        $method = $this->reflection->getMethod('getReflectionClass');
        $path = '\Tests\Feature\Fixtures\QueuedTask';
        $output = $method->invokeArgs($this->object, [$path]);

        expect($output)->toBeInstanceOf(ReflectionClass::class);
    });

    it('should create a reflection class from a SplFileInfo', function () {
        $method = $this->reflection->getMethod('getReflectionClass');
        $path = new SplFileInfo(__DIR__ . '/../Fixtures/QueuedTask.php');
        $output = $method->invokeArgs($this->object, [$path]);
        expect($output)->toBeInstanceOf(ReflectionClass::class);
    });
});

describe('getClassFullNameFromFile testsuite', function () {

    it('should get the full name from a file', function () {
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
