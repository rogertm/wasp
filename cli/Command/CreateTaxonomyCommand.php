<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:taxonomy',
    description: 'Creates a new Taxonomy class using stubs and the project configuration'
)]
final class CreateTaxonomyCommand extends AbstractCreateCommand
{
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Taxonomy Creation',
            stub: 'taxonomy',
            targetSubdir: 'classes/taxonomy',
            fileInfix: 'taxonomy',
            classPrefix: 'Taxonomy_',
            namespaceSuffix: 'Taxonomy',
            parentClass: 'Taxonomy\\Taxonomy',
            successLabel: 'Taxonomy',
            nameArgumentDescription: 'Name of the Taxonomy (e.g.: Genre)',
        );
    }

    protected function configureExtraArguments(): void
    {
        $this->addArgument('object_type', InputArgument::REQUIRED, 'Object type associated (e.g.: wasp-book)');
    }

    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Object type: ' . (string) $context->input->getArgument('object_type')];
    }

    protected function extraReplacements(CreateContext $context): array
    {
        return [
            '{{OBJECT_TYPE}}' => (string) $context->input->getArgument('object_type'),
        ];
    }
}
