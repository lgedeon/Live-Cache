<?php

/*
 * Handles ajax requests with cached values as early as possible. Uses a queryvar instead of
 * standard wp ajax method to allow page caching, refresh the cache every 10 seconds, and to
 * process requests as early as possible.
 * 
 * Installation suggestion: Instead of activating this plugin normally, call it from the top
 * of the theme's functions.php for even faster response and less load on the server.
 * Use: require_once( path/to/plugin/live-cache/live-cache.php' );
 * However this will still work when activated normally.
 * 
 * See live-cache-widget.php for a usage sample. Also note how refresh rate is set inside
 * sanitize_options() and picked up at the bottom of live-cache.js for a second method.
 */

class Live_Cache {

	/**
	 * Set to false with the 'live_cache_show_options' filter if you want to handle things differently
	 *
	 * @var bool
	 */
	var $show_options;

	/**
	 * Set to false with the 'live_cache_show_options' filter if you want to handle things differently
	 *
	 * @var bool
	 */
	var $show_widget;

	/**
	 * If you lower this value (using the 'live_cache_min_refresh_rate' filter, check minRefresh in live-cache.js
	 *
	 * @var int
	 */
	var $minimum_refresh_rate; // if you lower this here, check minRefresh in live-cache.js

	/**
	 * Data container
	 *
	 * @var bool|array
	 */
	var $data = false;

	/**
	 * Flag whether or not we need to save the cache, since flushing can be expensive.
	 *
	 * @var bool
	 */
	private $needs_save = false;

