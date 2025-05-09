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

		$name        = $input->getArgument('name');
		$slug        = $this->slugify($name);
		$classSuffix = str_replace('-', '_', ucwords($slug, '-'));
		$className   = 'Post_Type_' . $classSuffix;
		$fileName    = "class-{$this->slugRoot}-post-type-{$slug}.php";

		$targetDir = $this->baseDir . '/classes/post-type';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

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

		file_put_contents($filePath, $content);
		$output->writeln("Created CPT class file: $filePath");

		$loaderFile = $this->baseDir . '/inc/classes.php';
		$instanceLine = sprintf(
		    "new %s\\Post_Type\\%s;\n",
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
