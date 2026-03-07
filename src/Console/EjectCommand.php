<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/** Console command to eject Brain package source files into the user's project. */
class EjectCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'brain:eject {--namespace= : Target namespace (prompted if not provided)}';

    /** @var string The console command description. */
    protected $description = 'Eject Brain package source files into your project';

    /**
     * Cached PSR-4 mapping from composer.json.
     *
     * @var array<string, string|list<string>>|null
     */
    private ?array $psr4Map = null;

    /**
     * Files to exclude from copy (replaced by generated provider or not needed after eject).
     */
    private array $excludedFiles = [
        'BrainServiceProvider.php',
        'Console/EjectCommand.php',
    ];

    /** Execute the eject command. */
    public function handle(): int
    {
        $namespace = $this->askNamespace();

        $targetPath = $this->targetPath($namespace);

        $this->output->writeln('');
        $this->output->writeln(' <options=bold>Brain Eject</>');
        $this->output->writeln(' ───────────');
        $this->output->writeln(" Target namespace: <info>{$namespace}</info>");
        $this->output->writeln(" Target directory: <info>{$targetPath}</info>");

        if (! $this->namespaceHasPsr4Mapping($namespace)) {
            $this->output->writeln('');
            $this->output->writeln(' <comment>Warning: No PSR-4 mapping found for this namespace.</comment>');
            $this->output->writeln(' <comment>Path was resolved using fallback. Please update your composer.json autoloading.</comment>');
        }

        $this->output->writeln('');

        if (! confirm('Do you want to proceed with the eject?', true)) {
            $this->output->writeln(' <comment>Eject cancelled.</comment>');

            return self::SUCCESS;
        }

        $this->output->writeln('');

        $copiedFiles = $this->copySourceFiles($namespace);
        $this->output->writeln(" Copying source files... <info>{$copiedFiles} files</info>");

        $copiedStubs = $this->copyStubs($namespace);
        $this->output->writeln(" Copying stubs... <info>{$copiedStubs} files</info>");

        $this->generateServiceProvider($namespace);
        $this->output->writeln(' Generating ServiceProvider... <info>done</info>');

        $updatedFiles = $this->updateUserCode($namespace);
        $this->output->writeln(" Updating existing classes... <info>{$updatedFiles} files updated</info>");

        $configStatus = $this->ensureConfigPublished();
        $this->output->writeln(" Publishing config... <info>{$configStatus}</info>");

        $this->output->writeln('');
        $this->output->writeln(' <options=bold>Next steps:</>');
        $this->output->writeln(' 1. Register the provider in bootstrap/providers.php:');
        $this->output->writeln("    <comment>{$namespace}\\BrainServiceProvider::class</comment>");
        $this->output->writeln(' 2. Remove the package:');
        $this->output->writeln('    <comment>composer remove r2luna/brain</comment>');
        $autoload = 'dump-autoload'; // @laradumps-ignore
        $this->output->writeln(" 3. Run: <comment>composer {$autoload}</comment>");
        $this->output->writeln('');

        return self::SUCCESS;
    }

    /**
     * Prompt the user for the target namespace or use the provided option.
     */
    private function askNamespace(): string
    {
        $namespace = $this->option('namespace');

        if ($namespace !== null && $namespace !== '') {
            return $namespace;
        }

        return text(
            label: 'What namespace should the ejected files use?',
            default: 'App\\Brain',
            required: true,
        );
    }

    /**
     * Copy all PHP source files to the target directory with namespace replacement.
     */
    private function copySourceFiles(string $namespace): int
    {
        $sourcePath = dirname(__DIR__);
        $targetBase = base_path($this->targetPath($namespace));
        $count = 0;

        $files = File::allFiles($sourcePath);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();

            if ($file->getExtension() !== 'php') {
                continue;
            }

            if ($this->isExcluded($relativePath)) {
                continue;
            }

            $content = $file->getContents();
            $content = $this->swapBrainNamespace($content, $namespace);

            $targetFile = $targetBase.DIRECTORY_SEPARATOR.$relativePath;

            File::ensureDirectoryExists(dirname($targetFile));
            File::put($targetFile, $content);

            $count++;
        }

        return $count;
    }

    /** Copy stub files to the target directory with namespace replacement. */
    private function copyStubs(string $namespace): int
    {
        $sourcePath = dirname(__DIR__);
        $targetBase = base_path($this->targetPath($namespace));
        $count = 0;

        $files = File::allFiles($sourcePath);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();

            if ($file->getExtension() !== 'stub') {
                continue;
            }

            // Skip the eject stubs themselves
            if (str_starts_with($relativePath, 'Console/stubs/eject/')) {
                continue;
            }

            $content = $file->getContents();
            $content = $this->swapStubNamespace($content, $namespace);

            $targetFile = $targetBase.DIRECTORY_SEPARATOR.$relativePath;

            File::ensureDirectoryExists(dirname($targetFile));
            File::put($targetFile, $content);

            $count++;
        }

        return $count;
    }

    /** Generate the ejected BrainServiceProvider from a stub. */
    private function generateServiceProvider(string $namespace): void
    {
        $stubPath = __DIR__.'/stubs/eject/provider.stub';
        $content = File::get($stubPath);

        $content = str_replace('{{ namespace }}', $namespace, $content);

        $targetBase = base_path($this->targetPath($namespace));
        $targetFile = $targetBase.DIRECTORY_SEPARATOR.'BrainServiceProvider.php';

        File::ensureDirectoryExists(dirname($targetFile));
        File::put($targetFile, $content);
    }

    /** Update existing user Brain classes to use the new namespace. */
    private function updateUserCode(string $namespace): int
    {
        $root = config('brain.root', 'Brain');
        $userPath = app_path($root);
        $count = 0;

        if (! File::isDirectory($userPath)) {
            return $count;
        }

        $files = File::allFiles($userPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = $file->getContents();
            $original = $content;

            $content = $this->swapBrainNamespace($content, $namespace);

            if ($content !== $original) {
                File::put($file->getPathname(), $content);
                $count++;
            }
        }

        return $count;
    }

    /** Publish the brain config file if it doesn't already exist. */
    private function ensureConfigPublished(): string
    {
        $configPath = config_path('brain.php');

        if (File::exists($configPath)) {
            return 'already exists';
        }

        $sourceConfig = dirname(__DIR__, 2).'/config/brain.php';
        File::copy($sourceConfig, $configPath);

        return 'done';
    }

    /** Resolve the directory path for the given namespace using PSR-4 mappings. */
    private function targetPath(string $namespace): string
    {
        $psr4Map = $this->composerPsr4Map();
        $namespaceForMatch = rtrim($namespace, '\\').'\\';

        $bestPrefix = '';
        $bestPath = '';

        foreach ($psr4Map as $prefix => $paths) {
            $path = is_array($paths) ? $paths[0] : $paths;

            if (str_starts_with($namespaceForMatch, $prefix) && strlen($prefix) > strlen($bestPrefix)) {
                $bestPrefix = $prefix;
                $bestPath = $path;
            }
        }

        if ($bestPrefix !== '') {
            $remaining = substr($namespace, strlen($bestPrefix));
            $remainingPath = str_replace('\\', '/', $remaining);
            $basePath = rtrim((string) $bestPath, '/');

            return $basePath.($remainingPath !== '' ? '/'.$remainingPath : '');
        }

        return str_replace('\\', '/', lcfirst($namespace));
    }

    /** Check if the given namespace has a matching PSR-4 mapping entry. */
    private function namespaceHasPsr4Mapping(string $namespace): bool
    {
        $psr4Map = $this->composerPsr4Map();
        $namespaceForMatch = rtrim($namespace, '\\').'\\';

        foreach (array_keys($psr4Map) as $prefix) {
            if (str_starts_with($namespaceForMatch, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Load and cache the PSR-4 mapping from composer.json. */
    private function composerPsr4Map(): array
    {
        if ($this->psr4Map !== null) {
            return $this->psr4Map;
        }

        $composerPath = base_path('composer.json');

        if (! File::exists($composerPath)) {
            return $this->psr4Map = [];
        }

        $composer = json_decode(File::get($composerPath), true);

        if (! is_array($composer)) {
            return $this->psr4Map = [];
        }

        return $this->psr4Map = array_merge(
            $composer['autoload-dev']['psr-4'] ?? [],
            $composer['autoload']['psr-4'] ?? [],
        );
    }

    /** Replace Brain namespace references with the target namespace in PHP content. */
    private function swapBrainNamespace(string $content, string $namespace): string
    {
        return str_replace(
            ['namespace Brain\\', 'namespace Brain;', 'use Brain\\', 'use Brain;'],
            ["namespace {$namespace}\\", "namespace {$namespace};", "use {$namespace}\\", "use {$namespace};"],
            $content
        );
    }

    /** Replace Brain namespace references in stub use-statements. */
    private function swapStubNamespace(string $content, string $namespace): string
    {
        return str_replace('use Brain\\', "use {$namespace}\\", $content);
    }

    /** Determine if the given relative path is in the exclusion list. */
    private function isExcluded(string $relativePath): bool
    {
        $normalized = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        return in_array($normalized, $this->excludedFiles, true);
    }
}
