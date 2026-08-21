<?php

declare(strict_types=1);

namespace WaspCli\Generator;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class FileGenerator
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $stubsDir,
        private readonly bool $dryRun,
    ) {
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function mkdir(string $directory): void
    {
        if ($this->dryRun || is_dir($directory)) {
            return;
        }

        $this->filesystem->mkdir($directory, 0755);
    }

    /**
     * @param array<string, string> $replacements
     */
    public function writeFromStub(
        string $stubName,
        string $destinationDir,
        string $fileName,
        array $replacements
    ): string {
        $fullPath = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($fullPath)) {
            throw new RuntimeException("File already exists: $fullPath");
        }

        $content = $this->renderStub($stubName, $replacements);

        if ($this->dryRun) {
            return $fullPath;
        }

        $this->mkdir($destinationDir);

        $bytes = file_put_contents($fullPath, $content, LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException("Unable to write generated file: $fullPath");
        }

        return $fullPath;
    }

    /**
     * @param array<string, string> $replacements
     */
    public function renderStub(string $stubName, array $replacements): string
    {
        $stubPath = rtrim($this->stubsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $stubName . '.stub';
        if (! is_file($stubPath)) {
            throw new RuntimeException("Stub not found: $stubPath");
        }

        $stubContent = file_get_contents($stubPath);
        if ($stubContent === false) {
            throw new RuntimeException("Unable to read stub: $stubPath");
        }

        return str_replace(array_keys($replacements), array_values($replacements), $stubContent);
    }
}
