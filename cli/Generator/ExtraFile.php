<?php

declare(strict_types=1);

namespace WaspCli\Generator;

final class ExtraFile
{
    /**
     * Describes an additional stub-generated file to write after the main class.
     * @param string $stub Stub name without extension
     * @param string $destinationDir Directory where the file will be written
     * @param string $fileName Destination filename
     * @param array<string, string> $replacements Placeholder map applied to the stub
     * @param string $ifExists Behavior when the destination exists: fail or skip
     * @param string $label Human-readable label used in CLI messages
     * @return void
     *
     * @since 1.0.0
     */
    public function __construct(
        public readonly string $stub,
        public readonly string $destinationDir,
        public readonly string $fileName,
        public readonly array $replacements,
        public readonly string $ifExists = 'fail',
        public readonly string $label = 'File',
    ) {
    }
}
