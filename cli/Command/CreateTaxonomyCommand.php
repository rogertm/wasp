<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateTaxonomyCommand extends Command
{
	protected static $defaultName = 'create:taxonomy';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new Taxonomy class file using project config')
			->addArgument('name', InputArgument::REQUIRED, 'Taxonomy name (e.g., Genre)')
			->addArgument('object_type', InputArgument::REQUIRED, 'Object type with which the taxonomy should be associated. (e.g., wasp-book)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$baseDir    = realpath(__DIR__ . '/../../');
		$configPath = $baseDir . '/cli/config.json';

		$config        = json_decode(file_get_contents($configPath), true);
		$namespaceRoot = $config['namespace'];
		$slugRoot      = $config['slug'];
		$textDomain    = $config['text_domain'];

		$name        = $input->getArgument('name');
		$object_type = $input->getArgument('object_type');
		$slug        = $this->slugify($name);
		$classSuffix = str_replace('-', '_', ucwords($slug, '-'));
		$className   = 'Taxonomy_' . $classSuffix;
		$fileName    = "class-{$slugRoot}-taxonomy-{$slug}.php";

		$targetDir = $baseDir . '/classes/taxonomy';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

		$namespaceDecl = $namespaceRoot . '\\Taxonomy';
		$useDecl       = $namespaceRoot . '\\Taxonomy\\Taxonomy';
		$content       = <<<PHP
<?php
namespace {$namespaceDecl};

use {$useDecl};

class {$className} extends Taxonomy
{
	public function __construct()
	{
		parent::__construct();

		// Taxonomy slug
		\$this->taxonomy = '{$slugRoot}-{$slug}';

		// Object type
		\$this->object_type = '{$object_type}';

		// Taxonomy labels
		\$this->labels = array(
			'name'		=> _x( '{$name}', 'Taxonomy general name', '{$textDomain}' )
		);

		// Taxonomy arguments
		\$this->args = array(
			'public'	=> true
		);
	}
}

PHP;

		file_put_contents($filePath, $content);
		$output->writeln("Created Taxonomy class file: $filePath");

		$loaderFile = $baseDir . '/inc/classes.php';
		$instanceLine = sprintf(
		    "new %s\\Taxonomy\\%s;\n",
		    $namespaceRoot,
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

	private function slugify(string $text): string
	{
		$text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
		return strtolower(trim($text, '-'));
	}
}
