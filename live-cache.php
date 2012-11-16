<?php

/*
 * Handles ajax requests with cached values as early as possible.
 * 
 * Note: All values stored and passed by this plugin are sanitized using sanitize_text_field
 * If you need to send something more complex, consider using this as a flag to trigger
 * an ajax request or a refresh.
 * 
 * 
 */

class Live_Cache {
	// Check for live_cache_check on init. If set return cached value
	public function __construct() {

		/* By running this check as soon as the class is initialized instead of on action,
		 * we can skip most of the theme being loaded.
		 */
		if ( isset( $_GET['live_cache_check'] ) && $_GET['live_cache_check'] ) {

			// js esc instead of sanitize?
			$live_cache = array_map ( 'sanitize_text_field', (array) get_option( 'live_cache' ) );
			//$live_cache['ts'] = time();

			// output content and die here
			header( 'Content-Type: application/json' );
			nocache_headers(); //essential for getting date that is used for incrementing the timestamp
			echo json_encode( $live_cache );
			die();
		}
		
		// This was not a live-cache check. So let's set up the rest of the class and let the rest of the theme load.

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	public function init() {
		// embed the javascript file that makes the AJAX request
		wp_enqueue_script( 'live-cache', plugin_dir_url( __FILE__ ) . 'live-cache.js', array( 'jquery' ) );

		// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
		wp_localize_script( 'live-cache', 'Live_Cache', array( 'ajaxurl' => get_bloginfo('url') ) );

		$live_cache = get_option( 'live_cache' );

		// Set value. Create option if necessary.
		if ( is_array( $live_cache ) ) { 
			update_option( 'live_cache', apply_filters( 'live_cache_values', $live_cache ) );
		} else {
			add_option( 'live_cache', apply_filters( 'live_cache_values', $live_cache ), '', true );
		}
	}

	public function admin_init() {
		// add refresh rate option to the reading settings page
		register_setting( 'reading', 'live_cache_refresh_rate', array( $this, 'sanitize_options' ) ); //live_cache_check includes all of the values we are passing back thru ajax including the refresh rate controled here.
		add_settings_section( 'live-cache', '', '__return_false', 'reading' );
		add_settings_field( 'refresh_rate', 'Live Cache Refresh Rate', array( $this, 'settings_field_refresh_rate' ), 'reading', 'live-cache' );
	}

	public function settings_field_refresh_rate() {
		$live_cache_refresh_rate = get_option( 'live_cache_refresh_rate' );
	?>
		<input type="text" name="live_cache_refresh_rate" id="live_cache_refresh_rate" class="regular-text" value="<?php echo (int) $live_cache_refresh_rate; ?>" />
		<p class="description">Number of seconds between ajax calls. You can set this as low as 60 seconds for live events. Make sure to increase it back, though.</p>
	<?php
	}

	public function sanitize_options( $input ) {
		$new_input = (int) $input;
		if ( $new_input < 2 ) 
			$new_input = 2;
		
		$live_cache = (array) get_option( 'live_cache' );
		$live_cache['refresh_rate'] = $new_input;
		update_option( 'live_cache', $live_cache );

		return $new_input;
	}

}

$live_cache = new Live_Cache();




// Usage
function tc_demo_live_cache ( $values ) {
	$values['time'] = rand(1,12)."pm";
	return $values;
}
add_filter( 'live_cache_values', 'tc_demo_live_cache', 10, 1 );

function tc_demo_live_cache_print_footer_scripts() {
		?>
<script>
TC_Live_Cache.setCallback('time', function(value){
	console.log('Still working: '+value);
});
</script>
		<?php
	}

add_action( 'wp_print_footer_scripts', 'tc_demo_live_cache_print_footer_scripts');