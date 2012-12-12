<?php

class Live_Cache_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array(
			'classname' => 'live_cache_widget',
			'description' => __('The contents of this widget will be updated on all open pages within a minute of you saving it here. This will happen automatically without anyone needing to refresh their browser.')
		);
		$control_ops = array(
			'width' => 400,
			'height' => 350
		);
		parent::__construct('live-cache-widget', __('Live Cache Widget'), $widget_ops, $control_ops);

		add_filter( 'live_cache_auto_updates', array( $this, 'live_cache_auto_updates' ) );
	}

	function widget( $args, $instance ) {
		wp_parse_args( $args, array( 'before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => '' ) );
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		$text = apply_filters( 'widget_text', empty( $instance['text'] ) ? '' : $instance['text'], $instance );
		echo $args['before_widget'];
		if ( !empty( $title ) ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		?>
			<div class="livecachewidget"><?php echo !empty( $instance['filter'] ) ? wpautop( $text ) : $text; ?></div>
		<?php
		echo $args['after_widget'];
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		if ( current_user_can('unfiltered_html') ) {
			$instance['text'] = $new_instance['text'];
		} else {
			$instance['text'] = stripslashes( wp_filter_post_kses( addslashes($new_instance['text']) ) ); // wp_filter_post_kses() expects slashed
		}
		$instance['filter'] = isset( $new_instance['filter'] );
		// if live cache plugin is available, this will update values sent to the front-end
		do_action( 'live_cache_set_value', $this->id . '-title', $instance['title'] );
		do_action( 'live_cache_set_value', $this->id . '-text', $instance['text'] );
		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'text' => '' ) );
		$title = strip_tags( $instance['title'] );
		$text = esc_textarea( $instance['text'] );
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></p>

		<textarea class="widefat" rows="16" cols="20" id="<?php echo $this->get_field_id('text'); ?>" name="<?php echo $this->get_field_name('text'); ?>"><?php echo $text; ?></textarea>

		<p><input id="<?php echo $this->get_field_id('filter'); ?>" name="<?php echo $this->get_field_name('filter'); ?>" type="checkbox" <?php checked(isset($instance['filter']) ? $instance['filter'] : 0); ?> />&nbsp;<label for="<?php echo $this->get_field_id('filter'); ?>"><?php _e('Automatically add paragraphs'); ?></label></p>
<?php
	}

	// register two places per widget instance on the front-end where data should be updated when it changes.
	function live_cache_auto_updates( $updates ) {
		$updates[ $this->id . '-title' ] = "#$this->id h2";
		$updates[ $this->id . '-text' ] = "#$this->id div";

		return $updates;
	}
}

function live_cache_widgets_init() {
	register_widget('Live_Cache_Widget');
}

add_action('widgets_init', 'live_cache_widgets_init');
