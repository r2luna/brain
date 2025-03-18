<?php

declare(strict_types=1);

use Brain\Console\BrainMap;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->brainMap = new BrainMap;
});

test('BrainMap can be instantiated', function () {
    expect($this->brainMap)->toBeInstanceOf(BrainMap::class);
});

test('loadDomains loads directory structure correctly', function () {
    // Mock File facade
    File::shouldReceive('directories')
        ->once()
        ->with(app_path('Brain'))
        ->andReturn([
            app_path('Brain/UserManagement'),
            app_path('Brain/OrderProcessing'),
        ]);

    $brainMap = new BrainMap;

    // Access protected property using reflection
    $reflection = new ReflectionClass($brainMap);
    $property = $reflection->getProperty('domains');
    $property->setAccessible(true);

    $domains = $property->getValue($brainMap);
    expect($domains)->toBeArray();
});

test('loadProcessesFor returns empty array for non-existent directory', function () {
    // Mock File facade
    File::shouldReceive('directories')
        ->once()
        ->with(app_path('Brain'))
        ->andReturn([]);

    $brainMap = new BrainMap;

    // Test private method using reflection
    $reflection = new ReflectionClass($brainMap);
    $method = $reflection->getMethod('loadProcessesFor');
    $method->setAccessible(true);

    $result = $method->invoke($brainMap, '/non/existent/path');
    expect($result)->toBeArray()->toBeEmpty();
});

test('loadTasksFor returns empty array for non-existent directory', function () {
    // Mock File facade
    File::shouldReceive('directories')
        ->once()
        ->with(app_path('Brain'))
        ->andReturn([]);

    $brainMap = new BrainMap;

    // Test private method using reflection
    $reflection = new ReflectionClass($brainMap);
    $method = $reflection->getMethod('loadTasksFor');
    $method->setAccessible(true);

    $result = $method->invoke($brainMap, '/non/existent/path');
    expect($result)->toBeArray()->toBeEmpty();
});

test('loadQueriesFor returns empty array for non-existent directory', function () {
    // Mock File facade
    File::shouldReceive('directories')
        ->once()
        ->with(app_path('Brain'))
        ->andReturn([]);

    $brainMap = new BrainMap;

    // Test private method using reflection
    $reflection = new ReflectionClass($brainMap);
    $method = $reflection->getMethod('loadQueriesFor');
    $method->setAccessible(true);

    $result = $method->invoke($brainMap, '/non/existent/path');
    expect($result)->toBeArray()->toBeEmpty();
});

test('getClassFullNameFromFile extracts class name correctly', function () {
    $brainMap = new BrainMap;

    // Test private method using reflection
    $reflection = new ReflectionClass($brainMap);
    $method = $reflection->getMethod('getClassFullNameFromFile');
    $method->setAccessible(true);

    // Create a temporary test file
    $tempFile = tempnam(sys_get_temp_dir(), 'test_');
    file_put_contents($tempFile, '<?php
        namespace Test\Namespace;
        class TestClass {}
    ');

    $result = $method->invoke($brainMap, $tempFile);
    expect($result)->toBe('\Test\Namespace\TestClass');

    // Clean up
    unlink($tempFile);
});
