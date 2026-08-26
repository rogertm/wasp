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
    /**
     * Returns the specification used to generate a Shortcode class.
     * @return CreateSpec Stub, paths, naming and labels for shortcodes
     *
     * @since 1.0.0
     */
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

    /**
     * Adds the computed shortcode tag to the initial-data output.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return string[] Display lines that include the shortcode tag
     *
     * @since 1.0.0
     */
    protected function extraInitialLines(CreateContext $context): array
    {
        return ['Shortcode tag: ' . $this->shortcodeTag($context)];
    }

    /**
     * Supplies shortcode tag and page slug placeholders for the class stub.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return array<string, string> Placeholder map for the shortcode class
     *
     * @since 1.0.0
     */
    protected function extraReplacements(CreateContext $context): array
    {
        return [
            '{{SHORTCODE_TAG}}' => $this->shortcodeTag($context),
            '{{PAGE_SLUG}}' => $context->projectSlug,
        ];
    }

    /**
     * Generates the frontend template file for the shortcode.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return ExtraFile[] Template file under templates/shortcodes
     *
     * @since 1.0.0
     */
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

    /**
     * Builds the WordPress shortcode tag from the project slug and class slug.
     * @param CreateContext $context Resolved naming and project data for this run
     * @return string Tag such as wasp_photo_gallery
     *
     * @since 1.0.0
     */
    private function shortcodeTag(CreateContext $context): string
    {
        return $context->projectSlug . '_' . str_replace('-', '_', $context->slug);
    }
}
