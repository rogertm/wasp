<?php

declare(strict_types=1);

namespace WaspCli\Generator;

final class CreateSpec
{
    /**
     * Describes how a create:* command generates a class from a stub.
     * @param string $title Title shown in the CLI output
     * @param string $stub Stub basename used for the main class file
     * @param string $targetSubdir Destination directory relative to the plugin root
     * @param string $fileInfix Token inserted into the generated filename
     * @param string $classPrefix Prefix of the generated PHP class name
     * @param string $namespaceSuffix Namespace segment after the project prefix
     * @param string $parentClass Parent class referenced in the generated use statement
     * @param string $successLabel Label used in the success message
     * @param string $nameArgument Console argument name that holds the human-readable name
     * @param string $nameArgumentDescription Help text for the name argument
     * @return void
     *
     * @since 1.0.0
     */
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
