<?php

function leanwi_take_accessibility_snapshot() {
    global $wpdb;

    $src_table = "{$wpdb->prefix}accessibility_checker";
    $dest_table = "{$wpdb->prefix}leanwi_accessibility_checker_snapshot";
    $snapshot_table = "{$wpdb->prefix}leanwi_snapshot";

    $wpdb->query('START TRANSACTION');
    // 3. Insert one record into leanwi_snapshot
    try {
        $wpdb->query("INSERT INTO $snapshot_table (snapshot_date) VALUES (DEFAULT)");
        $snapshot_id = $wpdb->insert_id;

        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            error_log('DB insert into snapshot table: ' . $wpdb->last_error); // Logs the error to wp-content/debug.log
            return new WP_REST_Response(['message' => 'Failed to create snapshot record'], 500);
        }

        if (!$snapshot_id) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(['message' => 'Failed to create snapshot record'], 500);
        }
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('Snapshot error: ' . $e->getMessage());
        return new WP_REST_Response(['message' => 'Failed to create snapshot record'], 500);
    }

    // 4. Copy records with snapshot_id
    try {
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

        if ($wpdb->last_error) {
            $wpdb->query('ROLLBACK');
            error_log('DB insert into snapshot_data table: ' . $wpdb->last_error); // Logs the error to wp-content/debug.log
            return new WP_REST_Response(['message' => 'Snapshot record created, but data copy failed.'], 500);
        }

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return new WP_REST_Response(['message' => 'Snapshot record created, but data copy failed.'], 500);
        }

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('Snapshot copy records error: ' . $e->getMessage());
        return new WP_REST_Response(['message' => 'Snapshot record created, but data copy failed.'], 500);
    }

    $wpdb->query('COMMIT');
    return new WP_REST_Response([
        'message' => isset($snapshot_id, $result)
            ? "Snapshot ID $snapshot_id created with $result rows."
            : "Snapshot completed, but result details were incomplete.",
    ], 200);

}

add_action('wp_ajax_leanwi_take_snapshot', 'leanwi_take_accessibility_snapshot');