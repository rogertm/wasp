<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:admin_subpage',
    description: 'Creates a new Admin Subpage class file using project config'
)]
final class CreateAdminSubPageCommand extends AbstractCreateCommand
{
    /**
     * Returns the specification used to generate an Admin Subpage class.
     * @return CreateSpec Stub, paths, naming and labels for admin subpages
     *
     * @since 1.0.0
     */
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Create Admin Subpage',
            stub: 'admin_subpage',
            targetSubdir: 'classes/admin-page',
            fileInfix: 'admin-page',
            classPrefix: 'Admin_Page_',
            namespaceSuffix: 'Admin',
            parentClass: 'Admin\\Admin_Sub_Menu_Page',
            successLabel: 'Admin Subpage',
            nameArgumentDescription: 'Subpage name (e.g., My Plugin Subpage)',
        );
    }

    /**
     * Adds the required parent menu slug argument.
     * @return void
     *
     * @since 1.0.0
     */
    protected function configureExtraArguments(): void
    {
        $this->addArgument('parent_slug', InputArgument::REQUIRED, 'Parent menu slug (e.g., wasp-dashboard-setting)');
    }

    /**
     * Shows the parent menu slug in the initial-data output.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return string[] Display line with the parent slug
     *
     * @since 1.0.0
     */
    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Parent slug: ' . (string) $context->input->getArgument('parent_slug')];
    }

    /**
     * Supplies parent slug, menu and option placeholders for the subpage stub.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return array<string, string> Placeholder map for the admin subpage class
     *
     * @since 1.0.0
     */
    protected function extraReplacements(CreateContext $context): array
    {
        return [
            '{{PARENT_SLUG}}' => (string) $context->input->getArgument('parent_slug'),
            '{{PAGE_TITLE}}' => $context->name . ' Admin Page',
            '{{MENU_TITLE}}' => $context->name,
            '{{PAGE_HEADING}}' => $context->name . ' Submenu Dashboard',
            '{{CAPABILITY}}' => 'manage_options',
            '{{MENU_SLUG}}' => $context->projectSlug . '-' . $context->slug . '-subsetting',
            '{{OPTION_GROUP}}' => $context->projectSlug . '_subsetting',
            '{{OPTION_NAME}}' => $context->projectSlug . '_options',
        ];
    }
}
