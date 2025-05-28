<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateMetaBoxCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:meta_box';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Meta Box class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Meta Box name (e.g., My custom fields)')
			->addArgument('screen', InputArgument::REQUIRED, 'The screen or screens on which to show the box, such as a post type. (e.g., wasp-book)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$screen			= $input->getArgument('screen');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Meta_Box_' . $classSuffix;
		$targetDir		= '/classes/meta-box';
		$fileName		= "class-{$this->slugRoot}-meta-box-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$namespaceDecl	= $this->namespaceRoot . '\\Meta_Box';
		$useDecl		= $this->namespaceRoot . '\\Meta_Box\\Meta_Box';
		$filter			= str_replace('-', '_', $slug);
		$content		= <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Meta_Box
{
	public function __construct()
	{
		parent::__construct();
		\$this->id				= '{$slug}-custom-field';
		\$this->title			= __( '{$name}', '{$this->textDomain}' );
		\$this->screen			= '{$screen}';
		\$this->context			= 'advanced';
		\$this->priority		= 'default';
		\$this->callback_args	= null;
	}

	function fields()
	{
		\$fields = array(
			// Your fields goes here...
		);

		return apply_filters( '{$this->slugRoot}_{$filter}_custom_fields', \$fields );
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Meta_Box\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);

		$this->write( $filePath, $content, $instanceLine, $output );

		return Command::SUCCESS;

	}
}
