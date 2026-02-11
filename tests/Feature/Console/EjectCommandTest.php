<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->targetBase = base_path('app/Brain');
    $this->sourcePath = dirname(__DIR__, 3).'/src';

    // Clean up any leftover ejected files
    if (File::isDirectory($this->targetBase)) {
        File::deleteDirectory($this->targetBase);
    }
});

afterEach(function (): void {
    // Always clean up after each test
    if (File::isDirectory($this->targetBase)) {
        File::deleteDirectory($this->targetBase);
    }

    // Remove config if it was published during test
    if (File::exists(config_path('brain.php'))) {
        File::delete(config_path('brain.php'));
    }
});

test('it copies source files to target directory', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::exists($this->targetBase.'/Process.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Task.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Query.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Console/BaseCommand.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Console/BrainMap.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Console/Printer.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Console/ShowBrainCommand.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Exceptions/InvalidPayload.php'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Facades/Terminal.php'))->toBeTrue();
});

test('it does not copy excluded files', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    // BrainServiceProvider is replaced by generated one, not copied directly
    // EjectCommand should not be copied
    expect(File::exists($this->targetBase.'/Console/EjectCommand.php'))->toBeFalse();
});

test('it replaces namespaces correctly in copied files', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    $content = File::get($this->targetBase.'/Process.php');

    expect($content)->toContain('namespace App\\Brain;');
    expect($content)->not->toContain('namespace Brain;');
});

test('it replaces use statements in copied files', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    $content = File::get($this->targetBase.'/Console/BrainMap.php');

    expect($content)->toContain('use App\\Brain\\Process;');
    expect($content)->toContain('use App\\Brain\\Task;');
    expect($content)->not->toContain('use Brain\\Process;');
    expect($content)->not->toContain('use Brain\\Task;');
});

test('it copies and updates stubs', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::exists($this->targetBase.'/Processes/Console/stubs/process.stub'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Tasks/Console/stubs/task.stub'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Queries/Console/stubs/query.stub'))->toBeTrue();
    expect(File::exists($this->targetBase.'/Tests/Console/stubs/pest.stub'))->toBeTrue();

    $stubContent = File::get($this->targetBase.'/Tasks/Console/stubs/task.stub');
    expect($stubContent)->toContain('use App\\Brain\\Task;');
    expect($stubContent)->not->toContain('use Brain\\Task;');
});

test('it does not copy eject stubs', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::exists($this->targetBase.'/Console/stubs/eject/provider.stub'))->toBeFalse();
});

test('it generates a valid ServiceProvider', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    $providerPath = $this->targetBase.'/BrainServiceProvider.php';

    expect(File::exists($providerPath))->toBeTrue();

    $content = File::get($providerPath);

    expect($content)->toContain('namespace App\\Brain;');
    expect($content)->toContain('use App\\Brain\\Console\\ShowBrainCommand;');
    expect($content)->toContain('use App\\Brain\\Processes\\Console\\MakeProcessCommand;');
    expect($content)->toContain('use App\\Brain\\Queries\\Console\\MakeQueryCommand;');
    expect($content)->toContain('use App\\Brain\\Tasks\\Console\\MakeTaskCommand;');
    expect($content)->toContain('use App\\Brain\\Tests\\Console\\MakeTestCommand;');
    expect($content)->toContain('class BrainServiceProvider extends ServiceProvider');
    expect($content)->not->toContain('{{ namespace }}');
});

test('it updates existing user code', function (): void {
    $root = config('brain.root', 'Brain');
    $userTaskDir = app_path($root.'/Tasks');
    File::ensureDirectoryExists($userTaskDir);

    $userTask = <<<'PHP'
<?php

namespace App\Brain\Tasks;

use Brain\Task;

class MyTask extends Task
{
    public function handle(): self
    {
        return $this;
    }
}
PHP;

    File::put($userTaskDir.'/MyTask.php', $userTask);

    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    $updatedContent = File::get($userTaskDir.'/MyTask.php');

    expect($updatedContent)->toContain('use App\\Brain\\Task;');
    expect($updatedContent)->not->toContain('use Brain\\Task;');
});

test('it publishes config when missing', function (): void {
    // Ensure config doesn't exist
    if (File::exists(config_path('brain.php'))) {
        File::delete(config_path('brain.php'));
    }

    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::exists(config_path('brain.php')))->toBeTrue();
});

test('it skips config when already present', function (): void {
    File::ensureDirectoryExists(config_path());
    File::put(config_path('brain.php'), '<?php return [];');

    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0)
        ->expectsOutputToContain('already exists');

    // Content should not have been overwritten
    expect(File::get(config_path('brain.php')))->toBe('<?php return [];');
});

