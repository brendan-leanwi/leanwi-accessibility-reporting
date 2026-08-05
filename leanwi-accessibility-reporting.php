<?php

namespace LeanwiAccessibility;

/*
Plugin Name: LEANWI Accessibility Reporting
GitHub URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Update URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Description: Functionality to aid reporting on accessibility for your entire site.
Version: 1.2.2
Author: Brendan Tuckey
Author URI:   https://github.com/brendan-leanwi
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  leanwi-tutorial
Domain Path:  /languages
Tested up to: 7.0.2
*/

// Define plugin constants
define('LEANWI_AR_PATH', plugin_dir_path(__FILE__));
define('LEANWI_AR_URL', plugin_dir_url(__FILE__));

require_once LEANWI_AR_PATH . 'includes/db-setup.php';
require_once LEANWI_AR_PATH . 'includes/render-site-scan-page.php';
require_once LEANWI_AR_PATH . 'includes/render-focused-content-report-page.php';
require_once LEANWI_AR_PATH . 'includes/render-site-notes-page.php';
require_once LEANWI_AR_PATH . 'includes/render-site-ignores-page.php';
require_once LEANWI_AR_PATH . 'includes/render-site-review-request-page.php';
require_once LEANWI_AR_PATH . 'includes/take-snapshot.php';
require_once LEANWI_AR_PATH . 'includes/routes.php';
require_once LEANWI_AR_PATH . 'includes/latest-snapshot-endpoint.php';
require_once LEANWI_AR_PATH . 'includes/plugin-updater.php';

// Register activation hook to create database tables
register_activation_hook( __FILE__, __NAMESPACE__ . '\\leanwi_accessibility_create_tables' );

