<?php

declare(strict_types=1);

namespace WaspCli\Generator;

use RuntimeException;

final class LoaderRegistrar
{
    /**
     * Creates a registrar that appends class instantiations to a loader file.
     * @param bool $dryRun When true, the loader file is not modified
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly bool $dryRun,
    ) {
    }

    /**
     * Appends a line to the loader file when it is not already present.
     * @param string $loaderFile Absolute path to inc/classes.php
     * @param string $line Line to append, typically a new ClassName instantiation
     * @return bool|null True when appended, false when already present, null on dry-run
     *
     * @since 1.0.0
     */
    public function append(string $loaderFile, string $line): ?bool
    {
        if ($this->dryRun) {
            return null;
        }

        if (! is_file($loaderFile)) {
            throw new RuntimeException("Cannot register class. Loader not found: $loaderFile");
        }

        if (! is_writable($loaderFile)) {
            throw new RuntimeException("Cannot register class. Loader is not writable: $loaderFile");
        }

        $content = file_get_contents($loaderFile);
        if ($content === false) {
            throw new RuntimeException("Cannot read loader file: $loaderFile");
        }

        if (str_contains($content, $line)) {
            return false;
        }

        $prefix = '';
        if ($content !== '' && ! str_ends_with($content, "\n")) {
            $prefix = PHP_EOL;
        }

        $result = file_put_contents($loaderFile, $prefix . $line, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            throw new RuntimeException("Cannot write loader file: $loaderFile");
        }

        return true;
    }
}
