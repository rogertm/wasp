<?php
namespace WaspCli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Console\Command\Command;

class ProjectNewCommand extends AbstractGeneratorCommand
{
	protected static $defaultName = 'project:new';

	protected function configure(): void
	{
		$this
			->setDescription('Creates a new child plugin scaffold that depends on WASP')
			->addArgument('name', InputArgument::REQUIRED, 'Project name (e.g., WASP Child)');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$name = $input->getArgument('name');
		$slug = $this->slugify($name);
		$dirName = $slug;
		$pluginDir = $this->baseDir . '/../' . $dirName;

		if (is_dir($pluginDir)) {
			$output->writeln("<error>Plugin directory already exists: $pluginDir</error>");
			return Command::FAILURE;
		}
		mkdir($pluginDir, 0755, true);

		// Create subdirectories and empty index.php
		$classesDir = array(
			'classes/admin-page',
			'classes/meta-box',
			'classes/post-type',
			'classes/setting-fields',
			'classes/taxonomy',
			'classes/term-meta',
			'classes/user-meta',
		);
		foreach ($classesDir as $sub) {
			$dir = $pluginDir . '/' . $sub;
			mkdir($dir, 0755, true);
			// create empty index.php
			file_put_contents($dir . '/index.php', "<?php\n// Silence is golden\n");
		}

		$incDir =  $pluginDir .'/inc';
		mkdir($incDir, 0755);
		file_put_contents($incDir . '/index.php', "<?php\n// Silence is golden\nrequire plugin_dir_path( __FILE__ ) .'/classes.php';\n");
		file_put_contents($incDir . '/classes.php', "<?php\n// Instantiate your classes here\n");

		$autoloader = $pluginDir .'/autoloader.php';
		$content 	= <<<PHP
<?php
/**
 * Class Autoloader 101
 */
spl_autoload_register( function( \$class ){
	\$directories = glob( plugin_dir_path( __FILE__ ) .'classes/*', GLOB_ONLYDIR );

	\$parts = explode( '\\\', \$class );
	\$class_name = end( \$parts );

	foreach ( \$directories as \$dir ) :
		\$file = \$dir .'/class-{$slug}-'. str_replace( '_', '-', strtolower( \$class_name ) ) .'.php';

		if ( file_exists( \$file ) ) :
			require_once \$file;
			break;
		endif;
	endforeach;
} );

PHP;
		file_put_contents($autoloader, $content);

		$mainFile = $pluginDir . '/'. $slug .'.php';
		$textDomain = $slug;
		$header = <<<PHP
<?php
/**
 * Plugin Name: {$name}
 * Description: Wow! Another starter "Child" plugin
 * Plugin URI: https://github.com/rogertm/wasp
 * Author: RogerTM
 * Author URI: https://rogertm.com
 * Version: 1.0.0
 * License: GPL2
 * Text Domain: {$textDomain}
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( file_exists( WP_PLUGIN_DIR . '/{$this->slugRoot}/{$this->slugRoot}.php' ) ) {
	require WP_PLUGIN_DIR . '/{$this->slugRoot}/{$this->slugRoot}.php';
	require plugin_dir_path( __FILE__ ) .'/autoloader.php';
	require plugin_dir_path( __FILE__ ) .'/inc/index.php';
} else {
	wp_die( __( 'This plugin requires WASP', '{$textDomain}' ), __( 'Bum! 💣', '{$textDomain}' ) );
}

/** Your code goes here 😎 */
PHP;
		file_put_contents($mainFile, $header);
		$output->writeln("Created plugin scaffold: $mainFile");
		$output->writeln('<info>Done!</info>');
		return Command::SUCCESS;
	}
}
