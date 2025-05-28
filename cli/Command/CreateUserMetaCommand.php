<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateUserMetaCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:user_meta';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new User Meta class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'User Meta name (e.g., My custom fields)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'User_Meta_' . $classSuffix;
		$targetDir 		= '/classes/user-meta';
		$fileName		= "class-{$this->slugRoot}-user-meta-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$namespaceDecl	= $this->namespaceRoot . '\\Users';
		$useDecl		= $this->namespaceRoot . '\\Users\\User_Meta';
		$filter			= str_replace('-', '_', $slug);
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends User_Meta
{

	function fields()
	{
		\$fields = array(
			// Your fields goes here...
		);

		return apply_filters( '{$this->slugRoot}_{$filter}_user_meta_fields', \$fields );
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Users\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);

		$this->write( $filePath, $content, $instanceLine, $output );

		return Command::SUCCESS;

	}

}
