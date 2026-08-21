<?php

declare(strict_types=1);

namespace WaspCli\Generator;

final class ExtraFile
{
    /**
     * @param array<string, string> $replacements
     * @param 'fail'|'skip' $ifExists
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
