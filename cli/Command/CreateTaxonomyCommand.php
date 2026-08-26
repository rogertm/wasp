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
    /**
     * Returns the specification used to generate a Taxonomy class.
     * @return CreateSpec Stub, paths, naming and labels for taxonomies
     *
     * @since 1.0.0
     */
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

    /**
     * Adds the required object type argument associated with the taxonomy.
     * @return void
     *
     * @since 1.0.0
     */
    protected function configureExtraArguments(): void
    {
        $this->addArgument('object_type', InputArgument::REQUIRED, 'Object type associated (e.g.: wasp-book)');
    }

    /**
     * Shows the associated object type in the initial-data output.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return string[] Display line with the object type
     *
     * @since 1.0.0
     */
    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Object type: ' . (string) $context->input->getArgument('object_type')];
    }

    /**
     * Supplies the object type placeholder for the taxonomy stub.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return array<string, string> Placeholder map for the taxonomy class
     *
     * @since 1.0.0
     */
    protected function extraReplacements(CreateContext $context): array
    {
        return [
            '{{OBJECT_TYPE}}' => (string) $context->input->getArgument('object_type'),
        ];
    }
}
