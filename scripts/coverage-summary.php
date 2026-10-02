<?php
// Prints a Markdown coverage table from a Clover XML report.
// Usage: php scripts/coverage-summary.php build/coverage/clover.xml

$path = $argv[1] ?? 'build/coverage/clover.xml';
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
