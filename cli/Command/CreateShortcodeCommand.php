<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;
use WaspCli\Generator\ExtraFile;

#[AsCommand(
    name: 'create:shortcode',
    description: 'Creates a new Shortcode class file using project config'
)]
final class CreateShortcodeCommand extends AbstractCreateCommand
{
    protected function spec(): CreateSpec
    {
        return new CreateSpec(
            title: 'Create Shortcode',
            stub: 'shortcode',
            targetSubdir: 'classes/shortcode',
            fileInfix: 'shortcode',
            classPrefix: 'Shortcode_',
            namespaceSuffix: 'Shortcode',
            parentClass: 'Shortcode\\Shortcode',
            successLabel: 'Shortcode',
            nameArgumentDescription: 'Shortcode name (e.g., Photo Gallery)',
        );
    }

    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Shortcode tag: ' . $this->shortcodeTag($context)];
    }

    protected function extraReplacements(CreateContext $context): array
    {
        return [
            '{{SHORTCODE_TAG}}' => $this->shortcodeTag($context),
            '{{PAGE_SLUG}}' => $context->projectSlug,
        ];
    }

    protected function extraFiles(CreateContext $context): array
    {
        $tag = $this->shortcodeTag($context);

        return [
            new ExtraFile(
                stub: 'shortcode-template',
                destinationDir: $context->pluginBaseDir . '/templates/shortcodes',
                fileName: $tag . '.php',
                replacements: [
                    '{{SHORTCODE_TAG}}' => $tag,
                    '{{CLASS_NAME}}' => $context->className,
                    '{{PAGE_SLUG}}' => $context->projectSlug,
                ],
                ifExists: 'skip',
                label: 'Template',
            ),
        ];
    }

    private function shortcodeTag(CreateContext $context): string
    {
        return $context->projectSlug . '_' . str_replace('-', '_', $context->slug);
    }
}
