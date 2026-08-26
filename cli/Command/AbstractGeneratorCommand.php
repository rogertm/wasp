<?php

declare(strict_types=1);

namespace WaspCli\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use WaspCli\Config\ProjectConfig;
use WaspCli\Generator\FileGenerator;
use WaspCli\Generator\LoaderRegistrar;
use WaspCli\Naming\PhpNamer;

abstract class AbstractGeneratorCommand extends Command
{
    protected string $baseDir;

    protected string $pluginsRootDir;

    protected ProjectConfig $config;

    protected PhpNamer $namer;

    protected SymfonyStyle $io;

    protected Filesystem $filesystem;

    protected FileGenerator $fileGenerator;

    protected LoaderRegistrar $loaderRegistrar;

    /**
     * Resolves plugin paths, loads project config and prepares shared helpers.
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     * @return void
     *
     * @since 1.0.0
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);

        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new Filesystem();
        $this->namer = new PhpNamer();

        $baseDir = realpath(__DIR__ . '/../../');
        if ($baseDir === false) {
            throw new RuntimeException('Unable to resolve base directory.');
        }
        $this->baseDir = $baseDir;

        $pluginsRootDir = realpath($this->baseDir . '/..');
        if ($pluginsRootDir === false) {
            throw new RuntimeException('Unable to resolve plugins root directory.');
        }
        $this->pluginsRootDir = $pluginsRootDir;

        $this->config = ProjectConfig::fromFile($this->configPath(), $this->allowsMissingConfig());
    }

    /**
     * Returns the path to the JSON project configuration file.
     * @return string Absolute path to cli/config.json by default
     *
     * @since 1.0.0
     */
    protected function configPath(): string
    {
        return $this->baseDir . '/cli/config.json';
    }

    /**
     * Whether a missing config file should fall back to defaults instead of failing.
     * @return bool False by default
     *
     * @since 1.0.0
     */
    protected function allowsMissingConfig(): bool
    {
        return false;
    }

    /**
     * Instantiates the file generator and loader registrar for this run.
     * @param bool $dryRun When true, writers do not persist changes
     * @return void
     *
     * @since 1.0.0
     */
    protected function bootWriters(bool $dryRun): void
    {
        $this->fileGenerator = new FileGenerator(
            $this->filesystem,
            $this->baseDir . '/cli/stubs',
            $dryRun
        );
        $this->loaderRegistrar = new LoaderRegistrar($dryRun);
    }

    /**
     * Prefixes text with an emoji only when the output is decorated.
     * @param string $emoji Emoji shown when ANSI decoration is enabled
     * @param string $text Title or section text
     * @return string Decorated or plain text
     *
     * @since 1.0.0
     */
    protected function decorate(string $emoji, string $text): string
    {
        if ($this->io->isDecorated() && $emoji !== '') {
            return $emoji . ' ' . $text;
        }

        return $text;
    }

    /**
     * Resolves the target plugin directory, slug, namespace and text domain.
     * @param string|null $projectArg Optional child-plugin slug; null uses this plugin
     * @return array{plugin_base_dir:string,project_slug:string,namespace_prefix:string,text_domain:string} Paths and naming for the target plugin
     *
     * @since 1.0.0
     */
    protected function resolveProjectContext(?string $projectArg): array
    {
        if ($projectArg === null || $projectArg === '') {
            return [
                'plugin_base_dir' => $this->baseDir,
                'project_slug' => $this->config->slug,
                'namespace_prefix' => $this->config->namespace,
                'text_domain' => $this->config->textDomain,
            ];
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $projectArg)) {
            throw new RuntimeException(
                "Invalid project slug \"$projectArg\". Use lowercase letters, numbers and dashes only."
            );
        }

        $expectedPath = $this->baseDir . '/../' . $projectArg;
        $childDir = realpath($expectedPath);
        if (! $childDir || ! is_dir($childDir)) {
            throw new RuntimeException("Project not found: $projectArg (expected at $expectedPath)");
        }

        $pluginsRoot = rtrim($this->pluginsRootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolvedChildDir = rtrim($childDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (! str_starts_with($resolvedChildDir, $pluginsRoot)) {
            throw new RuntimeException("Project path is outside plugins root and is not allowed: $childDir");
        }

        return [
            'plugin_base_dir' => rtrim($childDir, DIRECTORY_SEPARATOR),
            'project_slug' => $projectArg,
            'namespace_prefix' => $this->namer->namespaceFromSlug($projectArg),
            'text_domain' => $projectArg,
        ];
    }
}