	/**
	 * Check for live_cache_check on init. If set return cached value
	 *
	 * @uses apply_filters()
	 * @uses add_action()
	 * @uses add_filter()
	 */
	public function __construct() {
		// Setup default options
		$this->show_options = apply_filters( 'live_cache_show_options', true );
		$this->show_widget = apply_filters( 'live_cache_show_widget', true );
		$this->minimum_refresh_rate = (int) apply_filters( 'live_cache_min_refresh_rate', 60 );

		/* By running this check as soon as the class is initialized instead of on action,
		 * we can skip most of the theme being loaded. ouput and die.
		 */
		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'live_cache_check' ) ) {
			header( 'Content-Type: application/json' );
			nocache_headers(); //essential for getting date that is used for incrementing the timestamp
			echo json_encode( (array) $this->get_cache() );
			die();
		}
		
		// ok, this was not a live-cache check. so let's set up the rest of the class and let the rest of the theme load.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		/* This one is a bit unusual. We are making it possible to plug-in to this plugin by
		 * reversing the hook and allowing the other plugin to trigger an action that we process.
		 */
		add_action( 'live_cache_set_value', array( $this, 'live_cache_set_value' ), 10, 2 );
		add_filter( 'live_cache_get_value', array( $this, 'live_cache_get_value' ), 10, 2 );
		add_action( 'shutdown', array( $this, 'live_cache_persist' ) );

		// Demo code and a small widget to make this plug-in do something fresh out of the box
		if ( $this->show_widget ) {
			require_once( __DIR__ . '/live-cache-widget.php' );
		}
	}

	/**
	 * Add scripts and localized variables to the admin.
	 *
	 * @uses wp_enqueue_script()
	 * @uses wp_localize_script()
	 */
	public function wp_enqueue_scripts() {
		// embed the javascript file that makes the AJAX request
		wp_enqueue_script( 'live-cache', plugin_dir_url( __FILE__ ) . 'live-cache.js', array( 'jquery' ) );

		/* pass in the url where we will be checking for updates along with a list of keys to monitor and where
		 * to put their values. filter live_cache_auto_updates to set a key and selector. see live-cache-widget.php
		 * for sample usage. wp_localize_script handles output sanitization for us. don't double escape. do verify
		 * you are passing valid input.
		 * 
		 * Note: no point in passing a timestamp here. It will get cached and be as out of date as the rest. that is
		 * fine. we are getting timestamp from http response headers.
		 */
		wp_localize_script( 'live-cache', 'Live_Cache', array( 'ajaxurl' => get_bloginfo( 'url' ), 'auto_updates' => apply_filters( 'live_cache_auto_updates', array() ) ) );
	}

	/**
	 * Initialize rewrite rules.
	 *
	 * @uses add_rewrite_rule()
	 */
	public function init() {
		add_rewrite_rule( '^live_cache_check/([\d]+)/?', 'index.php?live_cache_check=$matches[0]', 'top' );
	}

	/**
	 * Initialize the admin interface.
	 *
	 * @uses register_setting()
	 * @uses add_settings_section()
	 * @uses add_settings_field()
	 */
	public function admin_init() {
		if ( $this->show_options ) {
			// add refresh rate option to the reading settings page
			register_setting( 'reading', 'live_cache_refresh_rate', array( $this, 'sanitize_options' ) ); //live_cache_check includes all of the values we are passing back thru ajax including the refresh rate controled here.
			add_settings_section( 'live-cache', '', '__return_false', 'reading' );
			add_settings_field( 'refresh_rate', 'Live Cache Refresh Rate', array( $this, 'settings_field_refresh_rate' ), 'reading', 'live-cache' );
		}
	}

	/**
	 * Store a value in the cache.
	 *
	 * @uses sanitize_key()
	 * @uses sanitize_text_field()
	 *
	 * @param string $key   Name of the cache key to set.
	 * @param mixed  $value Value to cache.
	 */
	public function live_cache_set_value( $key, $value ) {
		$live_cache = $this->get_cache();
		$key = sanitize_key( $key );

		// Set value. Create option if necessary.
		if ( is_array( $live_cache ) ) { 
			$live_cache[$key] = sanitize_text_field( $value );
		} else {
			$live_cache = array( $key => sanitize_text_field( $value ) );
		}

		$this->data = $live_cache;
		$this->needs_save = true;
	}

	/**
	 * Retrieve a value from the cache if it exists
	 *
	 * @param string $key   Cached value to retrieve
	 * @param null   $value Default value to return if a value is not set in the cache.
	 *
	 * @return mixed|null
	 */
	public function live_cache_get_value( $key, $value = null ) {
		$live_cache = $this->get_cache();
		$key = sanitize_key( $key );

		// Get value if available.
		if ( isset( $live_cache[ $key ] ) ) {
			$value = $live_cache[ $key ];
		}

		return $value;
	}

	/**
	 * Get the Live Cache data either from the advanced cache or the database.
	 *
	 * @return bool|array False on failure.
	 */
	protected function get_cache() {
		if ( false === $this->data ) {
			$this->data = wp_cache_get( 'live_cache' );

			if ( false === $this->data ) {
				$this->data = get_option( 'live_cache' );

				if ( false === $this->data ) {
					$this->data = array();
				} else {
					wp_cache_set( 'live_cache', $this->data );
				}
			}
		}

		return $this->data;
	}

	/**
	 * Finally save the data in the cache on shutdown (so we only save once).
	 */
	public function live_cache_persist() {
		// If we don't need to save, then skip
		if ( ! $this->needs_save ) {
			return;
		}

		// Flush saved data.
		delete_option( 'live_cache' );

		// Store the data
		wp_cache_set( 'live_cache', $this->data );
		add_option( 'live_cache', $this->data, '', 'no' );
	}

	/**
	 * Render the refresh rate settings field.
	 */
	public function settings_field_refresh_rate() {
		$live_cache_refresh_rate = (int) get_option( 'live_cache_refresh_rate' );
		if ( $live_cache_refresh_rate < $this->minimum_refresh_rate ) 
			$live_cache_refresh_rate = $this->minimum_refresh_rate;
	?>
		<input type="text" name="live_cache_refresh_rate" id="live_cache_refresh_rate" class="regular-text" value="<?php echo $live_cache_refresh_rate; ?>" />
		<p class="description">Number of seconds between ajax calls. You can set this as low as 60 seconds for live events. Make sure to increase it back, though.</p>
	<?php
	}

	/**
	 * Sanitize the minimum refresh rate option.
	 *
	 * @param int $input
	 *
	 * @return int
	 */
	public function sanitize_options( $input ) {
		$new_input = (int) $input;
		if ( $new_input < $this->minimum_refresh_rate ) {
			$new_input = $this->minimum_refresh_rate;
		}

		// do live_cache_set_value action - same method that could be used by other plugins that extend this plugin.
		do_action( 'live_cache_set_value', 'refresh_rate', $new_input );

		return $new_input;
	}

}

$live_cache = new Live_Cache();
