<?php

declare(strict_types=1);

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

        $object = new Brain\Console\BrainMap;

        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod('getClassFullNameFromFile');
        $method->setAccessible(true);

        $output = $method->invokeArgs($object, [$filePath]);

        unlink($filePath);

        expect($output)->toBe('\MyApp\Services\TestClass');

    });

});
