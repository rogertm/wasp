<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateMetaBoxCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:meta_box';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Meta Box class using stubs and project configuration')
            ->addArgument('name', InputArgument::REQUIRED, 'Name of the Meta Box (e.g.: My Custom Fields)')
            ->addArgument('screen', InputArgument::REQUIRED, 'Screen where it will appear (e.g.: wasp-book)')
            ->addArgument(
                'project',
                InputArgument::OPTIONAL,
                'Project slug where to create the meta box (e.g.: wasp-child). If omitted, uses WASP.'
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
        // 1) Prepare IO and Filesystem
        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $this->io->title('🗃️  Meta Box Creation');

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
        $screen     = $input->getArgument('screen');
        $projectArg = $input->getArgument('project');

        $this->io->section('1) Initial data');
        $this->io->text([
            "Meta Box name:       $name",
            "Screen:              $screen",
            "Project (optional):  " . ($projectArg ?: 'WASP (default)'),
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

        // 5) Calculate slug and class names
        $slugMeta   = $this->slugify($name);
        $classSuffix = str_replace('-', '_', ucwords($slugMeta, '-'));
        $className   = 'Meta_Box_' . $classSuffix;

        // 6) Namespace and use
        if ($projectArg) {
            $nsParts      = array_map('ucfirst', explode('-', $projectArg));
            $nsDeclPrefix = implode('', $nsParts);
        } else {
            $nsDeclPrefix = $this->namespaceRoot;
        }
        $namespaceDecl = $nsDeclPrefix . '\\Meta_Box';
        $useDecl       = $this->namespaceRoot . '\\Meta_Box\\Meta_Box';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Meta box slug:       $slugMeta",
            "Generated class:     $className",
            "Declare namespace:   $namespaceDecl",
            "Extends from (use):  $useDecl",
            "Assigned screen:     $screen",
        ]);

        // 7) Create classes/meta-box folder
        $targetDir = $pluginBaseDir . '/classes/meta-box';
        $this->io->section('3) Creating meta-box folder');
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

        // 8) Prepare stub and generate file
        $fileName     = "class-{$projectSlug}-meta-box-{$slugMeta}.php";
        $fullFilePath = $targetDir . '/' . $fileName;
        if (file_exists($fullFilePath)) {
            $this->io->error("Already exists: $fullFilePath");
            return Command::FAILURE;
        }

        $replacements = [
            '{{NAMESPACE_DECL}}'  => $namespaceDecl,
            '{{USE_DECL}}'        => $useDecl,
            '{{CLASS_NAME}}'      => $className,
            '{{SLUG_FULL}}'       => $projectSlug . '-' . $slugMeta,
            '{{NAME}}'            => $name,
            '{{SCREEN}}'          => $screen,
            '{{TEXT_DOMAIN}}'     => ($projectArg ?: $this->textDomain),
            '{{FILTER}}'          => str_replace('-', '_', $projectSlug . '-' . $slugMeta)
        ];

        $this->io->section('4) Generating class from stub');
        if (! $dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'meta_box',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ Meta Box created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Error creating Meta Box: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(meta_box → $fullFilePath)");
        }

        // 9) Add instance in inc/classes.php
        $this->io->section('5) Registering in inc/classes.php');
        $loaderFile   = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Meta_Box\\%s;\n",
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
            $this->io->success('🎉 Meta Box successfully generated.');
        }

        return Command::SUCCESS;
    }
}
