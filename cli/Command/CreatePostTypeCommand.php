<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreatePostTypeCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:post_type';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Custom Post Type class using stubs and project configuration')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the Post Type (e.g.: Book)')
            ->addArgument(
                'project',
                InputArgument::OPTIONAL,
                'Project slug where the CPT will be created (e.g.: wasp-child). If omitted, uses WASP.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Simulates the creation without writing files.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1) Initialize SymfonyStyle, Filesystem and load config
        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();

        $this->io->title('📌  Custom Post Type Creation');

        // Initialize base paths/config from AbstractGeneratorCommand
        try {
            parent::initialize($input, $output);
        } catch (\Throwable $e) {
            $this->io->error('Error loading configuration: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun) {
            $this->io->warning('⚡ DRY-RUN mode activated: no files or folders will be created.');
        }

        // 2) Read arguments
        $nameArg = $input->getArgument('name');
        $projectArg = $input->getArgument('project'); // can be null

        $this->io->section('1) Preparing data');
        $this->io->text([
            "Post Type Name:  $nameArg",
            "Project (optional):  " . ($projectArg ?: 'WASP (default)'),
        ]);

        // 3) Determine in which folder the CPT will be created
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

        $this->io->text("CPT root directory: $pluginBaseDir");

        // 4) Generate slug and class names
        try {
            $slugCPT = $this->slugify($nameArg);
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }
        // Example: "book" → "Book"
        $classSuffix = str_replace('-', '_', ucwords($slugCPT, '-'));
        $className = 'Post_Type_' . $classSuffix; // e.g.: Post_Type_Book

        // 5) Compute namespace and use
        $namespaceDecl = $nsDeclPrefix . '\\Post_Type';
        $useDecl = $this->namespaceRoot . '\\Posts\\Post_Type';

        $this->io->text([
            "Slug CPT:           $slugCPT",
            "Generated class:    $className",
            "Declare Namespace:  $namespaceDecl",
            "Use (extends from): $useDecl",
        ]);

        // 6) Destination directory: {pluginBaseDir}/classes/post-type
        $targetDir = $pluginBaseDir . '/classes/post-type';
        $this->io->text("CPT destination directory: $targetDir");

        // 6.a) Ensure the folder exists (or simulate it)
        $this->io->section('2) Creating destination folder');
        if (! $dryRun) {
            try {
                $this->filesystem->mkdir($targetDir, 0755);
                $this->io->text("✔ Folder created (or already exists): $targetDir");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Error creating folder $targetDir: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ mkdir $targetDir");
        }

        // 7) File name: class-{projectSlug}-post-type-{slugCPT}.php
        $fileName = "class-{$projectSlug}-post-type-{$slugCPT}.php";
        $fullFilePath = $targetDir . '/' . $fileName;

        // 7.a) If it already exists, show error
        if (file_exists($fullFilePath)) {
            $this->io->error("A CPT with that name already exists: $fullFilePath");
            return Command::FAILURE;
        }

        // 8) Prepare replacements for the stub
        $replacements = [
            '{{NAMESPACE_DECL}}' => $namespaceDecl,
            '{{USE_DECL}}'       => $useDecl,
            '{{CLASS_NAME}}'     => $className,
            '{{SLUG_FULL}}'      => $projectSlug . '-' . $slugCPT,
            '{{NAME}}'           => $nameArg,
            '{{TEXT_DOMAIN}}'    => $textDomain,
        ];

        $this->io->section('3) Generating class from stub');

        if (! $dryRun) {
            try {
                $createdPath = $this->createFileFromStub(
                    'post_type',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ CPT class created at: $createdPath");
            } catch (\Throwable $e) {
                $this->io->error("Error generating CPT: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(post_type → $fullFilePath)");
        }

        // 9) Write instance to inc/classes.php (if it exists) so it auto-registers
        $this->io->section('4) Adding registration in inc/classes.php');
        // Absolute path to inc/classes.php in that plugin
        $loaderFile = $pluginBaseDir . '/inc/classes.php';

        // Line to add: new Namespace\Post_Type\ClassName;
        $instanceLine = sprintf(
            "new %s\\Post_Type\\%s;\n",
            $nsDeclPrefix,
            $className
        );

        if (! $dryRun) {
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
            $this->io->text("DRY-RUN ▶ Add line to loader: $loaderFile");
        }

        // 10) Completion
        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('🦄 DRY-RUN completed. No file was created or modified.');
        } else {
            $this->io->success('🎉 Custom Post Type successfully generated.');
        }

        return Command::SUCCESS;
    }
}
