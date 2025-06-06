<?php

namespace LeanwiAccessibility;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create or update database tables needed for accessibility snapshots.
 */
function leanwi_accessibility_create_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $engine = "ENGINE=InnoDB";
    $data_table = $wpdb->prefix . 'leanwi_accessibility_checker_snapshot';
    $snapshot_table = "{$wpdb->prefix}leanwi_snapshot";

    $sql_snapshot = "
        CREATE TABLE IF NOT EXISTS $snapshot_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            snapshot_date timestamp DEFAULT CURRENT_TIMESTAMP,
            snapshot_note mediumtext NULL,
            PRIMARY KEY (id)
        ) $engine $charset_collate;
    ";
    
    $sql_snapshot_data = "
    CREATE TABLE IF NOT EXISTS $data_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            snapshot_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
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
        ) $engine $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    try {
        dbDelta($sql_snapshot);
        // Debug logging to track SQL execution
        if (!empty($wpdb->last_error)) {
            return new WP_REST_Response(['message' => 'Failed to set up snapshot table.'], 500);
        }
    } catch (Exception $e) {
        error_log('Create snapshot table error: ' . $e->getMessage());
        return new WP_REST_Response(['message' => 'Failed to set up snapshot table.'], 500);
    }

    try {
        dbDelta($sql_snapshot_data);
        // Debug logging to track SQL execution
        if (!empty($wpdb->last_error)) {
            return new WP_REST_Response(['message' => 'Failed to set up snapshot data table.'], 500);
        }
    } catch (Exception $e) {
        error_log('Create snapshot data table error: ' . $e->getMessage());
        return new WP_REST_Response(['message' => 'Failed to set up snapshot data table.'], 500);
    }
}

/**
 * Hook the function to run on plugin activation.
 */
function leanwi_accessibility_register_activation_hook() {
    register_activation_hook(__FILE__, __NAMESPACE__ . '\\leanwi_accessibility_create_tables');
}
