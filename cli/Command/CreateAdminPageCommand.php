<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:admin_page',
    description: 'Creates a new Admin Page class file using project config'
)]
final class CreateAdminPageCommand extends AbstractCreateCommand
{
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Create Admin Page',
            stub: 'admin_page',
            targetSubdir: 'classes/admin-page',
            fileInfix: 'admin-page',
            classPrefix: 'Admin_Page_',
            namespaceSuffix: 'Admin',
            parentClass: 'Admin\\Admin_Page',
            successLabel: 'Admin Page',
            nameArgumentDescription: 'Admin Page name (e.g., My Plugin Dashboard)',
        );
    }

    protected function extraReplacements(CreateContext $context): array
    {
        return [
            '{{PAGE_TITLE}}' => $context->name . ' Admin Page',
            '{{MENU_TITLE}}' => $context->name,
            '{{PAGE_HEADING}}' => $context->name . ' Admin Page',
            '{{CAPABILITY}}' => 'manage_options',
            '{{MENU_SLUG}}' => $context->projectSlug . '-' . $context->slug . '-setting',
            '{{OPTION_GROUP}}' => $context->projectSlug . '_setting',
            '{{OPTION_NAME}}' => $context->projectSlug . '_options',
            '{{POSITION}}' => '2',
        ];
    }
}
