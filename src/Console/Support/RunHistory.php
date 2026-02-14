<?php

declare(strict_types=1);

namespace Brain\Console\Support;

use Illuminate\Support\Facades\File;

/** Persists brain:run execution history to a JSON file. */
class RunHistory
{
    private const int MAX_ENTRIES = 50;

    public function __construct(
        private readonly string $path
    ) {}

    /** Create an instance using the default storage path. */
    public static function default(): self
    {
        return new self(storage_path('brain/run-history.json'));
    }

    /** Record a new run entry at the top of the list, enforcing the max limit. */
    public function record(array $target, array $payload, bool $sync, array $sensitiveKeys = []): void
    {
        $entries = $this->all();

        $entry = [
            'class' => $target['class'],
            'type' => $target['type'],
            'sync' => $sync,
            'payload' => $payload,
            'timestamp' => now()->format('Y-m-d H:i:s'),
        ];

        if ($sensitiveKeys !== []) {
            $entry['sensitiveKeys'] = array_values($sensitiveKeys);
        }

        array_unshift($entries, $entry);

        $entries = array_slice($entries, 0, self::MAX_ENTRIES);

        $this->save($entries);
    }

    /** Return all history entries, most recent first. */
    public function all(): array
    {
        if (! File::exists($this->path)) {
            return [];
        }

        $contents = File::get($this->path);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /** Write entries to the JSON file, ensuring the directory is git-ignored. */
    private function save(array $entries): void
    {
        $directory = dirname($this->path);

        File::ensureDirectoryExists($directory);

        $gitignore = $directory.'/.gitignore';

        if (! File::exists($gitignore)) {
            File::put($gitignore, "*\n!.gitignore\n");
        }

        File::put($this->path, json_encode($entries, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
