<?php

declare(strict_types=1);

namespace WaspCli\Generator;

use Symfony\Component\Console\Input\InputInterface;

final class CreateContext
{
    /**
     * Holds resolved naming and project data for a create:* command run.
     * @param string $name Human-readable name supplied by the user
     * @param string $slug Slug derived from the name
     * @param string $className Fully built generated class name
     * @param string $classSuffix Class-name suffix derived from the slug
     * @param string $namespaceDecl Namespace declared in the generated file
     * @param string $useDecl Parent class imported by the generated file
     * @param string $pluginBaseDir Absolute path of the target plugin
     * @param string $projectSlug Slug of the target plugin
     * @param string $namespacePrefix PHP namespace prefix of the target plugin
     * @param string $textDomain Text domain used in generated strings
     * @param InputInterface $input Console input for extra arguments and options
     * @return void
     *
     * @since 1.0.0
     */
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
