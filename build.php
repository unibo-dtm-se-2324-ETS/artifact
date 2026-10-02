<?php
// Build automation: packages the application into build/expense-tracker.zip.
// Usage: composer build   (or: php build.php)

$root = __DIR__;
$outDir = $root . '/build';
$zipPath = $outDir . '/expense-tracker.zip';

$excludedDirs = ['.git', '.github', '.phpunit.cache', '.tmp-reportrepo-sync', 'build', 'node_modules', 'tests', 'tmp', 'vendor'];
$excludedFiles = ['.gitignore', 'composer.lock', 'phpunit.xml', 'requirements-dev.txt'];
$excludedExtensions = ['pdf', 'pptx'];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "The PHP zip extension is required to build the package.\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0777, true)) {
    fwrite(STDERR, "Could not create $outDir\n");
    exit(1);
}
if (is_file($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create $zipPath\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($current) use ($root, $excludedDirs) {
            if ($current->isDir()) {
                $relative = substr($current->getPathname(), strlen($root) + 1);
                return !in_array(str_replace(DIRECTORY_SEPARATOR, '/', $relative), $excludedDirs, true);
            }
            return true;
        }
    )
);

$count = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
    $extension = strtolower($file->getExtension());
    if (in_array($relative, $excludedFiles, true) || in_array($extension, $excludedExtensions, true)) {
        continue;
    }
    $zip->addFile($file->getPathname(), $relative);
    $count++;
}
$zip->close();

printf("Built %s (%d files, %.1f KB)\n", $zipPath, $count, filesize($zipPath) / 1024);
