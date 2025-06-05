<?php
/*
Plugin Name: LEANWI Accessibility Reporting
GitHub URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Update URI:   https://github.com/brendan-leanwi/leanwi-accessibility-reporting
Description: Functionality to aid reporting on accessibility for the entire site.
Version: 1.0
Author: Brendan Tuckey
Author URI:   https://github.com/brendan-leanwi
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  leanwi-tutorial
Domain Path:  /languages
Tested up to: 6.8.1
*/

// Currently set up just to scan pages (not posts)
add_action('rest_api_init', function () {
    register_rest_route('leanwi-accessibility-reporting/v1', '/published-posts', [
        'methods'  => 'GET',
        'callback' => function () {
            if (!current_user_can('edit_posts')) {
                return new WP_REST_Response(['message' => 'Forbidden'], 403);
            }

            $args = [
                'post_type'      => 'page', //['post', 'page'],  // adjust if you want other types
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ];

            $posts = get_posts($args);

            return new WP_REST_Response($posts, 200);
        },
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('leanwi-accessibility-reporting/v1', '/snapshot', [
        'methods'  => 'POST',
        'callback' => 'leanwi_take_accessibility_snapshot',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);
});

function leanwi_take_accessibility_snapshot() {
    global $wpdb;

    $src_table = "{$wpdb->prefix}accessibility_checker";
    $dest_table = "{$wpdb->prefix}leanwi_accessibility_checker_snapshot";
    $snapshot_table = "{$wpdb->prefix}leanwi_snapshot";

    $charset_collate = $wpdb->get_charset_collate();

    // 1. Create the snapshot table (if not exists)
    $sql_snapshot = "
        CREATE TABLE IF NOT EXISTS $snapshot_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            snapshot_date timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;
    ";

    // 2. Create the snapshot data table (if not exists)
    $sql_snapshot_data = "
        CREATE TABLE IF NOT EXISTS $dest_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            snapshot_id bigint(20),
            postid bigint(20),
            siteid text,
            type text,
            rule text,
            ruletype text,
            object mediumtext,
            recordcheck mediumint(9),
            created timestamp DEFAULT CURRENT_TIMESTAMP,
            user bigint(20),
            ignre mediumint(9),
            ignre_global mediumint(9),
            ignre_user bigint(20) NULL,
            ignre_date timestamp NULL,
            ignre_comment mediumtext NULL,
            PRIMARY KEY  (id),
            KEY postid (postid),
            KEY snapshot_id (snapshot_id),
            CONSTRAINT fk_snapshot_id
                FOREIGN KEY (snapshot_id)
                REFERENCES $snapshot_table(id)
                ON DELETE CASCADE
        ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_snapshot);
    dbDelta($sql_snapshot_data);

    // 3. Insert one record into leanwi_snapshot
    $wpdb->insert($snapshot_table, []); // snapshot_date auto-populated
    $snapshot_id = $wpdb->insert_id;

    if (!$snapshot_id) {
        return new WP_REST_Response(['message' => 'Failed to create snapshot record'], 500);
    }

    // 4. Copy records with snapshot_id
    $insert_sql = $wpdb->prepare("
        INSERT INTO $dest_table (
            snapshot_id, postid, siteid, type, rule, ruletype, object,
            recordcheck, created, user, ignre, ignre_global,
            ignre_user, ignre_date, ignre_comment
        )
        SELECT
            %d, postid, siteid, type, rule, ruletype, object,
            recordcheck, created, user, ignre, ignre_global,
            ignre_user, ignre_date, ignre_comment
        FROM $src_table
    ", $snapshot_id);

    $result = $wpdb->query($insert_sql);

    return new WP_REST_Response([
        'message' => "Snapshot ID $snapshot_id created with $result rows."
    ], 200);
}

