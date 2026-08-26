<?php

declare(strict_types=1);

namespace WaspCli\Generator;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class FileGenerator
{
    /**
     * Creates a generator bound to a stubs directory and dry-run flag.
     * @param Filesystem $filesystem Symfony filesystem helper
     * @param string $stubsDir Absolute path to the CLI stubs directory
     * @param bool $dryRun When true, directories and files are not written
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $stubsDir,
        private readonly bool $dryRun,
    ) {
    }

    /**
     * Reports whether this generator is in dry-run mode.
     * @return bool True when no files should be written
     *
     * @since 1.0.0
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * Creates a directory unless it already exists or dry-run is enabled.
     * @param string $directory Absolute path to create
     * @return void
     *
     * @since 1.0.0
     */
    public function mkdir(string $directory): void
    {
        if ($this->dryRun || is_dir($directory)) {
            return;
        }

        $this->filesystem->mkdir($directory, 0755);
    }

    /**
     * Renders a stub and writes it to disk unless dry-run is enabled.
     * @param string $stubName Stub basename without .stub
     * @param string $destinationDir Directory that will contain the generated file
     * @param string $fileName Destination filename
     * @param array<string, string> $replacements Placeholder map applied to the stub
     * @return string Absolute path of the file that was or would be created
     *
     * @since 1.0.0
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
     * Loads a stub file and applies placeholder replacements.
     * @param string $stubName Stub basename without .stub
     * @param array<string, string> $replacements Placeholder map applied to the stub
     * @return string Rendered file contents
     *
     * @since 1.0.0
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
