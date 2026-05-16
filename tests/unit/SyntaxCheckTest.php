<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SyntaxCheckTest extends TestCase
{
    #[DataProvider('phpFileProvider')]
    public function testPhpFileHasNoSyntaxErrors(string $file): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
        exec($command, $output, $exitCode);

        $this->assertSame(
            0,
            $exitCode,
            implode(PHP_EOL, $output)
        );
    }

    public static function phpFileProvider(): array
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            glob($root . DIRECTORY_SEPARATOR . '*.php') ?: array(),
            glob($root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . '*.php') ?: array()
        );

        sort($files);

        return array_map(
            static fn (string $file): array => array($file),
            $files
        );
    }
}
