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
			->addArgument('parent_slug', InputArgument::REQUIRED, 'The slug name for the parent menu (e.g., wasp-dashboard-setting)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$parent_slug	= $input->getArgument('parent_slug');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Admin_Page_' . $classSuffix;
		$fileName		= "class-{$this->slugRoot}-admin-page-{$slug}.php";

		$targetDir = $this->baseDir . '/classes/admin-page';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

		$namespaceDecl	= $this->namespaceRoot . '\\Admin';
		$useDecl		= $this->namespaceRoot . '\\Admin\\Admin_Page';
		$page_slug 		= $this->slugRoot.'-'.$slug.'-subsetting';
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
		\$this->page_title		= __( '{$name} Admin Page', '{$this->textDomain}' );
		\$this->menu_title		= __( '{$name}', '{$this->textDomain}' );
		\$this->page_heading	= __( '{$name} Submenu Dashboard', '{$this->textDomain}' );
		\$this->capability		= 'manage_options';
		\$this->menu_slug		= '{$page_slug}';
		\$this->option_group	= '{$this->slugRoot}_subsetting';
		\$this->option_name		= '{$this->slugRoot}_options';
	}
}

PHP;

		file_put_contents($filePath, $content);
		$output->writeln("Created Admin Sub Page class file: $filePath");

		$loaderFile = $this->baseDir . '/inc/classes.php';
		$instanceLine = sprintf(
		    "new %s\\Admin\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);

		if (file_exists($loaderFile) && is_writable($loaderFile)) {
		    file_put_contents($loaderFile, $instanceLine, FILE_APPEND);
		    $output->writeln("Appended instance to: $loaderFile");
		} else {
		    $output->writeln("<comment>Warning: Could not write to $loaderFile</comment>");
		}

		$output->writeln('<info>Done!</info>');
		return Command::SUCCESS;
	}
}
