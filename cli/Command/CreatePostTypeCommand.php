<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreatePostTypeCommand extends Command
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
		$baseDir    = realpath(__DIR__ . '/../../');
		$configPath = $baseDir . '/cli/config.json';

		$config        = json_decode(file_get_contents($configPath), true);
		$namespaceRoot = $config['namespace'];
		$slugRoot      = $config['slug'];
		$textDomain    = $config['text_domain'];

		$name        = $input->getArgument('name');
		$slug        = $this->slugify($name);
		$classSuffix = str_replace('-', '_', ucwords($slug, '-'));
		$className   = 'Post_Type_' . $classSuffix;
		$fileName    = "class-{$slugRoot}-post-type-{$slug}.php";

		$targetDir = $baseDir . '/classes/post-type';
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}
		$filePath = $targetDir . '/' . $fileName;

		// Check if file already exists to avoid overwriting
		if (file_exists($filePath)) {
			$output->writeln("<error>File already exists: $filePath</error>");
			return Command::FAILURE;
		}

		$namespaceDecl = $namespaceRoot . '\\Post_Type';
		$useDecl       = $namespaceRoot . '\\Posts\\Post_Type';
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
		\$this->post_type = '{$slugRoot}-{$slug}';

		// CPT labels
		\$this->labels = array(
			'name'		=> _x( '{$name}', 'Post type general name', '{$textDomain}' )
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
		$output->writeln('<info>Done!</info>');
		return Command::SUCCESS;
	}

	private function slugify(string $text): string
	{
		$text = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text);
		return strtolower(trim($text, '-'));
	}
}
