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
			->addArgument('screen', InputArgument::REQUIRED, 'The screen or screens on which to show the box (such as a post type. (e.g., wasp-book)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->initialize($input, $output);

		$name			= $input->getArgument('name');
		$screen			= $input->getArgument('screen');
		$slug			= $this->slugify($name);
		$classSuffix	= str_replace('-', '_', ucwords($slug, '-'));
		$className		= 'Meta_Box_' . $classSuffix;
		$fileName		= "class-{$this->slugRoot}-meta-box-{$slug}.php";

		$targetDir = $this->baseDir . '/classes/meta-box';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

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

		file_put_contents($filePath, $content);
		$output->writeln("Created Meta Box class file: $filePath");

		$loaderFile = $this->baseDir . '/inc/classes.php';
		$instanceLine = sprintf(
		    "new %s\\Meta_Box\\%s;\n",
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
