<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateTermMetaCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:term_meta';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Term Meta class file using project config')
            ->addArgument('name', InputArgument::REQUIRED, 'Term Meta name (e.g., My Custom Fields)')
            ->addArgument('taxonomy', InputArgument::REQUIRED, 'The taxonomy slug to associate with (e.g., wasp-genre)')
            ->addArgument(
                'project',
                InputArgument::OPTIONAL,
                'Project slug in which to create this Term Meta (e.g., wasp-child). Defaults to WASP.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulate creation without writing any files.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Initialize IO and Filesystem
        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $this->io->title('📝  Create Term Meta');

        // Load base config (namespaceRoot, slugRoot, etc.)
        try {
            parent::initialize($input, $output);
        } catch (\Throwable $e) {
            $this->io->error('Failed to load config: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $this->io->warning('⚡ DRY-RUN mode: no files will be created.');
        }

        // Read arguments
        $name        = $input->getArgument('name');
        $taxonomy    = $input->getArgument('taxonomy');
        $projectArg  = $input->getArgument('project');

        $this->io->section('1) Initial data');
        $this->io->text([
            "Term Meta Name:    $name",
            "Taxonomy Slug:     $taxonomy",
            "Project (optional): " . ($projectArg ?: 'WASP (default)'),
        ]);

        // Determine plugin base directory and slug
        try {
            $context = $this->resolveProjectContext($projectArg);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }
        $pluginBaseDir = $context['plugin_base_dir'];
        $projectSlug   = $context['project_slug'];
        $nsDeclPrefix  = $context['namespace_prefix'];
        $textDomain    = $context['text_domain'];

        $this->io->text("Plugin base directory: $pluginBaseDir");

        // Generate slug and class name
        try {
            $slugMeta = $this->slugify($name);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }
        $classSuffix = str_replace('-', '_', ucwords($slugMeta, '-'));
        $className   = 'Term_Meta_' . $classSuffix;

        // Build namespace and use declarations
        $namespaceDecl = $nsDeclPrefix . '\\Terms';
        $useDecl       = $this->namespaceRoot   . '\\Terms\\Term_Meta';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Slug meta:          $slugMeta",
            "Class name:         $className",
            "Namespace:          $namespaceDecl",
            "Extends (use):      $useDecl",
        ]);

        // Prepare target directory
        $targetDir = $pluginBaseDir . '/classes/term-meta';
        $this->io->section('3) Preparing directory');
        if (!$dryRun) {
            try {
                $this->filesystem->mkdir($targetDir, 0755);
                $this->io->text("✔ Directory ready: $targetDir");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Failed to create directory $targetDir: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ mkdir $targetDir");
        }

        // Define file path
        $fileName     = "class-{$projectSlug}-term-meta-{$slugMeta}.php";
        $fullFilePath = $targetDir . '/' . $fileName;
        if (file_exists($fullFilePath)) {
            $this->io->error("File already exists: $fullFilePath");
            return Command::FAILURE;
        }

        // Prepare stub replacements
        $replacements = [
            '{{NAMESPACE_DECL}}' => $namespaceDecl,
            '{{USE_DECL}}'       => $useDecl,
            '{{CLASS_NAME}}'     => $className,
            '{{SLUG_FULL}}'      => $projectSlug . '-' . $slugMeta,
            '{{TAXONOMY}}'       => $taxonomy,
            '{{NAME}}'           => $name,
            '{{TEXT_DOMAIN}}'    => $textDomain,
            '{{FILTER}}'         => str_replace('-', '_', $projectSlug . '-' . $slugMeta)
        ];

        $this->io->section('4) Generating class from stub');
        if (!$dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'term_meta',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ Term Meta class created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Failed to generate Term Meta: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(term_meta → $fullFilePath)");
        }

        // Append instantiation to inc/classes.php
        $this->io->section('5) Registering in inc/classes.php');
        $loaderFile   = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Terms\\%s;\n",
            $nsDeclPrefix,
            $className
        );

        if (!$dryRun) {
            try {
                $added = $this->appendLineToLoader($loaderFile, $instanceLine);
                if ($added) {
                    $this->io->success("✔ Instance added to: $loaderFile");
                } else {
                    $this->io->warning("Instance already registered in: $loaderFile");
                }
            } catch (\Throwable $e) {
                $this->io->warning($e->getMessage());
            }
        } else {
            $this->io->text("DRY-RUN ▶ append to $loaderFile: $instanceLine");
        }

        // Final success
        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('🦄 Dry-run complete. No files were written.');
        } else {
            $this->io->success('🎉 Term Meta generated successfully.');
        }

        return Command::SUCCESS;
    }
}