// Version-based update check
function leanwi_update_check() {
    $current_version = get_option('leanwi_accessibility_reporting_plugin_version', '1.7'); // Default to an old version if not set
    $new_version = '1.2.2'; // Update this with the new plugin version

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

//Focused content report page menu item etc
add_action('admin_menu', function () {
    add_submenu_page(
        'accessibility_checker',
        'Focused Content Report',
        'Focused Content Report',
        'edit_posts',
        'leanwi-focused-content-report',
        'leanwi_render_focused_content_report_page'
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

//Site ignores page menu item etc
add_action('admin_menu', function () {
    add_submenu_page(
        'accessibility_checker',        // Correct parent slug
        'Site Ignores',            // Page title
        'Site Ignores',                    // Menu title
        'manage_options',               // Capability
        'leanwi-site-ignores',             // Menu slug
        'leanwi_render_site_ignores_page'  // Callback function
    );
});

//Ask for Review page menu item etc
add_action('admin_menu', function () {
    add_menu_page(
        'Ask for Review',   // Page title (for the parent menu)
        'Ask for Review',     // Menu title (for the plugin name in the dashboard)
        'manage_options',         // Capability
        'leanwi-site-review-request-main', // Menu slug
        'leanwi_render_site_review_request_main_page',       // Callback function
        'dashicons-email-alt2',     // Menu icon (optional)
        6                         // Position
    );
    add_submenu_page(
        'accessibility_checker',        // Correct parent slug
        'Ask for an Accessibility Review',            // Page title
        'Ask for Review',                    // Menu title
        'manage_options',               // Capability
        'leanwi-site-review-request',             // Menu slug
        'leanwi_render_site_review_request_page'  // Callback function
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

        /*
        wp_localize_script('leanwi-accessibility-make-snapshot', 'leanwiAccessibility', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('leanwi_accessibility_nonce'),
        ]);
        */

        $rest_nonce = wp_create_nonce('wp_rest');

        wp_localize_script('leanwi-accessibility-make-snapshot', 'wpApiSettings', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => $rest_nonce,
        ]);

        // Also keep your plugin-specific object if needed
        wp_localize_script('leanwi-accessibility-make-snapshot', 'leanwiAccessibility', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => $rest_nonce,
        ]);

    }

    if (isset($_GET['page']) && $_GET['page'] === 'leanwi-focused-content-report') {
        wp_enqueue_style(
            'leanwi-focused-content-report',
            plugin_dir_url(__FILE__) . 'assets/focused-content-report.css',
            [],
            '1.1.5'
        );

        $script_dependencies = [];
        $tesseract_url = apply_filters(
            'leanwi_accessibility_tesseract_js_url',
            'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'
        );

        if (!empty($tesseract_url)) {
            wp_enqueue_script(
                'leanwi-tesseract-js',
                esc_url_raw($tesseract_url),
                [],
                '5.0.0',
                true
            );
            $script_dependencies[] = 'leanwi-tesseract-js';
        }

        wp_enqueue_script(
            'leanwi-focused-content-report',
            plugin_dir_url(__FILE__) . 'assets/focused-content-report.js',
            $script_dependencies,
            '1.1.5',
            true
        );

        wp_localize_script('leanwi-focused-content-report', 'leanwiFocusedReport', [
            'ocrMinWords' => 10,
        ]);
    }
}
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\leanwi_accessibility_enqueue_admin_scripts');

function leanwi_accessibility_enqueue_frontend_highlight() {
    if (empty($_GET['leanwi_acr_highlight']) || empty($_GET['leanwi_acr_post']) || empty($_GET['leanwi_acr_locator']) || empty($_GET['leanwi_acr_nonce'])) {
        return;
    }

    $post_id = absint($_GET['leanwi_acr_post']);
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_GET['leanwi_acr_nonce']));
    if (!wp_verify_nonce($nonce, 'leanwi_acr_highlight_' . $post_id)) {
        return;
    }

    $encoded_locator = sanitize_text_field(wp_unslash($_GET['leanwi_acr_locator']));
    $encoded_locator = strtr($encoded_locator, '-_', '+/');
    $encoded_locator .= str_repeat('=', (4 - strlen($encoded_locator) % 4) % 4);
    $locator_json = base64_decode($encoded_locator, true);
    if (!$locator_json) {
        return;
    }

    $locator = json_decode($locator_json, true);
    if (!is_array($locator) || empty($locator['tag'])) {
        return;
    }

    wp_enqueue_style(
        'leanwi-focused-content-highlight',
        plugin_dir_url(__FILE__) . 'assets/focused-content-highlight.css',
        [],
        '1.1.5'
    );

    wp_enqueue_script(
        'leanwi-focused-content-highlight',
        plugin_dir_url(__FILE__) . 'assets/focused-content-highlight.js',
        [],
        '1.1.5',
        true
    );

    wp_localize_script('leanwi-focused-content-highlight', 'leanwiFocusedHighlight', [
        'locator' => $locator,
    ]);
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\leanwi_accessibility_enqueue_frontend_highlight');

// AJAX callback for fetching latest items dynamically
function leanwi_get_latest_items_ajax() {
    check_ajax_referer('leanwi_nonce', 'nonce');

    $num_items = isset($_POST['num_items']) ? intval($_POST['num_items']) : 10;
    global $wpdb;

    $results = $wpdb->get_results(
        $wpdb->prepare("
            SELECT post_title, post_type, post_modified
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
              AND post_type IN ('post','page')
            ORDER BY post_modified DESC
            LIMIT %d
        ", $num_items)
    );

    $items = "The latest files changed include:\n";
    foreach ($results as $row) {
        $items .= sprintf(
            "- %s (%s), last modified %s\n",
            $row->post_title ?: '(no title)',
            ucfirst($row->post_type),
            date('Y-m-d H:i', strtotime($row->post_modified))
        );
    }

    wp_send_json_success($items);
}
add_action('wp_ajax_leanwi_get_latest_items', __NAMESPACE__ . '\\leanwi_get_latest_items_ajax');
