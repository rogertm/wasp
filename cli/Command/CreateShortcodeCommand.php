<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateShortcodeCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:shortcode';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Shortcode class file using project config')
            ->addArgument('name', InputArgument::REQUIRED, 'Shortcode name (e.g., Photo Gallery)')
            ->addArgument('project', InputArgument::OPTIONAL, 'Project slug where this shortcode should be created (e.g., wasp-child). Defaults to WASP.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate creation without writing files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Initialize IO and filesystem
        $this->io = new SymfonyStyle($input, $output);
        $this->filesystem = new \Symfony\Component\Filesystem\Filesystem();
        $this->io->title('🔌  Create Shortcode');

        // Load base configuration
        try {
            parent::initialize($input, $output);
        } catch (\Throwable $e) {
            $this->io->error('Failed to load config: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dryRun    = (bool)$input->getOption('dry-run');
        if ($dryRun) {
            $this->io->warning('⚡ DRY-RUN mode activated: no files or directories will be created.');
        }

        // Arguments
        $nameArg    = $input->getArgument('name');
        $projectArg = $input->getArgument('project');

        $this->io->section('1) Initial data');
        $this->io->text([
            "Shortcode name:      $nameArg",
            "Project (optional):  " . ($projectArg ?: 'WASP (default)'),
        ]);

        // Determine plugin base directory and project slug
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

        // Compute slugs and names
        // shortcode slug (used for filenames): "photo-gallery"
        $shortcodeSlug = $this->slugify($nameArg);

        // shortcode tag used at runtime (with plugin prefix and underscores): "wasp_photo_gallery"
        $shortcodeTag = $projectSlug . '_' . str_replace('-', '_', $shortcodeSlug);

        // class name suffix from original slug (e.g. Photo_Gallery -> Postfix style)
        $classSuffix  = str_replace('-', '_', ucwords($shortcodeSlug, '-'));
        $className    = 'Shortcode_' . $classSuffix; // e.g. Shortcode_Photo_Gallery

        // Namespace and use declarations
        if ($projectArg) {
            $nsParts      = array_map('ucfirst', explode('-', $projectArg));
            $nsDeclPrefix = implode('', $nsParts);
        } else {
            $nsDeclPrefix = $this->namespaceRoot;
        }
        $namespaceDecl = $nsDeclPrefix . '\\Shortcode';
        $useDecl       = $this->namespaceRoot . '\\Shortcode\\Shortcode';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Shortcode slug (file):  $shortcodeSlug",
            "Shortcode tag (runtime): $shortcodeTag",
            "Class name:              $className",
            "Namespace:               $namespaceDecl",
            "Extends (use):           $useDecl",
        ]);

        // Prepare directories
        $targetDir    = $pluginBaseDir . '/classes/shortcode';
        $templatesDir = $pluginBaseDir . '/templates/shortcodes';
        $this->io->section('3) Preparing directories');

        if (!$dryRun) {
            try {
                $this->filesystem->mkdir($targetDir, 0755);
                $this->filesystem->mkdir($templatesDir, 0755);
                $this->io->text("✔ Directories ready: $targetDir, $templatesDir");
            } catch (IOExceptionInterface $e) {
                $this->io->error("Failed to create directories: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ mkdir $targetDir");
            $this->io->text("DRY-RUN ▶ mkdir $templatesDir");
        }

        // Create class file from stub
        $fileName     = "class-{$projectSlug}-shortcode-{$shortcodeSlug}.php";
        $fullFilePath = $targetDir . '/' . $fileName;

        if (file_exists($fullFilePath)) {
            $this->io->error("File already exists: $fullFilePath");
            return Command::FAILURE;
        }

        $replacements = [
            '{{NAMESPACE_DECL}}' => $namespaceDecl,
            '{{USE_DECL}}'       => $useDecl,
            '{{CLASS_NAME}}'     => $className,
            '{{SHORTCODE_TAG}}'  => $shortcodeTag,
            '{{PAGE_SLUG}}'      => $projectSlug,
        ];

        $this->io->section('4) Generating class from stub');
        if (!$dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'shortcode',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ Shortcode class created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Failed to generate shortcode class: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(shortcode → $fullFilePath)");
        }

        // Create template file from template stub if it does not already exist.
        $templateFileName = $shortcodeTag . '.php';
        $templateFullPath = $templatesDir . '/' . $templateFileName;

        $this->io->section('5) Creating template from template stub');
        if (!$dryRun) {
            if (file_exists($templateFullPath)) {
                $this->io->warning("Template already exists: $templateFullPath");
            } else {
                // Prepare replacements for template stub (if needed)
                $tplReplacements = [
                    '{{SHORTCODE_TAG}}' => $shortcodeTag,
                    '{{CLASS_NAME}}'    => $className,
                    '{{PAGE_SLUG}}'     => $projectSlug,
                ];

                try {
                    // Use createFileFromStub to render cli/stubs/shortcode-template.stub → templates/shortcodes/{tag}.php
                    $createdTpl = $this->createFileFromStub(
                        'shortcode-template',
                        $templatesDir,
                        $templateFileName,
                        $tplReplacements
                    );
                    $this->io->success("✔ Template created at: $createdTpl");
                } catch (\Throwable $e) {
                    $this->io->error("Failed to generate template: " . $e->getMessage());
                    return Command::FAILURE;
                }
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(shortcode-template → $templateFullPath)");
        }

        // Register class in inc/classes.php
        $this->io->section('6) Registering in inc/classes.php');
        $loaderFile   = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Shortcode\\%s;\n",
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

        // Final message
        $this->io->newLine();
        if ($dryRun) {
            $this->io->success('🦄 DRY-RUN complete. No files were written.');
        } else {
            $this->io->success('🎉 Shortcode generated successfully.');
        }

        return Command::SUCCESS;
    }
}
