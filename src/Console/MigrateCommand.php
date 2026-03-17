<?php

declare(strict_types=1);

namespace Brain\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use SplFileInfo;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

/** Console command to migrate from Process/Task naming to Workflow/Action naming. */
class MigrateCommand extends Command
{
    /** @var string */
    protected $signature = 'brain:migrate
        {--dry-run : Preview changes without applying them}';

    /** @var string */
    protected $description = 'Migrate Brain classes from Process/Task to Workflow/Action naming';

    /** Exact string replacements to apply in PHP files. */
    private array $contentReplacements = [
        // Namespace segments (user classes living under Processes/Tasks directories)
        '\\Processes\\' => '\\Workflows\\',
        '\\Processes;' => '\\Workflows;',
        '\\Tasks\\' => '\\Actions\\',
        '\\Tasks;' => '\\Actions;',

        // Property: $tasks array in Process/Workflow classes
        'protected array $tasks = [' => 'protected array $actions = [',
    ];

    /** Case-insensitive regex replacements (pattern => replacement). */
    private array $regexReplacements = [
        '/use\s+Brain\\\\Process\s*;/i' => 'use Brain\\Workflow;',
        '/use\s+Brain\\\\Task\s*;/i' => 'use Brain\\Action;',
        '/extends\s+Process\b/i' => 'extends Workflow',
        '/extends\s+Task\b/i' => 'extends Action',
    ];

    /** Suffix renames: old suffix => new suffix. */
    private array $suffixRenames = [
        'Process' => 'Workflow',
        'Task' => 'Action',
    ];

    /** Directory renames to apply. */
    private array $directoryRenames = [
        'Processes' => 'Workflows',
        'Tasks' => 'Actions',
    ];

