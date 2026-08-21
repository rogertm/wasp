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

    protected function configureExtraArguments(): void
    {
        $this->addArgument('screen', InputArgument::REQUIRED, 'Screen where it will appear (e.g.: wasp-book)');
    }

    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Screen: ' . (string) $context->input->getArgument('screen')];
    }

    protected function extraReplacements(CreateContext $context): array
    {
        $slugFull = $context->projectSlug . '-' . $context->slug;

        return [
            '{{SCREEN}}' => (string) $context->input->getArgument('screen'),
            '{{FILTER}}' => $this->namer->filterKey($slugFull),
        ];
    }
}
