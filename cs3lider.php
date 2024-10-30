<?php declare (strict_types = 1);

/*
    Plugin Name: CS^3lider
    Plugin URI: http://plugins.svn.wordpress.org/cs3lider/
    Description: CS^3lider is a WordPress plugin to create very lightweight sliders written in only CSS and HTML.
    Version: 0.0.1
    Author: moxgummy
    Author URI: https://exp.trustring.co.jp/
    License: GPLv3 or later
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

namespace cs3lider;

class Widget extends \WP_Widget {
	public function __construct () { parent::__construct ('cs3lider', 'CS^3lider', ['description' => 'A slider written in CSS and HTML only.']); }
	public function widget ($args, $instance) { echo $args ['before_widget'] . do_shortcode ('[cs3lider /]') . $args ['after_widget']; }
}

add_action ('plugins_loaded', function () {


add_action ('wp_head', function () {
    global $post, $wp_widget_factory;
    if (has_shortcode ($post->post_content, 'cs3lider') || array_key_exists ('\cs3lider\Widget', $wp_widget_factory->widgets))
        echo '<style type="text/css">' . get_option ('cs3lider_css') . '</style>';
});

add_shortcode ('cs3lider', function ($atts, $content = null) { return get_option ('cs3lider_html'); });

add_action ('widgets_init', function() { register_widget ('\cs3lider\Widget'); } );


}); // plugin_loaded


if (is_admin ())
    require plugin_dir_path (__FILE__) . 'cs3lider-admin.php';