<?php
/*
Plugin Name:  LEANWI Accessibility Reporting
GitHub URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Update URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Description:  Added functionality for reporting on accessibility scans
Version:      1.0.0
Author:       Brendan Tuckey
Author URI:   https://github.com/brendan-leanwi
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  leanwi-tutorial
Domain Path:  /languages
Tested up to: 6.8.1
*/

add_action('admin_menu', function () {
    add_management_page('Trigger Accessibility Scans', 'Scan All Pages', 'manage_options', 'trigger-accessibility-scan', 'render_scan_page');
});

function render_scan_page() {
    if (isset($_POST['trigger_scan']) && check_admin_referer('trigger_scan_action')) {
        trigger_accessibility_scans();
    }
    ?>
    <div class="wrap">
        <h1>Trigger Accessibility Scans</h1>
        <form method="post">
            <?php wp_nonce_field('trigger_scan_action'); ?>
            <input type="submit" name="trigger_scan" value="Scan All Published Pages" class="button button-primary" />
        </form>
    </div>
    <?php
}

function trigger_accessibility_scans() {
    $args = [
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
    ];
    $pages = get_posts($args);

    foreach ($pages as $page) {
        $post_id = $page->ID;

        $response = wp_remote_post(
            site_url("/wp-json/accessibility-checker/v1/post-scan-results/{$post_id}"),
            [
                'method'    => 'POST',
                'headers'   => [
                    'Content-Type' => 'application/json',
                ],
                'body'      => json_encode([]), // If required — plugin may accept empty body
            ]
        );

        if (is_wp_error($response)) {
            error_log("Scan failed for post $post_id: " . $response->get_error_message());
        } else {
            error_log("Scan triggered for post $post_id: " . wp_remote_retrieve_body($response));
        }
    }
}
