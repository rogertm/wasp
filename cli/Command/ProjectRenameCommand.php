<?php

declare(strict_types=1);

namespace WaspCli\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use WaspCli\Config\ProjectConfig;

#[AsCommand(
    name: 'project:rename',
    description: 'Rename strings and files in this plugin using the existing configuration'
)]
final class ProjectRenameCommand extends AbstractGeneratorCommand
{
    private string $requestedConfigPath = 'cli/config.json';

    protected function configure(): void
    {
        $this
            ->addArgument(
                'project_name',
                InputArgument::REQUIRED,
                'New project name (e.g. "My Custom Plugin")'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'If specified, only shows which files would be changed without modifying anything.'
            )
            ->addOption(
                'backup',
                null,
                InputOption::VALUE_NONE,
                'Generate a backup in backup/{timestamp}/ before renaming.'
            )
            ->addOption(
                'config',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to JSON config file (default: cli/config.json)',
                'cli/config.json'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $configOption = $input->getOption('config');
        if (is_string($configOption) && $configOption !== '') {
            $this->requestedConfigPath = $configOption;
        }

        parent::initialize($input, $output);
    }

    protected function configPath(): string
    {
        $path = $this->requestedConfigPath;
        if ($this->filesystem->isAbsolutePath($path)) {
            return $path;
        }

        return $this->baseDir . '/' . ltrim($path, '/');
    }

    protected function allowsMissingConfig(): bool
    {
        return true;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $this->bootWriters($dryRun);

        $this->io->title($this->decorate('🚀', 'Project Rename'));
        $configPath = $this->configPath();

        $this->io->section('1) Loading configuration');
        $this->io->text(
            is_file($configPath)
                ? "Configuration loaded from: $configPath"
                : "No configuration found at $configPath. Using default values."
        );

        $old = $this->config;
        $projectName = (string) $input->getArgument('project_name');

        $this->io->section('2) Calculating new values');
        try {
            $newSlug = $this->namer->slugify($projectName);
        } catch (RuntimeException $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        $new = new ProjectConfig(
            $this->namer->namespaceFromProjectName($projectName),
            $newSlug,
            $this->namer->functionPrefixFromSlug($newSlug),
            $newSlug
        );

        $this->io->text([
            "Old Namespace: $old->namespace",
            "New Namespace: $new->namespace",
            "Old Slug: $old->slug",
            "New Slug: $new->slug",
            "Old Prefix: $old->functionPrefix",
            "New Prefix: $new->functionPrefix",
            "Old Text domain: $old->textDomain",
            "New Text domain: $new->textDomain",
        ]);

        if ($dryRun) {
            $this->io->warning('DRY-RUN mode: no files will be modified.');
        }

        if ($input->getOption('backup')) {
            $this->io->section('3) Creating backup');
            $timestamp = date('Ymd_His');
            $backupDir = $this->baseDir . '/backup/' . $timestamp;
            $iterator = Finder::create()
                ->ignoreDotFiles(false)
                ->ignoreVCS(true)
                ->exclude('backup')
                ->in($this->baseDir);

            if ($dryRun) {
                $this->io->text("DRY-RUN > Backup would be created at: $backupDir");
            } else {
                try {
                    $this->filesystem->mirror($this->baseDir, $backupDir, $iterator);
                    $this->io->success("Backup created at: $backupDir");
                } catch (IOExceptionInterface $e) {
                    $this->io->error("Error creating backup at $backupDir: " . $e->getMessage());
                    return Command::FAILURE;
                }
            }
        }

        $this->io->section('4) Processing files (php, js, css)');
        $replacementMap = [
            $old->namespace . '\\' => $new->namespace . '\\',
            $old->namespace => $projectName,
            $old->functionPrefix => $new->functionPrefix,
            "'$old->textDomain'" => "'$new->textDomain'",
            "\"$old->textDomain\"" => "\"$new->textDomain\"",
            $old->slug . '-' => $new->slug . '-',
            $old->slug => $new->slug,
        ];
        $searches = array_keys($replacementMap);

        $finder = new Finder();
        $finder
            ->files()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->exclude(['vendor', 'node_modules', 'backup', 'cli', '.git'])
            ->in($this->baseDir)
            ->name('*.php')
            ->name('*.js')
            ->name('*.css');

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            $needsChange = false;
            foreach ($searches as $search) {
                if (stripos($content, $search) !== false) {
                    $needsChange = true;
                    break;
                }
            }
            if (! $needsChange) {
                continue;
            }

            $newContent = strtr($content, $replacementMap);
            if ($newContent === $content) {
                continue;
            }

            if ($dryRun) {
                $this->io->text("DRY-RUN > Modify content: $filePath");
                continue;
            }

            try {
                $written = file_put_contents($filePath, $newContent, LOCK_EX);
                if ($written === false) {
                    throw new RuntimeException("Cannot write file: $filePath");
                }
                $this->io->text("Processed: $filePath");
            } catch (\Throwable $e) {
                $this->io->error("Error writing $filePath: " . $e->getMessage());
            }
        }

        $classesPath = $this->baseDir . '/classes';
        if (is_dir($classesPath)) {
            $this->io->section('5) Renaming files in /classes');
            $finderClasses = new Finder();
            $finderClasses
                ->files()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->exclude(['vendor', 'node_modules', '.git'])
                ->in($classesPath)
                ->name('*' . $old->slug . '*');

            foreach ($finderClasses as $file) {
                $oldName = $file->getFilename();
                $oldFull = $file->getRealPath();
                if ($oldFull === false) {
                    continue;
                }

                $newName = $this->namer->replaceSlugInFilename($oldName, $old->slug, $new->slug);
                $newFull = $file->getPath() . DIRECTORY_SEPARATOR . $newName;
                if ($oldFull === $newFull) {
                    continue;
                }

                if ($dryRun) {
                    $this->io->text("DRY-RUN > Rename: $oldFull -> $newFull");
                    continue;
                }

                try {
                    $this->filesystem->rename($oldFull, $newFull);
                    $this->io->text("Renamed: $oldFull -> $newFull");
                } catch (IOExceptionInterface $e) {
                    $this->io->error("Error renaming $oldFull: " . $e->getMessage());
                }
            }
        }

        $this->io->section('6) Renaming root files');
        $finderRoot = new Finder();
        $finderRoot
            ->files()
            ->in($this->baseDir)
            ->depth('== 0')
            ->name('*.php');

        foreach ($finderRoot as $file) {
            $oldName = $file->getFilename();
            if (stripos($oldName, $old->slug) === false) {
                continue;
            }

            $oldFull = $file->getRealPath();
            if ($oldFull === false) {
                continue;
            }

            $newName = $this->namer->replaceSlugInFilename($oldName, $old->slug, $new->slug);
            $newFull = $this->baseDir . DIRECTORY_SEPARATOR . $newName;
            if ($oldFull === $newFull) {
                continue;
            }

            if ($dryRun) {
                $this->io->text("DRY-RUN > Rename root: $oldFull -> $newFull");
                continue;
            }

            try {
                $this->filesystem->rename($oldFull, $newFull);
                $this->io->text("Root renamed: $oldFull -> $newFull");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Error renaming root $oldFull: " . $e->getMessage());
            }
        }

        $this->io->section('7) Updating config.json');
        $newConfigJson = $new->toJson();

        if ($dryRun) {
            $this->io->text("DRY-RUN > Write config: $configPath");
        } else {
            $configChanged = true;
            if (is_file($configPath)) {
                $oldConfigJson = file_get_contents($configPath);
                $configChanged = $oldConfigJson === false || trim($oldConfigJson) !== trim($newConfigJson);
            }

            if ($configChanged) {
                try {
                    $new->writeTo($configPath);
                    $this->io->success("Config updated: $configPath");
                } catch (\Throwable $e) {
                    $this->io->error("Error writing $configPath: " . $e->getMessage());
                }
            } else {
                $this->io->warning('config.json is already up to date. No changes made.');
            }
        }

        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('DRY-RUN completed. No files were modified.');
        } else {
            $this->io->success('Renaming completed successfully.');
        }

        return Command::SUCCESS;
    }
}
