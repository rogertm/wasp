<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class CreateSettingFieldsCommand extends AbstractGeneratorCommand
{
    protected static $defaultName = 'create:setting_fields';

    protected function configure(): void
    {
        $this
            ->setDescription('Creates a new Setting Fields class file using project config')
            ->addArgument('section', InputArgument::REQUIRED, 'Section name (e.g., My Section Fields)')
            ->addArgument('page_slug', InputArgument::REQUIRED, 'Settings page slug (e.g., wasp-dashboard-setting)')
            ->addArgument(
                'project',
                InputArgument::OPTIONAL,
                'Project slug where this should be created (e.g., wasp-child). Defaults to WASP.',
                null
            )
            ->addOption(
                'subpage',
                null,
                InputOption::VALUE_NONE,
                'Flag to indicate that fields belong to a subpage.'
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
        $this->io->title('⚙️  Create Setting Fields');

        // Load base config (namespaceRoot, slugRoot, textDomain)
        try {
            parent::initialize($input, $output);
        } catch (\Throwable $e) {
            $this->io->error('Failed to load config: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $dryRun   = (bool)$input->getOption('dry-run');
        $isSub    = (bool)$input->getOption('subpage');
        if ($dryRun) {
            $this->io->warning('⚡ DRY-RUN mode: no files will be created.');
        }

        // Read arguments
        $section     = $input->getArgument('section');
        $pageSlugArg = $input->getArgument('page_slug');
        $projectArg  = $input->getArgument('project');

        $this->io->section('1) Initial data');
        $this->io->text([
            "Section name:         $section",
            "Page slug:            $pageSlugArg",
            "Subpage flag:         " . ($isSub ? 'yes' : 'no'),
            "Project (optional):   " . ($projectArg ?: 'WASP (default)'),
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

        // Generate slug and class name
        $slugSection = $this->slugify($section);
        $classSuffix = str_replace('-', '_', ucwords($slugSection, '-'));
        $className   = 'Setting_Fields_' . $classSuffix;

        // Build namespace and use declaration
        if ($projectArg) {
            $nsParts      = array_map('ucfirst', explode('-', $projectArg));
            $nsDeclPrefix = implode('', $nsParts);
        } else {
            $nsDeclPrefix = $this->namespaceRoot;
        }
        $namespaceDecl = $nsDeclPrefix . '\\Setting_Fields';
        $useDecl       = $this->namespaceRoot   . '\\Setting_Fields\\Setting_Fields';

        $this->io->section('2) Class configuration');
        $this->io->text([
            "Slug section:         $slugSection",
            "Class name:           $className",
            "Namespace:            $namespaceDecl",
            "Extends (use):        $useDecl",
        ]);

        // Prepare target directory
        $targetDir = $pluginBaseDir . '/classes/setting-fields';
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
        $subPrefix    = $isSub ? 'sub' : '';
        $fileName     = "class-{$projectSlug}-setting-fields-{$slugSection}.php";
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
            '{{PAGE_SLUG}}'       => $pageSlugArg,
            '{{OPTION_GROUP}}'    => "{$projectSlug}_{$subPrefix}setting",
            '{{OPTION_NAME}}'     => "{$projectSlug}_{$subPrefix}options",
            '{{SECTION_ID}}'      => "{$slugSection}-section-id",
            '{{SECTION_TITLE}}'   => $section,
            '{{FIELD_ID}}'        => "{$slugSection}-field-id",
            '{{FIELD_TITLE}}'     => "{$section} fields",
            '{{TEXT_DOMAIN}}'     => ($projectArg ?: $this->textDomain),
            '{{FILTER}}'          => str_replace('-', '_', $projectSlug . '-' . $slugSection)
        ];

        $this->io->section('4) Generating class from stub');
        if (!$dryRun) {
            try {
                $created = $this->createFileFromStub(
                    'setting_fields',
                    $targetDir,
                    $fileName,
                    $replacements
                );
                $this->io->success("✔ Setting Fields class created at: $created");
            } catch (\Throwable $e) {
                $this->io->error("Failed to generate Setting Fields: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            $this->io->text("DRY-RUN ▶ createFileFromStub(setting_fields → $fullFilePath)");
        }

        // Append instantiation to inc/classes.php
        $this->io->section('5) Registering in inc/classes.php');
        $loaderFile   = $pluginBaseDir . '/inc/classes.php';
        $instanceLine = sprintf(
            "new %s\\Setting_Fields\\%s;\n",
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
            $this->io->success('🎉 Setting Fields generated successfully.');
        }

        return Command::SUCCESS;
    }
}
