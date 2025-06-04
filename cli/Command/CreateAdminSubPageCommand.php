<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateAdminSubPageCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:admin_subpage';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Admin Page class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Admin Page name (e.g., My plugin dashboard)')
			->addArgument('parent_slug', InputArgument::REQUIRED, 'The slug name for the parent menu (e.g., wasp-dashboard-setting)')
			->addArgument('project', InputArgument::OPTIONAL, 'Project slug where this CPT should be created (e.g., wasp-child). If omitted, uses WASP.');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$parent_slug	= $input->getArgument('parent_slug');
		$project 		= $input->getArgument('project');
		if (isset($project) && !is_dir('../'. $project)){
			$output->writeln("<error>Project not found: $project</error>");
			return Command::FAILURE;
		}

		$this->baseDir	= ($project) ? $this->baseDir .'/../'. $project : $this->baseDir;
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Admin_Page_' . $classSuffix;
		$targetDir		= ($project) ? '../'.$project.'/classes/admin-page' : 'classes/admin-page';
		$projectSlug	= $project ?: $this->slugRoot;
		$fileName		= "class-{$projectSlug}-admin-page-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$textDomain		= ($project) ? $project : $this->textDomain;
		$nsParts		= ($project) ? array_map('ucfirst', explode('-', $project)) : null;
		$nsDeclPrefix	= ($nsParts) ? implode('', $nsParts) : $this->namespaceRoot;

		$namespaceDecl	= $nsDeclPrefix . '\\Admin';
		$useDecl		= $this->namespaceRoot . '\\Admin\\Admin_Sub_Menu_Page';
		$page_slug 		= $projectSlug.'-'.$slug.'-subsetting';
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Admin_Sub_Menu_Page
{
	public function __construct()
	{
		parent::__construct();
		\$this->parent_slug		= '{$parent_slug}';
		\$this->page_title		= __( '{$name} Admin Page', '{$textDomain}' );
		\$this->menu_title		= __( '{$name}', '{$textDomain}' );
		\$this->page_heading	= __( '{$name} Submenu Dashboard', '{$textDomain}' );
		\$this->capability		= 'manage_options';
		\$this->menu_slug		= '{$page_slug}';
		\$this->option_group	= '{$projectSlug}_subsetting';
		\$this->option_name		= '{$projectSlug}_options';
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Admin\\%s;\n",
		    $nsDeclPrefix,
		    $className
		);
		$label = 'Admin Subpage';

		$this->write( $filePath, $content, $label, $instanceLine, $output, $project );

		return Command::SUCCESS;
	}
}
