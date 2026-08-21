<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:user_meta',
    description: 'Creates a new User Meta class file using project config'
)]
final class CreateUserMetaCommand extends AbstractCreateCommand
{
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Create User Meta',
            stub: 'user_meta',
            targetSubdir: 'classes/user-meta',
            fileInfix: 'user-meta',
            classPrefix: 'User_Meta_',
            namespaceSuffix: 'Users',
            parentClass: 'Users\\User_Meta',
            successLabel: 'User Meta',
            nameArgumentDescription: 'User Meta name (e.g., My Custom Fields)',
        );
    }

    protected function extraReplacements(CreateContext $context): array
    {
        return [
            '{{FILTER}}' => $this->namer->filterKey($context->projectSlug . '-' . $context->slug),
        ];
    }
}
