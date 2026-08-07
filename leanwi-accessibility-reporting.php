<?php

namespace LeanwiAccessibility;

/*
Plugin Name: LEANWI Accessibility Reporting
GitHub URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Update URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Description: Functionality to aid reporting on accessibility for your entire site.
Version: 1.3.0
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
define('LEANWI_AR_VERSION', '1.3.0');

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
    $current_version = get_option('leanwi_accessibility_reporting_plugin_version', '1.0.6'); // Default to an old version if not set
    $new_version = LEANWI_AR_VERSION; // Update this with the new plugin version

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

// Frontend accessibility fixes settings page.
add_action('admin_menu', function () {
    add_submenu_page(
        'accessibility_checker',
        'Frontend Accessibility Fixes',
        'Frontend Fixes',
        'manage_options',
        'leanwi-frontend-fixes',
        __NAMESPACE__ . '\\leanwi_render_frontend_fixes_page'
    );
});

function leanwi_frontend_fixes_option_name() {
    return 'leanwi_accessibility_frontend_fixes_enabled';
}

function leanwi_frontend_fixes_default_enabled() {
    return (bool) apply_filters('leanwi_accessibility_frontend_fixes_default_enabled', false);
}

function leanwi_frontend_fixes_enabled() {
    $default = leanwi_frontend_fixes_default_enabled() ? '1' : '0';
    $value = get_option(leanwi_frontend_fixes_option_name(), $default);
    $enabled = in_array($value, ['1', 1, true, 'true', 'yes', 'on'], true);

    return (bool) apply_filters('leanwi_accessibility_frontend_fixes_enabled', $enabled);
}

function leanwi_handle_frontend_fixes_settings() {
    if (empty($_POST['leanwi_frontend_fixes_settings'])) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to update these settings.', 'leanwi-tutorial'));
    }

    check_admin_referer('leanwi_frontend_fixes_settings');

    $enabled = !empty($_POST['leanwi_frontend_fixes_enabled']) ? '1' : '0';
    update_option(leanwi_frontend_fixes_option_name(), $enabled);

    wp_safe_redirect(add_query_arg([
        'page' => 'leanwi-frontend-fixes',
        'settings-updated' => 'true',
    ], admin_url('admin.php')));
    exit;
}
add_action('admin_init', __NAMESPACE__ . '\\leanwi_handle_frontend_fixes_settings');

function leanwi_render_frontend_fixes_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to view this page.', 'leanwi-tutorial'));
    }

    $enabled = leanwi_frontend_fixes_enabled();
    $scripts = leanwi_frontend_fix_scripts();
    ?>
    <div class="wrap">
        <h1>Frontend Accessibility Fixes</h1>

        <?php if (!empty($_GET['settings-updated'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Frontend accessibility fix settings saved.</p>
            </div>
        <?php endif; ?>

        <p>
            These plugin-managed fixes replace the matching Divi child theme accessibility CSS and JavaScript handles.
            Leave this disabled until you are ready for this plugin to take over those front-end fixes on this site.
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=leanwi-frontend-fixes')); ?>">
            <?php wp_nonce_field('leanwi_frontend_fixes_settings'); ?>
            <input type="hidden" name="leanwi_frontend_fixes_settings" value="1">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">Plugin-managed frontend fixes</th>
                        <td>
                            <label>
                                <input type="checkbox" name="leanwi_frontend_fixes_enabled" value="1" <?php checked($enabled); ?>>
                                Enable frontend accessibility fixes from this plugin
                            </label>
                            <p class="description">
                                When enabled, the plugin replaces known child-theme handles such as
                                <code>wvls-accessibility-fixes</code> and <code>wvls-divi-tabs-accessibility</code>.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button('Save Frontend Fixes Settings'); ?>
        </form>

        <h2>Bundled fixes</h2>
        <p>This build includes one CSS file and <?php echo esc_html((string) count($scripts)); ?> JavaScript files. Divi 5 test files are not included.</p>
    </div>
    <?php
}

function leanwi_accessibility_enqueue_admin_scripts($hook) {
    // Load only if current page is the Site Scan page
    if (isset($_GET['page']) && $_GET['page'] === 'leanwi-site-scan') {
        wp_enqueue_script(
            'leanwi-accessibility-make-snapshot',
            LEANWI_AR_URL . 'assets/admin-make-snapshot.js',
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
            LEANWI_AR_URL . 'assets/focused-content-report.css',
            [],
            LEANWI_AR_VERSION
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
            LEANWI_AR_URL . 'assets/focused-content-report.js',
            $script_dependencies,
            LEANWI_AR_VERSION,
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
        LEANWI_AR_URL . 'assets/focused-content-highlight.css',
        [],
        LEANWI_AR_VERSION
    );

    wp_enqueue_script(
        'leanwi-focused-content-highlight',
        LEANWI_AR_URL . 'assets/focused-content-highlight.js',
        [],
        LEANWI_AR_VERSION,
        true
    );

    wp_localize_script('leanwi-focused-content-highlight', 'leanwiFocusedHighlight', [
        'locator' => $locator,
    ]);
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\leanwi_accessibility_enqueue_frontend_highlight');

function leanwi_frontend_fix_scripts() {
    return apply_filters('leanwi_accessibility_frontend_fix_scripts', [
        'divi-accordion-accessibility.js',
        'divi-blurb-accessibility.js',
        'divi-gallery-accessibility.js',
        'divi-image-slider-accessibility.js',
        'divi-search-accessibility.js',
        'divi-tabs-accessibility.js',
        'divi-toggle-accessibility.js',
        'divi-video-slider-accessibility.js',
        'events-calendar-accessibility.js',
        'events-carousel-accessibility.js',
        'events-feed-accessibility.js',
        'peeaye-tabs-maker-accessibility.js',
        'skip-link-focus-for-sr-accessibility-fix.js',
        'supreme-advanced-tabs-accessibility.js',
        'supreme-blog-carousel-accessibility.js',
    ]);
}

function leanwi_frontend_fix_script_dependencies($script) {
    $jquery_scripts = [
        'events-calendar-accessibility.js',
        'events-carousel-accessibility.js',
    ];

    return in_array($script, $jquery_scripts, true) ? ['jquery'] : [];
}

function leanwi_enqueue_frontend_fix_style($handle, $relative_path) {
    $path = LEANWI_AR_PATH . $relative_path;
    if (!file_exists($path)) {
        return;
    }

    wp_dequeue_style($handle);
    wp_deregister_style($handle);

    wp_enqueue_style(
        $handle,
        LEANWI_AR_URL . $relative_path,
        [],
        (string) filemtime($path)
    );
}

function leanwi_enqueue_frontend_fix_script($handle, $relative_path, $dependencies = []) {
    $path = LEANWI_AR_PATH . $relative_path;
    if (!file_exists($path)) {
        return;
    }

    wp_dequeue_script($handle);
    wp_deregister_script($handle);

    wp_enqueue_script(
        $handle,
        LEANWI_AR_URL . $relative_path,
        $dependencies,
        (string) filemtime($path),
        true
    );
}

function leanwi_enqueue_frontend_accessibility_fixes() {
    if (is_admin() || !leanwi_frontend_fixes_enabled()) {
        return;
    }

    leanwi_enqueue_frontend_fix_style(
        'wvls-accessibility-fixes',
        'assets/frontend-fixes/css/accessibility-fixes.css'
    );

    foreach (leanwi_frontend_fix_scripts() as $script) {
        $script = basename((string) $script);
        if ($script === '') {
            continue;
        }

        $handle = 'wvls-' . basename($script, '.js');
        leanwi_enqueue_frontend_fix_script(
            $handle,
            'assets/frontend-fixes/js/' . $script,
            leanwi_frontend_fix_script_dependencies($script)
        );
    }
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\leanwi_enqueue_frontend_accessibility_fixes', 999);

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
