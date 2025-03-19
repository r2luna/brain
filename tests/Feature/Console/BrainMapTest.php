<?php

declare(strict_types=1);

beforeEach(function () {
    $this->object = new Brain\Console\BrainMap;
    $this->reflection = new ReflectionClass(get_class($this->object));
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
