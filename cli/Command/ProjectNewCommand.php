<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

#[AsCommand(
    name: 'project:new',
    description: 'Creates a new child plugin that inherits from WASP'
)]
final class ProjectNewCommand extends AbstractGeneratorCommand
{
    /**
     * Registers the plugin name argument and the dry-run option.
     * @return void
     *
     * @since 1.0.0
     */
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the new plugin (e.g.: "WASP Child")')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If specified, only simulates creation without writing files.'
            );
    }

    /**
     * Creates a child plugin directory structure and stub files inheriting from WASP.
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     * @return int Command::SUCCESS or Command::FAILURE
     *
     * @since 1.0.0
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $this->bootWriters($dryRun);

        $this->io->title($this->decorate('🔌', 'Creating new child plugin'));

        if ($dryRun) {
            $this->io->warning('DRY-RUN mode: no files or folders will be created.');
        }

        $name = (string) $input->getArgument('name');
        try {
            $slug = $this->namer->slugify($name);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        $pluginDir = $this->baseDir . '/../' . $slug;

        $this->io->section('1) Preparing basic data');
        $this->io->text([
            "Plugin name: $name",
            "Slug (folder name): $slug",
            "Destination folder: $pluginDir",
        ]);

        if (is_dir($pluginDir)) {
            $this->io->error("The plugin directory already exists: $pluginDir");
            return Command::FAILURE;
        }

        $waspPluginDir = $this->baseDir . '/../' . $this->config->slug;
        if (! is_dir($waspPluginDir)) {
            $this->io->error('WASP plugin not found at: ' . $waspPluginDir);
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->io->text("DRY-RUN > mkdir $pluginDir");
        } else {
            try {
                $this->filesystem->mkdir($pluginDir, 0755);
                $this->io->text("Folder created: $pluginDir");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Error creating folder $pluginDir: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $this->io->section('2) Creating folder and file structure');

        $finder = new Finder();
        $finder
            ->directories()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->in($waspPluginDir . '/classes')
            ->exclude(['vendor', 'node_modules', '.git', 'helpers', 'interfaces']);

        foreach ($finder as $dir) {
            $relativePath = $dir->getRelativePathname();
            $newDir = $pluginDir . '/classes/' . $relativePath;

            if ($dryRun) {
                $this->io->text("DRY-RUN > mkdir $newDir");
                $this->io->text("DRY-RUN > createFileFromStub(index.php -> $newDir/index.php)");
                continue;
            }

            try {
                $this->fileGenerator->mkdir($newDir);
                $this->io->text("Folder: $newDir");
                $fullPath = $this->fileGenerator->writeFromStub('index', $newDir, 'index.php', [
                    '{{SLUG}}' => $slug,
                ]);
                $this->io->text("Stub created: $fullPath");
            } catch (\Throwable $e) {
                $this->io->error($e->getMessage());
                return Command::FAILURE;
            }
        }

        $incDir = $pluginDir . '/inc';
        $files = [
            ['inc.index', $incDir, 'index.php', ['{{SLUG}}' => $slug]],
            ['classes', $incDir, 'classes.php', ['{{SLUG}}' => $slug, '{{SLUG_PARENT}}' => $this->config->slug]],
            ['autoloader', $pluginDir, 'autoloader.php', ['{{SLUG}}' => $slug, '{{SLUG_PARENT}}' => $this->config->slug]],
            [
                'plugin',
                $pluginDir,
                $slug . '.php',
                [
                    '{{PLUGIN_NAME}}' => $name,
                    '{{SLUG}}' => $slug,
                    '{{SLUG_PARENT}}' => $this->config->slug,
                    '{{TEXT_DOMAIN}}' => $slug,
                    '{{AUTHOR}}' => 'RogerTM',
                    '{{AUTHOR_URI}}' => 'https://rogertm.com',
                    '{{PLUGIN_URI}}' => 'https://github.com/rogertm/wasp',
                    '{{VERSION}}' => '1.0.0',
                ],
            ],
        ];

        foreach ($files as [$stub, $dir, $fileName, $replacements]) {
            $dest = $dir . '/' . $fileName;
            if ($dryRun) {
                $this->io->text("DRY-RUN > createFileFromStub($stub -> $dest)");
                continue;
            }

            try {
                $fullPath = $this->fileGenerator->writeFromStub($stub, $dir, $fileName, $replacements);
                $this->io->text("Stub created: $fullPath");
            } catch (\Throwable $e) {
                $this->io->error($e->getMessage());
                return Command::FAILURE;
            }
        }

        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('DRY-RUN completed. No files were created.');
        } else {
            $this->io->success("Plugin \"$name\" successfully generated at: $pluginDir");
        }

        return Command::SUCCESS;
    }
}
