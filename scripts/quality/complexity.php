<?php

declare(strict_types=1);

/**
 * Fast, static half of the CRAP gate. Runs on staged files during pre-commit.
 *
 * CRAP = C² × (1 − coverage)³ + C, so complexity alone bounds the score: a
 * method with complexity C scores C even at 100% coverage, and C² + C with no
 * tests at all. This script never needs a coverage driver.
 *
 * Usage: php scripts/quality/complexity.php [--max=10] [--crap-threshold=30] [--staged] [files...]
 *
 * Without files it analyses everything under src/. With --staged it analyses
 * only the PHP files under src/ staged for commit.
 */

use SebastianBergmann\Complexity\Calculator;

require __DIR__.'/../../vendor/autoload.php';

$root = dirname(__DIR__, 2);

$options = getopt('', ['max:', 'crap-threshold:', 'staged'], $restIndex);
$max = (int) ($options['max'] ?? 10);
$threshold = (float) ($options['crap-threshold'] ?? 30);

$files = targetFiles($root, array_slice($argv, $restIndex), isset($options['staged']));

if ($files === []) {
    echo "No PHP files to analyse.\n";

    exit(0);
}

$offenders = [];

foreach ($files as $file) {
    foreach ((new Calculator)->calculateForSourceFile($file)->asArray() as $complexity) {
        if ($complexity->cyclomaticComplexity() <= $max) {
            continue;
        }

        $offenders[] = [$file, $complexity];
    }
}

if ($offenders === []) {
    printf("Cyclomatic complexity within limit (max %d) across %d file(s).\n", $max, count($files));

    exit(0);
}

usort($offenders, fn (array $a, array $b): int => $b[1]->cyclomaticComplexity() <=> $a[1]->cyclomaticComplexity());

printf("%d method(s) above the complexity limit of %d.\n\n", count($offenders), $max);
printf("%-70s %10s  %-28s %s\n", 'Method', 'Complexity', sprintf('Coverage needed for CRAP < %.0f', $threshold), 'File');

foreach ($offenders as [$file, $complexity]) {
    printf(
        "%-70s %10d  %-28s %s\n",
        $complexity->name(),
        $complexity->cyclomaticComplexity(),
        coverageNeeded($complexity->cyclomaticComplexity(), $threshold),
        $file,
    );
}

echo "\nSplit these methods up. Lowering complexity costs less than the coverage it would take to offset it.\n";

exit(1);

/**
 * @param  list<string>  $arguments
 * @return list<string>
 */
function targetFiles(string $root, array $arguments, bool $staged): array
{
    if ($staged) {
        exec('git diff --cached --name-only --diff-filter=ACMR -- '.escapeshellarg('src/*.php'), $arguments);
        $arguments = array_map(fn (string $file): string => $root.'/'.$file, $arguments);
    } elseif ($arguments === []) {
        $arguments = array_map(
            fn (SplFileInfo $file): string => $file->getPathname(),
            iterator_to_array(
                new RegexIterator(
                    new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src')),
                    '/\.php$/',
                ),
                false,
            ),
        );
    }

    return array_values(array_filter(
        $arguments,
        fn (string $file): bool => str_ends_with($file, '.php') && is_file($file) && is_readable($file),
    ));
}

/**
 * Coverage that would be required to keep this complexity under the CRAP
 * threshold. Past C = threshold the term C² × (1 − coverage)³ vanishes but
 * the trailing + C alone already breaches, so no amount of testing helps.
 */
function coverageNeeded(int $complexity, float $threshold): string
{
    if ($complexity >= $threshold) {
        return 'impossible, refactor';
    }

    $coverage = 1 - (($threshold - $complexity) / $complexity ** 2) ** (1 / 3);

    return $coverage <= 0
        ? 'none'
        : sprintf('%.0f%%', $coverage * 100);
}