test('it accepts namespace via option', function (): void {
    $customTarget = base_path('app/Custom/Brain');

    $this->artisan('brain:eject', ['--namespace' => 'App\\Custom\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::isDirectory($customTarget))->toBeTrue();

    $content = File::get($customTarget.'/Process.php');
    expect($content)->toContain('namespace App\\Custom\\Brain;');

    // Cleanup
    File::deleteDirectory(base_path('app/Custom'));
});

test('it works with a custom namespace for all replacements', function (): void {
    $customTarget = base_path('app/MyApp/Core');

    $this->artisan('brain:eject', ['--namespace' => 'App\\MyApp\\Core'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    $content = File::get($customTarget.'/Console/BaseCommand.php');

    expect($content)->toContain('namespace App\\MyApp\\Core\\Console;');
    expect($content)->not->toContain('namespace Brain\\Console;');

    $providerContent = File::get($customTarget.'/BrainServiceProvider.php');
    expect($providerContent)->toContain('namespace App\\MyApp\\Core;');
    expect($providerContent)->toContain('use App\\MyApp\\Core\\Console\\ShowBrainCommand;');

    // Cleanup
    File::deleteDirectory(base_path('app/MyApp'));
});

test('it can be cancelled by the user', function (): void {
    $this->artisan('brain:eject', ['--namespace' => 'App\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'no')
        ->assertExitCode(0);

    expect(File::isDirectory($this->targetBase))->toBeFalse();
});

test('it resolves target path from composer.json PSR-4 mapping', function (): void {
    $composerPath = base_path('composer.json');
    $originalComposer = File::exists($composerPath) ? File::get($composerPath) : null;

    File::put($composerPath, json_encode([
        'autoload' => [
            'psr-4' => [
                'Domain\\' => 'src/Domain/',
            ],
        ],
    ]));

    $customTarget = base_path('src/Domain/Brain');

    $this->artisan('brain:eject', ['--namespace' => 'Domain\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::isDirectory($customTarget))->toBeTrue();
    expect(File::exists($customTarget.'/Process.php'))->toBeTrue();

    $content = File::get($customTarget.'/Process.php');
    expect($content)->toContain('namespace Domain\\Brain;');

    // Cleanup
    File::deleteDirectory(base_path('src/Domain'));

    if ($originalComposer !== null) {
        File::put($composerPath, $originalComposer);
    } else {
        File::delete($composerPath);
    }
});

test('it uses longest matching PSR-4 prefix', function (): void {
    $composerPath = base_path('composer.json');
    $originalComposer = File::exists($composerPath) ? File::get($composerPath) : null;

    File::put($composerPath, json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
                'App\\Modules\\' => 'modules/',
            ],
        ],
    ]));

    $customTarget = base_path('modules/Brain');

    $this->artisan('brain:eject', ['--namespace' => 'App\\Modules\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::isDirectory($customTarget))->toBeTrue();
    expect(File::exists($customTarget.'/Process.php'))->toBeTrue();

    $content = File::get($customTarget.'/Process.php');
    expect($content)->toContain('namespace App\\Modules\\Brain;');

    // Cleanup
    File::deleteDirectory(base_path('modules'));

    if ($originalComposer !== null) {
        File::put($composerPath, $originalComposer);
    } else {
        File::delete($composerPath);
    }
});

test('it warns when no PSR-4 mapping matches the namespace', function (): void {
    $composerPath = base_path('composer.json');
    $originalComposer = File::exists($composerPath) ? File::get($composerPath) : null;

    File::put($composerPath, json_encode([
        'autoload' => [
            'psr-4' => [
                'App\\' => 'app/',
            ],
        ],
    ]));

    $this->artisan('brain:eject', ['--namespace' => 'Custom\\Brain'])
        ->expectsOutputToContain('No PSR-4 mapping found for this namespace')
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    // Fallback should use lcfirst: Custom\Brain -> custom/Brain
    $fallbackTarget = base_path('custom/Brain');
    expect(File::isDirectory($fallbackTarget))->toBeTrue();

    // Cleanup
    File::deleteDirectory(base_path('custom'));

    if ($originalComposer !== null) {
        File::put($composerPath, $originalComposer);
    } else {
        File::delete($composerPath);
    }
});

test('it handles PSR-4 mapping with array paths', function (): void {
    $composerPath = base_path('composer.json');
    $originalComposer = File::exists($composerPath) ? File::get($composerPath) : null;

    File::put($composerPath, json_encode([
        'autoload' => [
            'psr-4' => [
                'Shared\\' => ['src/Shared/', 'lib/Shared/'],
            ],
        ],
    ]));

    $customTarget = base_path('src/Shared/Brain');

    $this->artisan('brain:eject', ['--namespace' => 'Shared\\Brain'])
        ->expectsConfirmation('Do you want to proceed with the eject?', 'yes')
        ->assertExitCode(0);

    expect(File::isDirectory($customTarget))->toBeTrue();
    expect(File::exists($customTarget.'/Process.php'))->toBeTrue();

    // Cleanup
    File::deleteDirectory(base_path('src/Shared'));

    if ($originalComposer !== null) {
        File::put($composerPath, $originalComposer);
    } else {
        File::delete($composerPath);
    }
});