    public function handle(): int
    {
        $root = config('brain.root');
        $basePath = $root ? app_path($root) : app_path();

        if (! File::isDirectory($basePath)) {
            warning("Brain directory not found: {$basePath}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->output->writeln('');
        $this->output->writeln(' <options=bold>Brain Migration: Process/Task → Workflow/Action</>');
        $this->output->writeln(' ─────────────────────────────────────────────────');
        $this->output->writeln(" Directory: <info>{$basePath}</info>");

        if ($dryRun) {
            $this->output->writeln(' Mode: <comment>dry-run (no changes will be made)</comment>');
        }

        $this->output->writeln('');

        // Phase 1: Scan and report
        $filesToUpdate = $this->scanFiles($basePath);
        $directoriesToRename = $this->scanDirectories($basePath);
        $filesToRename = $this->scanFileRenames($basePath);

        $configNeedsUpdate = $this->configNeedsUpdate();

        if ($filesToUpdate === [] && $directoriesToRename === [] && $filesToRename === [] && ! $configNeedsUpdate) {
            info('Nothing to migrate. Your codebase already uses Workflow/Action naming.');

            return self::SUCCESS;
        }

        $this->printPreview($filesToUpdate, $directoriesToRename, $filesToRename, $configNeedsUpdate);

        if ($dryRun) {
            note('Dry-run complete. No changes were made.');

            return self::SUCCESS;
        }

        if (! confirm('Apply these changes?', true)) {
            note('Migration cancelled.');

            return self::SUCCESS;
        }

        $this->output->writeln('');

        // Phase 2: Update file contents (before renaming so paths still exist)
        $updatedFiles = $this->updateFiles($filesToUpdate);
        $this->output->writeln(" Updated file contents: <info>{$updatedFiles} files</info>");

        // Phase 3: Rename class files (suffixed names)
        $renamedFiles = $this->renameFiles($filesToRename, $basePath);
        $this->output->writeln(" Renamed files: <info>{$renamedFiles} files</info>");

        // Phase 4: Rename directories
        $renamedDirs = $this->renameDirectories($directoriesToRename);
        $this->output->writeln(" Renamed directories: <info>{$renamedDirs} directories</info>");

        // Phase 5: Update config/brain.php
        $configUpdated = $this->updateConfig();
        $this->output->writeln(" Updated config: <info>{$configUpdated}</info>");

        $this->output->writeln('');
        info('Migration complete!');

        return self::SUCCESS;
    }

    /**
     * Scan all PHP files and identify which ones need content updates.
     *
     * @return array<string, array{path: string, replacements: array<string, string>}>
     */
    private function scanFiles(string $basePath): array
    {
        $files = [];

        foreach (File::allFiles($basePath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = $file->getContents();
            $replacements = $this->findReplacements($content);

            if ($this->hasReplacements($replacements)) {
                $relativePath = $this->relativePath($basePath, $file);
                $files[$file->getPathname()] = [
                    'path' => $relativePath,
                    'replacements' => $replacements,
                ];
            }
        }

        return $files;
    }

    /**
     * Find which replacements apply to the given content.
     *
     * @return array{exact: array<string, string>, regex: array<string, string>}
     */
    private function findReplacements(string $content): array
    {
        $exact = [];

        foreach ($this->contentReplacements as $search => $replace) {
            if (str_contains($content, (string) $search)) {
                $exact[$search] = $replace;
            }
        }

        $regex = [];

        foreach ($this->regexReplacements as $pattern => $replace) {
            if (preg_match($pattern, $content)) {
                $regex[$pattern] = $replace;
            }
        }

        return ['exact' => $exact, 'regex' => $regex];
    }

    /** Check if any replacements were found. */
    private function hasReplacements(array $replacements): bool
    {
        return $replacements['exact'] !== [] || $replacements['regex'] !== [];
    }

    /**
     * Scan for files that need renaming (suffix-based: e.g. CreateOrderProcess.php → CreateOrderWorkflow.php).
     *
     * @return array<string, array{old: string, new: string, oldClass: string, newClass: string}>
     */
    private function scanFileRenames(string $basePath): array
    {
        $renames = [];

        foreach (File::allFiles($basePath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilenameWithoutExtension();

            foreach ($this->suffixRenames as $oldSuffix => $newSuffix) {
                if (str_ends_with($filename, (string) $oldSuffix) && $filename !== $oldSuffix) {
                    $newFilename = substr($filename, 0, -strlen((string) $oldSuffix)).$newSuffix;
                    $newPath = dirname($file->getPathname()).DIRECTORY_SEPARATOR.$newFilename.'.php';

                    if (! File::exists($newPath)) {
                        $renames[$file->getPathname()] = [
                            'old' => $this->relativePath($basePath, $file),
                            'new' => $this->relativePath($basePath, new SplFileInfo($newPath)),
                            'oldClass' => $filename,
                            'newClass' => $newFilename,
                        ];
                    }
                }
            }
        }

        return $renames;
    }

    /**
     * Scan for directories that need renaming.
     *
     * @return array<string, string> Old path => New path
     */
    private function scanDirectories(string $basePath): array
    {
        $directories = [];

        $this->findDirectoriesToRename($basePath, $directories);

        return $directories;
    }

    /** Recursively find directories that match rename patterns. */
    private function findDirectoriesToRename(string $path, array &$directories): void
    {
        if (! File::isDirectory($path)) {
            return;
        }

        foreach (File::directories($path) as $dir) {
            $basename = basename((string) $dir);

            if (isset($this->directoryRenames[$basename])) {
                $newPath = dirname((string) $dir).DIRECTORY_SEPARATOR.$this->directoryRenames[$basename];

                if (! File::isDirectory($newPath)) {
                    $directories[$dir] = $newPath;
                }
            }

            // Recurse into subdirectories (for domain structure)
            $this->findDirectoriesToRename($dir, $directories);
        }
    }

    /** Check if the published config/brain.php needs updating. */
    private function configNeedsUpdate(): bool
    {
        $configPath = config_path('brain.php');

        if (! File::exists($configPath)) {
            return false;
        }

        $content = File::get($configPath);

        return str_contains($content, "'process' => env('BRAIN_PROCESS_SUFFIX'")
            || str_contains($content, "'task' => env('BRAIN_TASK_SUFFIX'");
    }

    /** Print a preview of all planned changes. */
    private function printPreview(array $filesToUpdate, array $directoriesToRename, array $filesToRename, bool $configNeedsUpdate): void
    {
        if ($filesToUpdate !== []) {
            $this->output->writeln(' <options=bold>Files to update:</>');

            foreach ($filesToUpdate as $info) {
                $this->output->writeln("   • {$info['path']}");

                foreach ($info['replacements']['exact'] as $search => $replace) {
                    $this->output->writeln("     <comment>{$search}</comment> → <info>{$replace}</info>");
                }

                foreach ($info['replacements']['regex'] as $pattern => $replace) {
                    $this->output->writeln("     <comment>{$pattern}</comment> → <info>{$replace}</info>");
                }
            }

            $this->output->writeln('');
        }

        if ($filesToRename !== []) {
            $this->output->writeln(' <options=bold>Files to rename:</>');

            foreach ($filesToRename as $info) {
                $this->output->writeln("   • <comment>{$info['old']}</comment> → <info>{$info['new']}</info>");
            }

            $this->output->writeln('');
        }

        if ($directoriesToRename !== []) {
            $this->output->writeln(' <options=bold>Directories to rename:</>');

            foreach ($directoriesToRename as $oldPath => $newPath) {
                $this->output->writeln('   • <comment>'.basename((string) $oldPath).'</comment> → <info>'.basename((string) $newPath).'</info> in '.dirname((string) $oldPath));
            }

            $this->output->writeln('');
        }

        if ($configNeedsUpdate) {
            $this->output->writeln(' <options=bold>Config to update:</>');
            $this->output->writeln('   • <comment>config/brain.php</comment> → remove deprecated process/task suffix entries');
            $this->output->writeln('');
        }
    }

    /** Apply content replacements to all identified files. */
    private function updateFiles(array $filesToUpdate): int
    {
        $count = 0;

        foreach ($filesToUpdate as $filePath => $info) {
            $content = File::get($filePath);

            foreach ($info['replacements']['exact'] as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }

            foreach ($info['replacements']['regex'] as $pattern => $replace) {
                $content = preg_replace($pattern, (string) $replace, (string) $content);
            }

            File::put($filePath, $content);
            $count++;
        }

        return $count;
    }

    /** Rename files with old suffixes and update all class name references. */
    private function renameFiles(array $filesToRename, string $basePath): int
    {
        if ($filesToRename === []) {
            return 0;
        }

        // First, update all class name references across all PHP files
        $allFiles = File::allFiles($basePath);

        foreach ($allFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = File::get($file->getPathname());
            $original = $content;

            foreach ($filesToRename as $info) {
                $content = str_replace($info['oldClass'], $info['newClass'], $content);
            }

            if ($content !== $original) {
                File::put($file->getPathname(), $content);
            }
        }

        // Then rename the files themselves
        $count = 0;

        foreach ($filesToRename as $oldPath => $info) {
            if (File::exists($oldPath)) {
                $newPath = dirname((string) $oldPath).DIRECTORY_SEPARATOR.$info['newClass'].'.php';
                File::move($oldPath, $newPath);
                $count++;
            }
        }

        return $count;
    }

    /** Rename directories from old naming to new naming. */
    private function renameDirectories(array $directoriesToRename): int
    {
        // Sort by depth (deepest first) to avoid renaming parent before child
        uksort($directoriesToRename, fn (string $a, string $b): int => substr_count($b, DIRECTORY_SEPARATOR) - substr_count($a, DIRECTORY_SEPARATOR));

        $count = 0;

        foreach ($directoriesToRename as $oldPath => $newPath) {
            if (File::isDirectory($oldPath)) {
                File::moveDirectory($oldPath, $newPath);
                $count++;
            }
        }

        return $count;
    }

    /** Update the published config/brain.php to use Workflow/Action naming. */
    private function updateConfig(): string
    {
        $configPath = config_path('brain.php');

        if (! File::exists($configPath)) {
            return 'skipped (not published)';
        }

        $content = File::get($configPath);
        $original = $content;

        $content = str_replace(
            [
                'processes, tasks, and queries',
                'processes, tasks, and queries',
                "'process' => env('BRAIN_PROCESS_SUFFIX', 'Process'),",
                "'task' => env('BRAIN_TASK_SUFFIX', 'Task'),",
                "/** @deprecated Use 'workflow' instead. */",
                "/** @deprecated Use 'action' instead. */",
            ],
            [
                'workflows, actions, and queries',
                'workflows, actions, and queries',
                '',
                '',
                '',
                '',
            ],
            $content
        );

        // Clean up leftover blank lines from removed entries
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        if ($content === $original) {
            return 'already up to date';
        }

        File::put($configPath, $content);

        return 'done';
    }

    /** Get a relative path from base to file. */
    private function relativePath(string $basePath, SplFileInfo $file): string
    {
        return str_replace($basePath.DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}
