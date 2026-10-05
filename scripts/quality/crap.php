<?php

declare(strict_types=1);

/**
 * Evaluates the CRAP (Change Risk Anti-Patterns) index produced by a coverage
 * run: CRAP = C² × (1 − coverage)³ + C, per method.
 *
 * Reads the crap4j XML that `composer test:unit` writes. This script only
 * judges the report, so it needs no coverage driver.
 *
 * Usage: php scripts/quality/crap.php [--threshold=30] [--exclude=Prefix ...] [report]
 */
const LISTED_OFFENDERS = 25;

$options = getopt('', ['threshold:', 'exclude:'], $restIndex);
$report = $argv[$restIndex] ?? dirname(__DIR__, 2).'/.phpunit.cache/crap4j.xml';
$threshold = (float) ($options['threshold'] ?? 30);
$prefixes = (array) ($options['exclude'] ?? []);

if (! is_file($report)) {
    printf("CRAP report not found at [%s].\nGenerate it first: composer test:unit\n", $report);

    exit(1);
}

$methods = parse($report);

if ($methods === []) {
    printf("No methods found in [%s]. The coverage run produced an empty report.\n", $report);

    exit(1);
}

$kept = array_values(array_filter(
    $methods,
    fn (array $method): bool => array_filter($prefixes, fn (string $prefix): bool => str_starts_with($method['class'], $prefix)) === [],
));
$excluded = count($methods) - count($kept);

if ($kept === []) {
    echo "Every method in the report was excluded. Check the --exclude prefixes.\n";

    exit(1);
}

$methods = $kept;

$offenders = array_values(array_filter($methods, fn (array $method): bool => $method['crap'] >= $threshold));

printf("Methods analysed: %d\n", count($methods));
printf("Above threshold:  %d (%.1f%%)\n", count($offenders), count($offenders) / count($methods) * 100);
printf("CRAP load:        %.0f\n", array_sum(array_column($methods, 'crapLoad')));

if ($excluded > 0) {
    printf("Excluded:         %d (%s)\n", $excluded, implode(', ', $prefixes));
}

if ($offenders === []) {
    printf("\nNo method scores %.0f or above.\n", $threshold);

    exit(0);
}

usort($offenders, fn (array $a, array $b): int => $b['crap'] <=> $a['crap']);

printf("\n%d method(s) at or above CRAP %.0f.\n\n", count($offenders), $threshold);
printf("%-80s %10s %8s %8s\n", 'Method', 'Complexity', 'Coverage', 'CRAP');

foreach (array_slice($offenders, 0, LISTED_OFFENDERS) as $method) {
    printf(
        "%-80s %10d %7.0f%% %8.1f\n",
        $method['class'].'::'.$method['method'],
        $method['complexity'],
        $method['coverage'],
        $method['crap'],
    );
}

if (count($offenders) > LISTED_OFFENDERS) {
    printf("... and %d more.\n", count($offenders) - LISTED_OFFENDERS);
}

exit(1);

/**
 * @return list<array{class: string, method: string, complexity: int, coverage: float, crap: float, crapLoad: float}>
 */
function parse(string $report): array
{
    // A truncated or malformed report must surface as this script's own
    // error, not as a wall of libxml warnings printed before it.
    $previous = libxml_use_internal_errors(true);

    $xml = simplexml_load_file($report);

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($xml === false) {
        return [];
    }

    $methods = [];

    foreach ($xml->methods->method as $method) {
        $methods[] = [
            'class' => (string) $method->className,
            'method' => (string) $method->methodName,
            'complexity' => (int) $method->complexity,
            'coverage' => (float) $method->coverage,
            'crap' => (float) $method->crap,
            'crapLoad' => (float) $method->crapLoad,
        ];
    }

    return $methods;
}
