<?php

function leanwi_render_site_scan_page() {
    global $wpdb;

    $rule_filter = isset($_GET['rule']) ? sanitize_text_field($_GET['rule']) : '';

    echo '<div class="wrap"><h1>Site Scan Summary</h1>';

    // --- Snapshot selection form and comparison results ---

    // Fetch all snapshots ordered by newest first
    $snapshot_table = "{$wpdb->prefix}leanwi_snapshot";
    $snapshots = $wpdb->get_results("SELECT id, snapshot_date FROM $snapshot_table ORDER BY snapshot_date DESC");

    // Determine pre-checked snapshot IDs (last two)
    $prechecked_ids = [];
    if (count($snapshots) >= 2) {
        $prechecked_ids = [$snapshots[0]->id, $snapshots[1]->id];
    } elseif (count($snapshots) === 1) {
        $prechecked_ids = [$snapshots[0]->id];
    }

    // Get user selection from query params or use defaults
    $selected_snapshots = isset($_GET['compare_snapshots']) && is_array($_GET['compare_snapshots']) 
        ? array_map('intval', $_GET['compare_snapshots']) 
        : $prechecked_ids;

    // Render the snapshot selection form
    echo '<form method="GET" action="' . admin_url('admin.php') . '" style="margin-bottom: 2em;">';
    echo '<input type="hidden" name="page" value="leanwi-site-scan">';
    echo '<h2>Select Two Snapshots to Compare</h2>';
    foreach ($snapshots as $snapshot) {
        $checked = in_array($snapshot->id, $selected_snapshots) ? 'checked' : '';
        echo '<label style="display:block; margin-bottom:4px;">';
        echo '<input type="checkbox" name="compare_snapshots[]" value="' . esc_attr($snapshot->id) . '" ' . $checked . ' />';
        echo ' Snapshot #' . esc_html($snapshot->id) . ' - ' . esc_html($snapshot->snapshot_date);
        echo '</label>';
    }
    echo '<p><small>Select exactly two snapshots.</small></p>';
    echo '<button type="submit" class="button button-primary">Compare Snapshots</button>';
    echo '</form>';

    // If exactly two snapshots selected, run comparison query and display results
    if (count($selected_snapshots) === 2) {
        $snapshot_latest = max($selected_snapshots);
        $snapshot_previous = min($selected_snapshots);

        $compare_sql = $wpdb->prepare("
            SELECT 
                COALESCE(a.rule, b.rule) AS rule,
                COALESCE(a.ruleType, b.ruleType) AS ruleType,
                COALESCE(a.count, 0) AS count_latest,
                COALESCE(b.count, 0) AS count_previous
            FROM
                (
                    SELECT rule, ruleType, COUNT(*) AS count
                    FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
                    WHERE snapshot_id = %d AND ignre = 0
                    GROUP BY rule, ruleType
                ) a
            LEFT JOIN
                (
                    SELECT rule, ruleType, COUNT(*) AS count
                    FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
                    WHERE snapshot_id = %d AND ignre = 0
                    GROUP BY rule, ruleType
                ) b ON a.rule = b.rule AND a.ruleType = b.ruleType

            UNION

            SELECT 
                COALESCE(a.rule, b.rule) AS rule,
                COALESCE(a.ruleType, b.ruleType) AS ruleType,
                COALESCE(a.count, 0) AS count_latest,
                COALESCE(b.count, 0) AS count_previous
            FROM
                (
                    SELECT rule, ruleType, COUNT(*) AS count
                    FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
                    WHERE snapshot_id = %d AND ignre = 0
                    GROUP BY rule, ruleType
                ) a
            RIGHT JOIN
                (
                    SELECT rule, ruleType, COUNT(*) AS count
                    FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
                    WHERE snapshot_id = %d AND ignre = 0
                    GROUP BY rule, ruleType
                ) b ON a.rule = b.rule AND a.ruleType = b.ruleType

            ORDER BY (count_latest - count_previous) DESC
        ", $snapshot_latest, $snapshot_previous, $snapshot_latest, $snapshot_previous);

        $comparison_results = $wpdb->get_results($compare_sql);

        echo '<h2>Comparison Results for Snapshots #' . esc_html($snapshot_latest) . ' vs #' . esc_html($snapshot_previous) . '</h2>';
        if ($comparison_results) {
            echo '<table class="widefat"><thead><tr>';
            echo '<th>Rule</th><th>Type</th><th>Count (Snapshot #' . esc_html($snapshot_latest) . ')</th><th>Count (Snapshot #' . esc_html($snapshot_previous) . ')</th><th>Difference</th>';
            echo '</tr></thead><tbody>';

            foreach ($comparison_results as $row) {
                $diff = intval($row->count_latest) - intval($row->count_previous);
                echo '<tr>';
                echo '<td>' . esc_html($row->rule) . '</td>';
                echo '<td>' . esc_html($row->ruleType) . '</td>';
                echo '<td>' . intval($row->count_latest) . '</td>';
                echo '<td>' . intval($row->count_previous) . '</td>';
                echo '<td>' . $diff . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>No comparison data found for selected snapshots.</p>';
        }
    } elseif (isset($_GET['compare_snapshots'])) {
        // If submitted but not 2 snapshots selected, show error
        echo '<p style="color:red;"><strong>Please select exactly two snapshots to compare.</strong></p>';
    }

    // --- Existing logic for detailed rule view or summary ---

    if ($rule_filter) {
        // Detail view
        echo '<h2>Pages with rule: ' . esc_html($rule_filter) . '</h2>';

        $results = $wpdb->get_results(
            $wpdb->prepare("
                SELECT COUNT(p.ID) AS count, p.ID, p.post_title, p.post_name,
                    CASE s.ignre 
                        WHEN 1 THEN 'Yes' 
                        ELSE 'No' 
                    END AS ignre_status
                FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot s
                JOIN {$wpdb->prefix}posts p ON p.ID = s.postid
                WHERE s.snapshot_id = (
                    SELECT MAX(snapshot_id) FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
                )
                AND s.rule = %s
                GROUP BY p.ID, p.post_name, s.rule, s.ignre
                ORDER BY p.post_name
            ", $rule_filter)
        );

        if ($results) {
            echo '<table class="widefat"><thead><tr><th>Page</th><th>Violations</th><th>Ignored</th></tr></thead><tbody>';
            foreach ($results as $row) {
                $edit_link = get_edit_post_link($row->ID);
                echo '<tr>';
                echo '<td><a href="' . esc_url($edit_link) . '">' . esc_html($row->post_title) . '</a></td>';
                echo '<td>' . intval($row->count) . '</td>';
                echo '<td>' . esc_html($row->ignre_status) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>No pages found for this rule.</p>';
        }

        echo '<p><a href="' . admin_url('admin.php?page=leanwi-site-scan') . '">&larr; Back to summary</a></p>';
    } else {
        // Summary view
        $results = $wpdb->get_results("
            SELECT COUNT(rule) AS count, rule, ruleType
            FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
            WHERE snapshot_id = (
              SELECT MAX(snapshot_id) FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
            )
            AND ignre = 0
            GROUP BY rule, ruleType
            ORDER BY count DESC
        ");

        echo '<h2>Errored or Warning Violations Still Outstanding</h2>';
        echo '<table class="widefat"><thead><tr><th>Rule</th><th>Type</th><th>Count</th></tr></thead><tbody>';
        foreach ($results as $row) {
            $link = admin_url('admin.php?page=leanwi-site-scan&rule=' . urlencode($row->rule));
            echo '<tr>';
            echo '<td><a href="' . esc_url($link) . '">' . esc_html($row->rule) . '</a></td>';
            echo '<td>' . esc_html($row->ruleType) . '</td>';
            echo '<td>' . intval($row->count) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        // Ignored violations summary view
        $ignored_results = $wpdb->get_results("
            SELECT COUNT(rule) AS count, rule, ruleType
            FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
            WHERE snapshot_id = (
            SELECT MAX(snapshot_id) FROM {$wpdb->prefix}leanwi_accessibility_checker_snapshot
            )
            AND ignre = 1
            GROUP BY rule, ruleType
            ORDER BY count DESC
        ");

        echo '<h2>Ignored Violations Summary</h2>';
        echo '<table class="widefat"><thead><tr><th>Rule</th><th>Type</th><th>Count</th></tr></thead><tbody>';
        foreach ($ignored_results as $row) {
            $link = admin_url('admin.php?page=leanwi-site-scan&rule=' . urlencode($row->rule) . '&ignored=1');
            echo '<tr>';
            echo '<td><a href="' . esc_url($link) . '">' . esc_html($row->rule) . '</a></td>';
            echo '<td>' . esc_html($row->ruleType) . '</td>';
            echo '<td>' . intval($row->count) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';
    echo '<div style="margin-top:2em;">';
    echo '<button id="leanwi-make-snapshot" class="button button-secondary">Make a Snapshot</button>';
    echo '<span id="leanwi-snapshot-spinner" class="spinner" style="float: none; margin-left: 1em; visibility: hidden;"></span>';
    echo '<span id="leanwi-snapshot-status" style="margin-left: 1em;"></span>';
    echo '</div>';
}