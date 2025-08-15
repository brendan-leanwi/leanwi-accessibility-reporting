<?php
add_action('rest_api_init', function () {
    register_rest_route('leanwi-accessibility-reporting/v1', '/latest-snapshot', [
        'methods'  => 'GET',
        'callback' => 'leanwi_get_latest_snapshot',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ]);
});

function leanwi_get_latest_snapshot() {
    global $wpdb;

    $snapshot_table = "{$wpdb->prefix}leanwi_snapshot";
    $data_table = "{$wpdb->prefix}leanwi_accessibility_checker_snapshot";
    $notes_table = "{$wpdb->prefix}leanwi_accessibility_notes";

    // Get latest snapshot ID
    $latest_snapshot_id = $wpdb->get_var("
        SELECT id 
        FROM $snapshot_table 
        ORDER BY snapshot_date DESC 
        LIMIT 1
    ");

    if (!$latest_snapshot_id) {
        return new WP_REST_Response(['message' => 'No snapshots found'], 404);
    }

    // Get snapshot info
    $snapshot_info = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $snapshot_table WHERE id = %d", $latest_snapshot_id),
        ARRAY_A
    );

    // Get snapshot data
    $snapshot_data = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $data_table WHERE snapshot_id = %d", $latest_snapshot_id),
        ARRAY_A
    );

    // Get related notes
    $notes = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $notes_table WHERE snapshot_id = %d", $latest_snapshot_id),
        ARRAY_A
    );

    return new WP_REST_Response([
        'snapshot' => $snapshot_info,
        'data' => $snapshot_data,
        'notes' => $notes
    ], 200);
}
