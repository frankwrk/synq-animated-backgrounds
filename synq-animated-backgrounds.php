<?php

/**
 * Plugin Name:       SYNQ Animated Backgrounds for Elementor
 * Description:       Generic animated backgrounds for Elementor Containers (v0.3 ships with Vanta Topology + Trunk).
 * Version:           0.3.4
 * Author:            SYNQ Group
 * Text Domain:       synq-animated-backgrounds
 * Requires at least: 6.4
 * Requires PHP:      7.2
 * Tested up to:      6.9.4
 * Requires Plugins:  elementor
 * Update URI:        https://github.com/frankwrk/synq-animated-backgrounds
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SYNQ_AB_PLUGIN_VERSION', '0.3.4');
define('SYNQ_AB_PLUGIN_FILE', __FILE__);
define('SYNQ_AB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SYNQ_AB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SYNQ_AB_MIN_WP_VERSION', '6.4');
define('SYNQ_AB_MIN_PHP_VERSION', '7.2');
define('SYNQ_AB_MIN_ELEMENTOR_VERSION', '3.24.0');
define('SYNQ_AB_GITHUB_REPOSITORY', 'frankwrk/synq-animated-backgrounds');

/**
 * Normalize plugin dependency versions for minimum checks.
 *
 * Strips prerelease/build suffixes so values like "4.0.0-dev4" are treated as
 * "4.0.0" when evaluating minimum supported versions.
 */
function synq_ab_normalize_dependency_version(string $version): string {
    if (preg_match('/^\d+(?:\.\d+){0,2}/', $version, $matches)) {
        return $matches[0];
    }

    return $version;
}

/**
 * Return an array of compatibility issues.
 *
 * @return string[]
 */
function synq_ab_get_compatibility_issues(): array {
    global $wp_version;

    $issues = [];

    if (version_compare(PHP_VERSION, SYNQ_AB_MIN_PHP_VERSION, '<')) {
        $issues[] = sprintf(
            /* translators: 1: current version, 2: required version */
            __('SYNQ Animated Backgrounds requires PHP %2$s or higher. Current PHP version: %1$s.', 'synq-animated-backgrounds'),
            PHP_VERSION,
            SYNQ_AB_MIN_PHP_VERSION
        );
    }

    if (version_compare($wp_version, SYNQ_AB_MIN_WP_VERSION, '<')) {
        $issues[] = sprintf(
            /* translators: 1: current version, 2: required version */
            __('SYNQ Animated Backgrounds requires WordPress %2$s or higher. Current WordPress version: %1$s.', 'synq-animated-backgrounds'),
            $wp_version,
            SYNQ_AB_MIN_WP_VERSION
        );
    }

    if (! class_exists('\Elementor\Plugin') && ! defined('ELEMENTOR_VERSION')) {
        $issues[] = __('SYNQ Animated Backgrounds requires Elementor to be active.', 'synq-animated-backgrounds');
    } elseif (defined('ELEMENTOR_VERSION') && version_compare(
        synq_ab_normalize_dependency_version(ELEMENTOR_VERSION),
        synq_ab_normalize_dependency_version(SYNQ_AB_MIN_ELEMENTOR_VERSION),
        '<'
    )) {
        $issues[] = sprintf(
            /* translators: 1: current version, 2: required version */
            __('SYNQ Animated Backgrounds requires Elementor %2$s or higher. Current Elementor version: %1$s.', 'synq-animated-backgrounds'),
            ELEMENTOR_VERSION,
            SYNQ_AB_MIN_ELEMENTOR_VERSION
        );
    }

    return $issues;
}

/**
 * Render compatibility notices in wp-admin.
 *
 * @param string[] $issues Validation errors.
 */
function synq_ab_render_admin_notice(array $issues): void {
    if (! current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p><strong>' .
        esc_html__('SYNQ Animated Backgrounds was not loaded due to compatibility requirements:', 'synq-animated-backgrounds') .
        '</strong></p><ul style="margin-left:1.4em;list-style:disc;">';

    foreach ($issues as $issue) {
        echo '<li>' . esc_html($issue) . '</li>';
    }

    echo '</ul></div>';
}

/**
 * Plugin bootstrap.
 */
add_action('plugins_loaded', function () {
    $issues = synq_ab_get_compatibility_issues();

    if (! empty($issues)) {
        add_action('admin_notices', function () use ($issues) {
            synq_ab_render_admin_notice($issues);
        });

        return;
    }

    require_once SYNQ_AB_PLUGIN_PATH . 'includes/class-provider-interface.php';
    require_once SYNQ_AB_PLUGIN_PATH . 'includes/class-github-updater.php';
    require_once SYNQ_AB_PLUGIN_PATH . 'includes/providers/class-provider-vanta-topology.php';
    require_once SYNQ_AB_PLUGIN_PATH . 'includes/providers/class-provider-vanta-trunk.php';
    require_once SYNQ_AB_PLUGIN_PATH . 'includes/class-plugin.php';

    add_action('init', function () {
        load_plugin_textdomain(
            'synq-animated-backgrounds',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    });

    new SYNQ_AB_GitHub_Updater(SYNQ_AB_PLUGIN_FILE, SYNQ_AB_PLUGIN_VERSION);
    new SYNQ_Animated_Backgrounds_Plugin();
});
