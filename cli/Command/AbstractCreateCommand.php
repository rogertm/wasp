<?php

declare(strict_types=1);

namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WaspCli\Generator\CreateContext;
use WaspCli\Generator\CreateSpec;
use WaspCli\Generator\ExtraFile;

abstract class AbstractCreateCommand extends AbstractGeneratorCommand
{
    abstract protected function spec(): CreateSpec;

    protected function configure(): void
    {
        $spec = $this->spec();

        $this
            ->addArgument($spec->nameArgument, InputArgument::REQUIRED, $spec->nameArgumentDescription)
            ->addOption(
                'project',
                null,
                InputOption::VALUE_REQUIRED,
                'Project slug where the class will be created (e.g. wasp-child). Defaults to this plugin.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulate creation without writing files.'
            );

        $this->configureExtraArguments();
    }

    protected function configureExtraArguments(): void
    {
    }

    /**
     * @return array<string, string>
     */
    protected function extraReplacements(CreateContext $context): array
    {
        return [];
    }

    /**
     * @return ExtraFile[]
     */
    protected function extraFiles(CreateContext $context): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    protected function extraInitialLines(CreateContext $context): array
    {
        return [];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $spec = $this->spec();
        $dryRun = (bool) $input->getOption('dry-run');
        $this->bootWriters($dryRun);

        $this->io->title($this->decorate('📦', $spec->title));

        if ($dryRun) {
            $this->io->warning('DRY-RUN mode: no files will be created.');
        }

        $name = (string) $input->getArgument($spec->nameArgument);
        $projectOption = $input->getOption('project');
        $projectArg = is_string($projectOption) && $projectOption !== '' ? $projectOption : null;

        try {
            $resolved = $this->resolveProjectContext($projectArg);
            $slug = $this->namer->slugify($name);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        $classSuffix = $this->namer->classSuffixFromSlug($slug);
        $className = $spec->classPrefix . $classSuffix;
        $namespaceDecl = $resolved['namespace_prefix'] . '\\' . $spec->namespaceSuffix;
        $useDecl = $this->config->namespace . '\\' . $spec->parentClass;

        $context = new CreateContext(
            $name,
            $slug,
            $className,
            $classSuffix,
            $namespaceDecl,
            $useDecl,
            $resolved['plugin_base_dir'],
            $resolved['project_slug'],
            $resolved['namespace_prefix'],
            $resolved['text_domain'],
            $input
        );

        $this->io->section('1) Initial data');
        $this->io->text(array_merge(
            [
                sprintf('%s: %s', ucfirst(str_replace('_', ' ', $spec->nameArgument)), $name),
                'Project: ' . ($projectArg ?: $this->config->slug . ' (default)'),
                'Plugin directory: ' . $context->pluginBaseDir,
            ],
            $this->extraInitialLines($context)
        ));

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Slug: $slug",
            "Class name: $className",
            "Namespace: $namespaceDecl",
            "Extends (use): $useDecl",
        ]);

        $targetDir = $context->pluginBaseDir . '/' . $spec->targetSubdir;
        $fileName = sprintf('class-%s-%s-%s.php', $context->projectSlug, $spec->fileInfix, $slug);
        $replacements = array_merge(
            [
                '{{NAMESPACE_DECL}}' => $namespaceDecl,
                '{{USE_DECL}}' => $useDecl,
                '{{CLASS_NAME}}' => $className,
                '{{SLUG_FULL}}' => $context->projectSlug . '-' . $slug,
                '{{NAME}}' => $name,
                '{{TEXT_DOMAIN}}' => $context->textDomain,
            ],
            $this->extraReplacements($context)
        );

        $this->io->section('3) Generating class from stub');
        try {
            $createdPath = $this->fileGenerator->writeFromStub(
                $spec->stub,
                $targetDir,
                $fileName,
                $replacements
            );
            if ($dryRun) {
                $this->io->text("DRY-RUN > createFileFromStub({$spec->stub} -> $createdPath)");
            } else {
                $this->io->success("Class created at: $createdPath");
            }
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        $extraFiles = $this->extraFiles($context);
        if ($extraFiles !== []) {
            $this->io->section('4) Generating extra files');
            foreach ($extraFiles as $extraFile) {
                $fullPath = rtrim($extraFile->destinationDir, DIRECTORY_SEPARATOR)
                    . DIRECTORY_SEPARATOR
                    . $extraFile->fileName;

                if (is_file($fullPath) && $extraFile->ifExists === 'skip') {
                    $this->io->warning("{$extraFile->label} already exists: $fullPath");
                    continue;
                }

                try {
                    $createdExtra = $this->fileGenerator->writeFromStub(
                        $extraFile->stub,
                        $extraFile->destinationDir,
                        $extraFile->fileName,
                        $extraFile->replacements
                    );
                    if ($dryRun) {
                        $this->io->text("DRY-RUN > createFileFromStub({$extraFile->stub} -> $createdExtra)");
                    } else {
                        $this->io->success("{$extraFile->label} created at: $createdExtra");
                    }
                } catch (\Throwable $e) {
                    $this->io->error($e->getMessage());
                    return Command::FAILURE;
                }
            }
        }

        $this->io->section($extraFiles === [] ? '4) Registering in inc/classes.php' : '5) Registering in inc/classes.php');
        $loaderFile = $context->pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\%s\\%s;\n",
            $context->namespacePrefix,
            $spec->namespaceSuffix,
            $className
        );

        if ($dryRun) {
            $this->io->text("DRY-RUN > append to $loaderFile: $instanceLine");
        } else {
            try {
                $added = $this->loaderRegistrar->append($loaderFile, $instanceLine);
                if ($added === true) {
                    $this->io->success("Instance added to: $loaderFile");
                } else {
                    $this->io->warning("Instance already registered in: $loaderFile");
                }
            } catch (\Throwable $e) {
                $this->io->warning($e->getMessage());
            }
        }

        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('DRY-RUN completed. No files were created or modified.');
        } else {
            $this->io->success($spec->successLabel . ' generated successfully.');
        }

        return Command::SUCCESS;
    }
}
