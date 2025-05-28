<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateAdminPageCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:admin_page';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Admin Page class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Admin Page name (e.g., My plugin dashboard)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Admin_Page_' . $classSuffix;
		$targetDir 		= '/classes/admin-page';
		$fileName		= "class-{$this->slugRoot}-admin-page-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$namespaceDecl	= $this->namespaceRoot . '\\Admin';
		$useDecl		= $this->namespaceRoot . '\\Admin\\Admin_Page';
		$page_slug 		= $this->slugRoot.'-'.$slug.'-setting';
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Admin_Page
{
	public function __construct()
	{
		parent::__construct();
		\$this->page_title		= __( '{$name} Admin Page', '{$this->textDomain}' );
		\$this->menu_title		= __( '{$name}', '{$this->textDomain}' );
		\$this->page_heading	= __( '{$name} Admin Page', '{$this->textDomain}' );
		\$this->capability		= 'manage_options';
		\$this->menu_slug		= '{$page_slug}';
		\$this->option_group	= '{$this->slugRoot}_setting';
		\$this->option_name		= '{$this->slugRoot}_options';
		\$this->position		= 2;
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Admin\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);

		$this->write( $filePath, $content, $instanceLine, $output );

		return Command::SUCCESS;
	}
}
