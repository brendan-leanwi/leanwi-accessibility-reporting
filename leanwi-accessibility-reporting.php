<?php

namespace LeanwiAccessibility;

/*
Plugin Name: LEANWI Accessibility Reporting
GitHub URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Update URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Description: Functionality to aid reporting on accessibility for your entire site.
Version: 1.0.3
Author: Brendan Tuckey
Author URI:   https://github.com/brendan-leanwi
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  leanwi-tutorial
Domain Path:  /languages
Tested up to: 6.8.1
*/

// Define plugin constants
define('LEANWI_AR_PATH', plugin_dir_path(__FILE__));
define('LEANWI_AR_URL', plugin_dir_url(__FILE__));

require_once LEANWI_AR_PATH . 'includes/db-setup.php';
require_once LEANWI_AR_PATH . 'includes/render-site-scan-page.php';
require_once LEANWI_AR_PATH . 'includes/render-site-notes-page.php';
require_once LEANWI_AR_PATH . 'includes/take-snapshot.php';
require_once LEANWI_AR_PATH . 'includes/routes.php';
require_once LEANWI_AR_PATH . 'includes/plugin-updater.php';

// Register activation hook to create database tables
register_activation_hook( __FILE__, __NAMESPACE__ . '\\leanwi_accessibility_create_tables' );

// Version-based update check
function leanwi_update_check() {
    $current_version = get_option('leanwi_accessibility_reporting_plugin_version', '1.0.1'); // Default to an old version if not set
    $new_version = '1.0.3'; // Update this with the new plugin version

    if (version_compare($current_version, $new_version, '<')) {
        // Run the table creation logic
        leanwi_accessibility_create_tables();

        // Update the version in the database
        update_option('leanwi_accessibility_reporting_plugin_version', $new_version);
    }
}
add_action('admin_init', __NAMESPACE__ . '\\leanwi_update_check');

//Site scan page menu item etc
add_action('admin_menu', function () {
    add_submenu_page(
        'accessibility_checker',        // Correct parent slug
        'Site Scan Summary',            // Page title
        'Site Scan',                    // Menu title
        'manage_options',               // Capability
        'leanwi-site-scan',             // Menu slug
        'leanwi_render_site_scan_page'  // Callback function
    );
});

//Site notes page menu item etc
add_action('admin_menu', function () {
    add_submenu_page(
        'accessibility_checker',        // Correct parent slug
        'Site Scan Notes',            // Page title
        'Site Notes',                    // Menu title
        'manage_options',               // Capability
        'leanwi-site-notes',             // Menu slug
        'leanwi_render_site_notes_page'  // Callback function
    );
});

function leanwi_accessibility_enqueue_admin_scripts($hook) {
    // Load only if current page is the Site Scan page
    if (isset($_GET['page']) && $_GET['page'] === 'leanwi-site-scan') {
        wp_enqueue_script(
            'leanwi-accessibility-make-snapshot',
            plugin_dir_url(__FILE__) . 'assets/admin-make-snapshot.js',
            ['jquery'],
            '1.0',
            true
        );

        wp_localize_script('leanwi-accessibility-make-snapshot', 'leanwiAccessibility', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('leanwi_accessibility_nonce'),
        ]);
    }
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\leanwi_accessibility_enqueue_admin_scripts');

