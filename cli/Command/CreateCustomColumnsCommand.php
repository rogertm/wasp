<?php
namespace WaspCli\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateCustomColumnsCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:custom_columns';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Custom Columns class file using project config')
            ->addArgument('name', InputArgument::REQUIRED, 'Custom columns name (e.g., Product Columns)')
            ->addArgument('project', InputArgument::OPTIONAL, 'Project slug where this class should be created (e.g., wasp-child). Defaults to WASP.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate creation without writing files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Initialize IO and Filesystem (keeps consistency with other commands)
        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();

        $this->io->title('🧩  Create Custom Columns');

        // Load base config from AbstractGeneratorCommand
        try {
            parent::initialize($input, $output);
        } catch (\Throwable $e) {
            $this->io->error('Failed to load config: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dryRun    = (bool)$input->getOption('dry-run');
        $nameArg   = $input->getArgument('name');
        $projectArg = $input->getArgument('project');

        $this->io->section('1) Initial data');
        $this->io->text([
            "Custom Columns name: " . $nameArg,
            "Project (optional):  " . ($projectArg ?: 'WASP (default)'),
            "Dry-run:             " . ($dryRun ? 'yes' : 'no'),
        ]);

        // Determine plugin base dir and project slug
        if ($projectArg) {
            $childDir = realpath($this->baseDir . '/../' . $projectArg);
            if (!$childDir || !is_dir($childDir)) {
                $this->io->error("Project not found: $projectArg");
                return Command::FAILURE;
            }
            $pluginBaseDir = $childDir;
            $projectSlug   = $projectArg;
        } else {
            $pluginBaseDir = $this->baseDir;
            $projectSlug   = $this->slugRoot;
        }

        $this->io->text("Plugin base directory: $pluginBaseDir");

        // Compute slug and class name
        $slug        = $this->slugify($nameArg); // e.g. product-columns
        $classSuffix = str_replace('-', '_', ucwords($slug, '-')); // Product_Columns
        $className   = 'Custom_Columns_' . $classSuffix; // Custom_Columns_Product_Columns

        // Namespace prefix: either based on projectArg or namespaceRoot (from config)
        if ($projectArg) {
            $nsParts      = array_map('ucfirst', explode('-', $projectArg));
            $nsDeclPrefix = implode('', $nsParts);
        } else {
            $nsDeclPrefix = $this->namespaceRoot;
        }

        $namespaceDecl = $nsDeclPrefix . '\\Custom_Columns';
        $useDecl       = $this->namespaceRoot . '\\Custom_Columns\\Custom_Columns';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Class name:         $className",
            "Namespace:          $namespaceDecl",
            "Use (extends):      $useDecl",
        ]);

        // Prepare target directory
        $targetDir = $pluginBaseDir . '/classes/custom-columns';
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

        // Define file name and check existence
        $fileName     = "class-{$projectSlug}-custom-columns-{$slug}.php";
        $fullFilePath = $targetDir . '/' . $fileName;

        if (file_exists($fullFilePath)) {
            $this->io->error("File already exists: $fullFilePath");
            return Command::FAILURE;
        }

        // Prepare replacements for stub
        $replacements = [
            '{{NAMESPACE_DECL}}' => $namespaceDecl,
            '{{USE_DECL}}'       => $useDecl,
            '{{CLASS_NAME}}'     => $className,
        ];

        $this->io->section('4) Generating class from stub');
        if (!$dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'custom_columns',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ Class created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Failed to generate class: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(custom-columns → $fullFilePath)");
        }

        // Append instantiation to inc/classes.php
        $this->io->section('5) Registering in inc/classes.php');
        $loaderFile = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Custom_Columns\\%s;\n",
            $nsDeclPrefix,
            $className
        );

        if (!$dryRun) {
            if (file_exists($loaderFile) && is_writable($loaderFile)) {
                try {
                    file_put_contents($loaderFile, $instanceLine, FILE_APPEND);
                    $this->io->success("✔ Instance added to: $loaderFile");
                } catch (\Throwable $e) {
                    $this->io->error("Failed to write to $loaderFile: " . $e->getMessage());
                    return Command::FAILURE;
                }
            } else {
                $this->io->warning("Cannot write to $loaderFile. Check existence and permissions.");
            }
        } else {
            $this->io->text("DRY-RUN ▶ append to $loaderFile: $instanceLine");
        }

        // Final
        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('✅ Dry-run complete. No files were written.');
        } else {
            $this->io->success('🎉 Custom Columns generated successfully.');
        }

        return Command::SUCCESS;
    }
}
