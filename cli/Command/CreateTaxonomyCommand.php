<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateTaxonomyCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:taxonomy';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Taxonomy class using stubs and the project configuration')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the Taxonomy (e.g.: Genre)')
            ->addArgument('object_type', InputArgument::REQUIRED, 'Object type associated (e.g.: wasp-book)')
            ->addArgument(
                'project',
                InputArgument::OPTIONAL,
                'Project slug where the taxonomy will be created (e.g.: wasp-child). If omitted, uses WASP.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulates creation without writing files or folders.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1) Prepare IO and filesystem
        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $this->io->title('🏷️  Taxonomy Creation');

        // 2) Load base configuration
        try {
            parent::initialize($input, $output);
        } catch (\Throwable $e) {
            $this->io->error('Error loading configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $this->io->warning('⚡ DRY-RUN mode: no files will be created.');
        }

        // 3) Read arguments
        $name       = $input->getArgument('name');
        $objectType = $input->getArgument('object_type');
        $projectArg = $input->getArgument('project');

        $this->io->section('1) Initial data');
        $this->io->text([
            "Taxonomy name:      $name",
            "Object type:        $objectType",
            "Project (optional): " . ($projectArg ?: 'WASP (default)'),
        ]);

        // 4) Determine plugin base directory
        if ($projectArg) {
            $childDir = realpath($this->baseDir . '/../' . $projectArg);
            if (! $childDir || ! is_dir($childDir)) {
                $this->io->error("Project not found: $projectArg");
                return Command::FAILURE;
            }
            $pluginBaseDir = $childDir;
            $projectSlug   = $projectArg;
        } else {
            $pluginBaseDir = $this->baseDir;
            $projectSlug   = $this->slugRoot;
        }

        $this->io->text("Destination folder: $pluginBaseDir");

        // 5) Compute slug and class names
        $slugTax = $this->slugify($name);
        $classSuffix = str_replace('-', '_', ucwords($slugTax, '-'));
        $className   = 'Taxonomy_' . $classSuffix; // Taxonomy_Genre

        // 6) Namespace and use
        if ($projectArg) {
            $nsParts      = array_map('ucfirst', explode('-', $projectArg));
            $nsDeclPrefix = implode('', $nsParts);
        } else {
            $nsDeclPrefix = $this->namespaceRoot;
        }
        $namespaceDecl = $nsDeclPrefix . '\\Taxonomy';
        $useDecl       = $this->namespaceRoot . '\\Taxonomy\\Taxonomy';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Taxonomy slug:      $slugTax",
            "Class name:         $className",
            "Declare namespace:  $namespaceDecl",
            "Extends from (use): $useDecl",
        ]);

        // 7) Create folder classes/taxonomy
        $targetDir = $pluginBaseDir . '/classes/taxonomy';
        $this->io->section('3) Creating taxonomy folder');
        if (! $dryRun) {
            try {
                $this->filesystem->mkdir($targetDir, 0755);
                $this->io->text("✔ Folder exists/created: $targetDir");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Error mkdir $targetDir: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ mkdir $targetDir");
        }

        // 8) Prepare stub
        $fileName     = "class-{$projectSlug}-taxonomy-{$slugTax}.php";
        $fullFilePath = $targetDir . '/' . $fileName;
        if (file_exists($fullFilePath)) {
            $this->io->error("Already exists: $fullFilePath");
            return Command::FAILURE;
        }

        $replacements = [
            '{{NAMESPACE_DECL}}' => $namespaceDecl,
            '{{USE_DECL}}'       => $useDecl,
            '{{CLASS_NAME}}'     => $className,
            '{{SLUG_FULL}}'      => $projectSlug . '-' . $slugTax,
            '{{OBJECT_TYPE}}'    => $objectType,
            '{{NAME}}'           => $name,
            '{{TEXT_DOMAIN}}'    => ($projectArg ?: $this->textDomain),
        ];

        $this->io->section('4) Generating class from stub');
        if (! $dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'taxonomy',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ Taxonomy created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Error creating taxonomy: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(taxonomy → $fullFilePath)");
        }

        // 9) Add instance in inc/classes.php
        $this->io->section('5) Registering in inc/classes.php');
        $loaderFile   = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Taxonomy\\%s;\n",
            $nsDeclPrefix,
            $className
        );

        if (! $dryRun) {
            if (file_exists($loaderFile) && is_writable($loaderFile)) {
                try {
                    file_put_contents($loaderFile, $instanceLine, FILE_APPEND);
                    $this->io->success("✔ Instance added to: $loaderFile");
                } catch (\Throwable $e) {
                    $this->io->error("Error writing to $loaderFile: " . $e->getMessage());
                    return Command::FAILURE;
                }
            } else {
                $this->io->warning("Could not write to $loaderFile. Check permissions/existence.");
            }
        } else {
            $this->io->text("DRY-RUN ▶ append to $loaderFile: $instanceLine");
        }

        // 10) Finish
        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('🦄 DRY-RUN completed. No files were created.');
        } else {
            $this->io->success('🎉 Taxonomy successfully generated.');
        }

        return Command::SUCCESS;
    }
}
