<?php

declare(strict_types=1);

namespace WaspCli\Generator;

use Symfony\Component\Console\Input\InputInterface;

final class CreateContext
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $className,
        public readonly string $classSuffix,
        public readonly string $namespaceDecl,
        public readonly string $useDecl,
        public readonly string $pluginBaseDir,
        public readonly string $projectSlug,
        public readonly string $namespacePrefix,
        public readonly string $textDomain,
        public readonly InputInterface $input,
    ) {
    }
}
