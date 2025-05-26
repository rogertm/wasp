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
		$fileName		= "class-{$this->slugRoot}-user-meta-{$slug}.php";

		$targetDir = $this->baseDir . '/classes/user-meta';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

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

		file_put_contents($filePath, $content);
		$output->writeln("Created User Meta class file: $filePath");

		$loaderFile = $this->baseDir . '/inc/classes.php';
		$instanceLine = sprintf(
		    "new %s\\Users\\%s;\n",
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
