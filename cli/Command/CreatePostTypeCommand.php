<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreatePostTypeCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'create:post_type';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Custom Post Type class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Post Type name (e.g., Book)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Post_Type_' . $classSuffix;
		$targetDir		=  '/classes/post-type';
		$fileName		= "class-{$this->slugRoot}-post-type-{$slug}.php";
		$filePath 		= $this->file($targetDir, $fileName, $output);

		if (false === $filePath)
			return Command::FAILURE;

		$namespaceDecl = $this->namespaceRoot . '\\Post_Type';
		$useDecl       = $this->namespaceRoot . '\\Posts\\Post_Type';
		$content       = <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Post_Type
{
	public function __construct()
	{
		parent::__construct();

		// CPT slug
		\$this->post_type = '{$this->slugRoot}-{$slug}';

		// CPT labels
		\$this->labels = array(
			'name'		=> _x( '{$name}', 'Post type general name', '{$this->textDomain}' )
		);

		// CPT arguments
		\$this->args = array(
			'public'	=> true
		);
	}
}

PHP;

		$instanceLine = sprintf(
		    "new %s\\Post_Type\\%s;\n",
		    $this->namespaceRoot,
		    $className
		);
		$label = 'Post Type';

		$this->write( $filePath, $content, $label, $instanceLine, $output );

		return Command::SUCCESS;
	}
}
