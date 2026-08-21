<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;

#[AsCommand(
    name: 'create:setting_fields',
    description: 'Creates a new Setting Fields class file using project config'
)]
final class CreateSettingFieldsCommand extends AbstractCreateCommand
{
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Create Setting Fields',
            stub: 'setting_fields',
            targetSubdir: 'classes/setting-fields',
            fileInfix: 'setting-fields',
            classPrefix: 'Setting_Fields_',
            namespaceSuffix: 'Setting_Fields',
            parentClass: 'Setting_Fields\\Setting_Fields',
            successLabel: 'Setting Fields',
            nameArgument: 'section',
            nameArgumentDescription: 'Section name (e.g., My Section Fields)',
        );
    }

    protected function configureExtraArguments(): void
    {
        $this
            ->addArgument('page_slug', InputArgument::REQUIRED, 'Settings page slug (e.g., wasp-dashboard-setting)')
            ->addOption('subpage', null, InputOption::VALUE_NONE, 'Flag to indicate that fields belong to a subpage.');
    }

    protected function extraInitialLines(CreateContext $context): array
    {
        return [
            'Page slug: ' . (string) $context->input->getArgument('page_slug'),
            'Subpage flag: ' . ($context->input->getOption('subpage') ? 'yes' : 'no'),
        ];
    }

    protected function extraReplacements(CreateContext $context): array
    {
        $subPrefix = $context->input->getOption('subpage') ? 'sub' : '';

        return [
            '{{PAGE_SLUG}}' => (string) $context->input->getArgument('page_slug'),
            '{{OPTION_GROUP}}' => "{$context->projectSlug}_{$subPrefix}setting",
            '{{OPTION_NAME}}' => "{$context->projectSlug}_{$subPrefix}options",
            '{{SECTION_ID}}' => $context->slug . '-section-id',
            '{{SECTION_TITLE}}' => $context->name,
            '{{FIELD_ID}}' => $context->slug . '-field-id',
            '{{FIELD_TITLE}}' => $context->name . ' fields',
            '{{FILTER}}' => $this->namer->filterKey($context->projectSlug . '-' . $context->slug),
        ];
    }
}
