<?php
namespace WASP\Shortcode;

use WASP\Helpers\Transient;

/**
 * Shortcode
 *
 * @since 1.1.0
 */
abstract class Shortcode
{

	/**
	 * Shortcode Tag
	 * @access public
	 * @var string
	 *
	 * @since 1.1.0
	 */
	public string $shortcode_tag = '';

	/**
	 * Default attributes for shortcode_atts()
	 * @access public
	 * @var array
	 *
	 * @since 1.1.0
	 */
	public array $defaults = array();

	/**
	 * Assets
	 * Associative array that holds assets to enqueue.
	 *
	 * Structure:
	 *  - css: list of arrays:
	 *      [ handle (string), src (string), deps (array<string>)?, ver (string|null)?, media (string)? ]
	 *  - js: list of arrays:
	 *      [ handle (string), src (string), deps (array<string>)?, ver (string|null)?, in_footer (bool)?, localize (array{object_name:string, l10n:array<string,mixed>})? ]
	 *
	 * Example:
	 *  [
	 *      'css' => [
	 *          ['site-style', '/assets/css/site.css', ['wp-editor'], '1.0.0', 'all'],
	 *      ],
	 *      'js' => [
	 *          ['site-script', '/assets/js/site.js', ['jquery'], '1.0.0', true, ['objectName' => 'Site', 'l10n' => ['key' => 'value']]],
	 *      ],
	 *  ]
	 * @access public
	 * @var array
	 *
	 * @since 1.1.0
	 */
	public array $assets = array(
		'css'	=> array(),
		'js'	=> array()
	);

	/**
	 * Cache lifetime in seconds (0 == disabled)
	 * @access public
	 * @var int
	 *
	 * @since 1.1.0
	 */
	public int $cache_ttl = 0;

	/**
	 * Flag to prevent double enqueue
	 * @access protected
	 * @var bool
	 *
	 * @since 1.1.0
	 */
	protected bool $assets_queued = false;

	/**
	 * Transient
	 *
	 * @since 1.1.0
	 */
	public $transient;

	/**
	 * Constructor
	 *
	 * @since 1.1.0
	 */
	public function __construct()
	{
		add_action( 'init', array( $this, 'register_shortcode' ) );

		$this->transient = new Transient;
	}

	/**
	 * Register shortcode and asset hook
	 *
	 * @since 1.1.0
	 */
	public function register_shortcode() : void
	{
		add_shortcode( $this->shortcode_tag, array( $this, 'shortcode_handler' ) );

		// Instead of hooking enqueue_assets here unconditionally, scan the posts on the front-end
		// and only add the wp_enqueue_scripts hook if the shortcode is present.
		add_filter( 'the_posts', array( $this, 'maybe_add_assets_for_shortcode' ) );
	}

	/**
	 * Scan posts returned by the main query and, if any contains the shortcode,
	 * attach the enqueue_assets() to wp_enqueue_scripts.
	 *
	 * This runs early (before wp_enqueue_scripts) and avoids enqueuing assets on pages
	 * where the shortcode doesn't appear.
	 *
	 * @param array|WP_Post[]|null $posts
	 * @return array|WP_Post[]|null
	 *
	 * @since 1.1.0
	 */
	public function maybe_add_assets_for_shortcode( $posts )
	{
		// Only act on front-end main query; skip admin to be safe.
		if ( is_admin() || empty( $posts ) )
			return $posts;

		foreach ( $posts as $post ) :
			// sometimes objects without post_content might appear; guard as string
			if ( isset( $post->post_content ) && is_string( $post->post_content ) ) :
				if ( has_shortcode( $post->post_content, $this->shortcode_tag ) ) :
					// Hook enqueue once we know the shortcode is present
					add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
					// nothing else to do
					break;
				endif;
			endif;
		endforeach;

		return $posts;
	}

	/**
	 * Main handler passed to add_shortcode
	 * Returns the rendered HTML (string)
	 * @param array $atts
	 * @param string|null $content
	 * @param string $shortcode_tag
	 * @return string
	 *
	 * @since 1.1.0
	 */
	public function shortcode_handler( $atts = array(), $content = null, $shortcode_tag = '' ) : string
	{
		$atts = shortcode_atts(
			$this->defaults,
			(array) $atts,
			$shortcode_tag
		);

		// Fallback: if for some reason the posts-scan didn't detect the shortcode
		// (dynamic insertion, widgets, builders...), ensure assets are enqueued.
		// We only enqueue once (assets_queued flag).
		if ( ! $this->assets_queued )
			// If wp_enqueue_scripts already ran, calling enqueue_assets() here still enqueues assets,
			// WP will print them if printing hasn't already happened; this is the safe fallback.
			$this->enqueue_assets();

		// Cache the output
		if ( $this->cache_ttl > 0 ) :
			$cache_key = $this->get_cache_key( $atts, $content );
			$cached = get_transient( $cache_key );
			if ( $cached !== false  )
				return $cached;

			$output = $this->render( $atts, $content, $shortcode_tag );
			$this->transient::set_transient( $cache_key, $output, $this->cache_ttl );
			return $output;
		endif;

		return $this->render( $atts, $content, $shortcode_tag );
	}

	/**
	 * Enqueue assets declared in $this->assets
	 *
	 * @since 1.1.0
	 */
	public function enqueue_assets() : void
	{
		// Prevent multiple enqueues from different hooks/calls.
		if ( $this->assets_queued )
			return;

		$this->assets_queued = true;

		$css = ( isset( $this->assets['css'] ) ) ? $this->assets['css'] : null;
		$js = ( isset( $this->assets['js'] ) ) ? $this->assets['js'] : null;

		if ( $css ) :
			foreach ( $css as $style ) :
				wp_enqueue_style(
					$style['handle'],
					$style['src'],
					$style['deps'] ?? null,
					$style['ver'] ?? null,
					$style['media'] ?? 'all'
				);
			endforeach;
		endif;

		if ( $js ) :
			foreach ( $js as $script ) :
				wp_enqueue_script(
					$script['handle'],
					$script['src'],
					$script['deps'] ?? null,
					$script['ver'] ?? null,
					$script['in_footer'] ?? true
				);
				if ( isset( $script['localize'] ) ) :
					wp_localize_script(
						$script['handle'],
						$script['localize']['object_name'],
						$script['localize']['l10n']
					);
				endif;
			endforeach;
		endif;
	}

	/**
	 * Generate cache key from attrs + content
	 *
	 * @since 1.1.0
	 */
	public function get_cache_key( array $atts, $content ) : string
	{
		$hash = md5( serialize( $atts ) .'|'. (string) $content );
		return "sc_{$this->shortcode_tag}_{$hash}";
	}

	/**
	 * The render
	 * Should return a string (escaped HTML)
	 * @param array $atts
	 * @param string|null $content
	 * @param string $shortcode_tag
	 * @return string
	 *
	 * @since 1.1.0
	 */
	abstract protected function render( array $atts, $content = null, string $shortcode_tag ) : string;

    /**
     * Optional helper to render a PHP template file
     * @param string $path 	Absolute path to the template
     * @param array $vars 	Associate array extracted to the template
     *
     * @since 1.1.0
     */
    public function render_template( string $path, array $vars = array() ) : string
    {
        if ( ! file_exists( $path ) )
            return '';

        extract( $vars, EXTR_SKIP );
        ob_start();
        include $path;
        return ob_get_clean();
    }
}
