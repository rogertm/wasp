<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:term_meta',
    description: 'Creates a new Term Meta class file using project config'
)]
final class CreateTermMetaCommand extends AbstractCreateCommand
{
    /**
     * Returns the specification used to generate a Term Meta class.
     * @return CreateSpec Stub, paths, naming and labels for term meta
     *
     * @since 1.0.0
     */
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Create Term Meta',
            stub: 'term_meta',
            targetSubdir: 'classes/term-meta',
            fileInfix: 'term-meta',
            classPrefix: 'Term_Meta_',
            namespaceSuffix: 'Terms',
            parentClass: 'Terms\\Term_Meta',
            successLabel: 'Term Meta',
            nameArgumentDescription: 'Term Meta name (e.g., My Custom Fields)',
        );
    }

    /**
     * Adds the required taxonomy slug argument associated with the term meta.
     * @return void
     *
     * @since 1.0.0
     */
    protected function configureExtraArguments(): void
    {
        $this->addArgument('taxonomy', InputArgument::REQUIRED, 'The taxonomy slug to associate with (e.g., wasp-genre)');
    }

    /**
     * Shows the associated taxonomy slug in the initial-data output.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return string[] Display line with the taxonomy slug
     *
     * @since 1.0.0
     */
    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Taxonomy slug: ' . (string) $context->input->getArgument('taxonomy')];
    }

    /**
     * Supplies taxonomy and filter placeholders for the term meta stub.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return array<string, string> Placeholder map for the term meta class
     *
     * @since 1.0.0
     */
    protected function extraReplacements(CreateContext $context): array
    {
        $slugFull = $context->projectSlug . '-' . $context->slug;

        return [
            '{{TAXONOMY}}' => (string) $context->input->getArgument('taxonomy'),
            '{{FILTER}}' => $this->namer->filterKey($slugFull),
        ];
    }
}
