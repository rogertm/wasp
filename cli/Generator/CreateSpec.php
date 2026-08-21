<?php

declare(strict_types=1);

namespace WaspCli\Generator;

final class CreateSpec
{
    public function __construct(
        public readonly string $title,
        public readonly string $stub,
        public readonly string $targetSubdir,
        public readonly string $fileInfix,
        public readonly string $classPrefix,
        public readonly string $namespaceSuffix,
        public readonly string $parentClass,
        public readonly string $successLabel,
        public readonly string $nameArgument = 'name',
        public readonly string $nameArgumentDescription = 'Name of the generated class',
    ) {
    }
}
