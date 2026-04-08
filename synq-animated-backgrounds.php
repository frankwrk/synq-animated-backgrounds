<?php

/**
 * Plugin Name:       SYNQ Animated Backgrounds for Elementor
 * Description:       Generic animated backgrounds for Elementor Containers (v0.1 ships with Vanta Topology).
 * Version:           0.1.0
 * Author:            SYNQ Group
 * Text Domain:       synq-animated-backgrounds
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SYNQ_AB_PLUGIN_VERSION', '0.1.0');
define('SYNQ_AB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SYNQ_AB_PLUGIN_URL', plugin_dir_url(__FILE__));

// Includes
require_once SYNQ_AB_PLUGIN_PATH . 'includes/class-provider-interface.php';
require_once SYNQ_AB_PLUGIN_PATH . 'includes/providers/class-provider-vanta-topology.php';
require_once SYNQ_AB_PLUGIN_PATH . 'includes/class-plugin.php';

// Bootstrap
add_action('plugins_loaded', function () {
    // Optional: ensure Elementor exists
    if (! did_action('elementor/loaded')) {
        // Elementor not active, bail silently
        return;
    }

    new SYNQ_Animated_Backgrounds_Plugin();
});