<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:meta_box',
    description: 'Creates a new Meta Box class using stubs and project configuration'
)]
final class CreateMetaBoxCommand extends AbstractCreateCommand
{
    /**
     * Returns the specification used to generate a Meta Box class.
     * @return CreateSpec Stub, paths, naming and labels for meta boxes
     *
     * @since 1.0.0
     */
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Meta Box Creation',
            stub: 'meta_box',
            targetSubdir: 'classes/meta-box',
            fileInfix: 'meta-box',
            classPrefix: 'Meta_Box_',
            namespaceSuffix: 'Meta_Box',
            parentClass: 'Meta_Box\\Meta_Box',
            successLabel: 'Meta Box',
            nameArgumentDescription: 'Name of the Meta Box (e.g.: My Custom Fields)',
        );
    }

    /**
     * Adds the required screen argument where the meta box will appear.
     * @return void
     *
     * @since 1.0.0
     */
    protected function configureExtraArguments(): void
    {
        $this->addArgument('screen', InputArgument::REQUIRED, 'Screen where it will appear (e.g.: wasp-book)');
    }

    /**
     * Shows the target screen in the initial-data output.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return string[] Display line with the screen
     *
     * @since 1.0.0
     */
    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Screen: ' . (string) $context->input->getArgument('screen')];
    }

    /**
     * Supplies screen and filter placeholders for the meta box stub.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return array<string, string> Placeholder map for the meta box class
     *
     * @since 1.0.0
     */
    protected function extraReplacements(CreateContext $context): array
    {
        $slugFull = $context->projectSlug . '-' . $context->slug;

        return [
            '{{SCREEN}}' => (string) $context->input->getArgument('screen'),
            '{{FILTER}}' => $this->namer->filterKey($slugFull),
        ];
    }
}
