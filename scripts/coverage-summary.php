<?php
// Prints a Markdown coverage table from a Clover XML report.
// Usage: php scripts/coverage-summary.php [clover.xml] [--min=80]
// With --min, the script exits with status 1 when the statement coverage is below that percentage.

$minimum = null;
$args = array();
foreach (array_slice($argv, 1) as $arg) {
    if (strpos($arg, '--min=') === 0) {
        $minimum = (float) substr($arg, 6);
    } else {
        $args[] = $arg;
    }
}

$path = $args[0] ?? 'build/coverage/clover.xml';
if (!is_file($path)) {
    fwrite(STDERR, "Coverage report not found: $path\n");
    exit(1);
}

$xml = simplexml_load_file($path);
$percent = static fn (int $covered, int $total): string => $total === 0 ? '100.0%' : sprintf('%.1f%%', 100 * $covered / $total);

echo "### Code coverage\n\n";
echo "| File | Statements | Methods | Covered statements |\n";
echo "|---|---|---|---|\n";

foreach ($xml->xpath('//file') as $file) {
    $m = $file->metrics;
    $name = str_replace(DIRECTORY_SEPARATOR, '/', (string) $file['name']);
    $name = preg_replace('#^.*/(src|config|templates|public)/#', '$1/', $name);
    printf(
        "| `%s` | %s | %s | %s |\n",
        $name,
        $percent((int) $m['coveredstatements'], (int) $m['statements']),
        $percent((int) $m['coveredmethods'], (int) $m['methods']),
        sprintf('%d/%d', (int) $m['coveredstatements'], (int) $m['statements'])
    );
}

$t = $xml->project->metrics;
printf(
    "| **Total** | **%s** | **%s** | **%d/%d** |\n",
    $percent((int) $t['coveredstatements'], (int) $t['statements']),
    $percent((int) $t['coveredmethods'], (int) $t['methods']),
    (int) $t['coveredstatements'],
    (int) $t['statements']
);

if ($minimum !== null) {
    $total = (int) $t['statements'];
    $actual = $total === 0 ? 100.0 : 100 * (int) $t['coveredstatements'] / $total;
    if ($actual < $minimum) {
        fwrite(STDERR, sprintf("Coverage %.1f%% is below the required minimum of %.1f%%.
", $actual, $minimum));
        exit(1);
    }
}
