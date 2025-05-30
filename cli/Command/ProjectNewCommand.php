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
