<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateAdminPageCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:admin_page';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Admin Page class file using project config')
            ->addArgument('name', InputArgument::REQUIRED, 'Admin Page name (e.g., My Plugin Dashboard)')
            ->addArgument(
                'project',
                InputArgument::OPTIONAL,
                'Project slug where this Admin Page should be created (e.g., wasp-child). Defaults to WASP.'
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
        $this->io->title('⚙️ Create Admin Page');

        // Load base configuration (namespaceRoot, slugRoot, textDomain)
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
            "Admin Page Name:    $name",
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
            $slugPage = $this->slugify($name);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }
        $classSuffix = str_replace('-', '_', ucwords($slugPage, '-'));
        $className   = 'Admin_Page_' . $classSuffix;

        // Build namespace and use declaration
        $namespaceDecl = $nsDeclPrefix . '\\Admin';
        $useDecl       = $this->namespaceRoot   . '\\Admin\\Admin_Page';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Slug page:          $slugPage",
            "Class name:         $className",
            "Namespace:          $namespaceDecl",
            "Extends (use):      $useDecl",
        ]);

        // Prepare target directory
        $targetDir = $pluginBaseDir . '/classes/admin-page';
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
        $pageSlug   = $projectSlug . '-' . $slugPage . '-setting';
        $fileName   = "class-{$projectSlug}-admin-page-{$slugPage}.php";
        $fullFilePath = $targetDir . '/' . $fileName;
        if (file_exists($fullFilePath)) {
            $this->io->error("File already exists: $fullFilePath");
            return Command::FAILURE;
        }

        // Prepare stub replacements
        $replacements = [
            '{{NAMESPACE_DECL}}'  => $namespaceDecl,
            '{{USE_DECL}}'        => $useDecl,
            '{{CLASS_NAME}}'      => $className,
            '{{PAGE_TITLE}}'      => $name . ' Admin Page',
            '{{MENU_TITLE}}'      => $name,
            '{{PAGE_HEADING}}'    => $name . ' Admin Page',
            '{{CAPABILITY}}'      => 'manage_options',
            '{{MENU_SLUG}}'       => $pageSlug,
            '{{OPTION_GROUP}}'    => $projectSlug . '_setting',
            '{{OPTION_NAME}}'     => $projectSlug . '_options',
            '{{POSITION}}'        => '2',
            '{{TEXT_DOMAIN}}'     => $textDomain,
        ];

        $this->io->section('4) Generating class from stub');
        if (!$dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'admin_page',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ Admin Page class created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Failed to generate Admin Page: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(admin_page → $fullFilePath)");
        }

        // Append instantiation to inc/classes.php
        $this->io->section('5) Registering in inc/classes.php');
        $loaderFile   = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Admin\\%s;\n",
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
            $this->io->success('🎉 Admin Page generated successfully.');
        }

        return Command::SUCCESS;
    }
}
