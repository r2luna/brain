<?php

declare(strict_types=1);

beforeEach(function () {
    $this->object = new Brain\Console\BrainMap;
    $this->reflection = new ReflectionClass(get_class($this->object));
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
        $path = new SplFileInfo(__DIR__.'/../Fixtures/QueuedTask.php');
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
