<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateUserMetaCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:user_meta';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new User Meta class file using project config')
            ->addArgument('name', InputArgument::REQUIRED, 'User Meta name (e.g., My Custom Fields)')
            ->addArgument(
                'project',
                InputArgument::OPTIONAL,
                'Project slug where this User Meta should be created (e.g., wasp-child). Defaults to WASP.'
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
        // Initialize SymfonyStyle and Filesystem
        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $this->io->title('👤 Create User Meta');

        // Load base configuration
        try {
            parent::initialize($input, $output);
        } catch (\Throwable $e) {
            $this->io->error('Failed to load config: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dryRun = (bool)$input->getOption('dry-run');
        if ($dryRun) {
            $this->io->warning('⚡ DRY-RUN mode: no files will be created.');
        }

        // Read arguments
        $name       = $input->getArgument('name');
        $projectArg = $input->getArgument('project');

        $this->io->section('1) Initial data');
        $this->io->text([
            "User Meta Name:     $name",
            "Project (optional): " . ($projectArg ?: 'WASP (default)'),
        ]);

        // Determine plugin base directory and slug
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

        // Generate slug and class name
        $slugMeta    = $this->slugify($name);
        $classSuffix = str_replace('-', '_', ucwords($slugMeta, '-'));
        $className   = 'User_Meta_' . $classSuffix;

        // Build namespace and use declaration
        if ($projectArg) {
            $nsParts      = array_map('ucfirst', explode('-', $projectArg));
            $nsDeclPrefix = implode('', $nsParts);
        } else {
            $nsDeclPrefix = $this->namespaceRoot;
        }
        $namespaceDecl = $nsDeclPrefix . '\\Users';
        $useDecl       = $this->namespaceRoot   . '\\Users\\User_Meta';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Slug meta:          $slugMeta",
            "Class name:         $className",
            "Namespace:          $namespaceDecl",
            "Extends (use):      $useDecl",
        ]);

        // Prepare target directory
        $targetDir = $pluginBaseDir . '/classes/user-meta';
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
        $fileName     = "class-{$projectSlug}-user-meta-{$slugMeta}.php";
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
            '{{FILTER}}'         => str_replace('-', '_', $projectSlug . '-' . $slugMeta)
        ];

        $this->io->section('4) Generating class from stub');
        if (!$dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'user_meta',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ User Meta class created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Failed to generate User Meta: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(user_meta → $fullFilePath)");
        }

        // Append instantiation to inc/classes.php
        $this->io->section('5) Registering in inc/classes.php');
        $loaderFile   = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Users\\%s;\n",
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

        // Final success
        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('🦄 Dry-run complete. No files were written.');
        } else {
            $this->io->success('🎉 User Meta generated successfully.');
        }

        return Command::SUCCESS;
    }
}
