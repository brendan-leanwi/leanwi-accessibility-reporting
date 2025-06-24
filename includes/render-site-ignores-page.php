<?php

function leanwi_render_site_ignores_page() {
    global $wpdb;
    $accessibility_table = $wpdb->prefix . 'accessibility_checker';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leanwi_add_ignore_nonce']) && wp_verify_nonce($_POST['leanwi_add_ignore_nonce'], 'leanwi_add_ignore_action')) {
        $ignore = $_POST['ignore'] ?? [];
        $rules = $_POST['rule'] ?? [];
        $ruletype = $_POST['ruletype'] ?? [];
        $submitted_objects = $_POST['object'] ?? [];
        $comments = $_POST['comment'] ?? [];

        foreach ($ignore as $index => $val) {
            // Sanitize inputs
            $rule = sanitize_text_field($rules[$index]);
            $type = sanitize_text_field($ruletype[$index]);
            $comment = sanitize_text_field($comments[$index]);

            $rows = $wpdb->get_results(
                $wpdb->prepare("
                    SELECT id, object 
                    FROM $accessibility_table 
                    WHERE rule = %s AND ruletype = %s
                    AND ignre = 0
                ", $rule, $type)
            );

            $submitted_normalized = leanwi_normalize_html_object($submitted_objects[$index]);
            foreach ($rows as $row) {
                $db_normalized = leanwi_normalize_html_object($row->object);

                if ($db_normalized === $submitted_normalized) {
                    error_log("Match found DB: $db_normalized  ===  Submitted: $submitted_normalized");
                    // Match found, update this row
                    $result = $wpdb->update(
                        $accessibility_table,
                        [
                            'ignre' => 1,
                            'ignre_comment' => $comment,
                            'ignre_date' => current_time('mysql'),
                            'ignre_user' => get_current_user_id(),
                        ],
                        [ 'id' => $row->id ],
                        ['%d', '%s', '%s', '%d'],
                        ['%d']
                    );
                    if ($result === false) {
                        error_log("Update failed for rule: $rule | " . $wpdb->last_error);
                    } 
                }
            }
        }

        echo '<div class="updated notice"><p>Selected rules were ignored with comments added.</p></div>';
    }

    $filter_rule = isset($_POST['filter_rule']) ? sanitize_text_field($_POST['filter_rule']) : '';
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : '';

    // Get all non ignored rules and group them
    $query = "
        SELECT rule, ruletype, object
        FROM $accessibility_table
        WHERE ignre = 0
    ";

    $params = [];

    if ($filter_rule !== '') {
        $query .= " AND rule = %s";
        $params[] = $filter_rule;
    }

    if ($filter_type !== '') {
        $query .= " AND ruletype = %s";
        $params[] = $filter_type;
    }

    $query .= " GROUP BY rule, ruletype, object ORDER BY rule";

    $rules = $params ? $wpdb->get_results($wpdb->prepare($query, ...$params)) : $wpdb->get_results($query);


    $distinct_rules = $wpdb->get_col("SELECT DISTINCT rule FROM $accessibility_table WHERE ignre = 0 ORDER BY rule");
    $distinct_types = $wpdb->get_col("SELECT DISTINCT ruletype FROM $accessibility_table WHERE ignre = 0 ORDER BY ruletype");

?>

    <div class="wrap">
        <h1>Site Ignores</h1>
        <form method="post">
            <?php wp_nonce_field('leanwi_add_ignore_action', 'leanwi_add_ignore_nonce'); ?>
            <h2>Site-wide Non-Ignored Errors/Warnings</h2>

            <div style="margin-bottom: 1em;">
                <label for="filter_rule"><strong>Filter by Rule:</strong></label>
                <select name="filter_rule" id="filter_rule">
                    <option value="">-- All Rules --</option>
                    <?php foreach ($distinct_rules as $rule_option): ?>
                        <option value="<?php echo esc_attr($rule_option); ?>" <?php selected($filter_rule, $rule_option); ?>>
                            <?php echo esc_html($rule_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="filter_type" style="margin-left: 2em;"><strong>Filter by Rule Type:</strong></label>
                <select name="filter_type" id="filter_type">
                    <option value="">-- All Types --</option>
                    <?php foreach ($distinct_types as $type_option): ?>
                        <option value="<?php echo esc_attr($type_option); ?>" <?php selected($filter_type, $type_option); ?>>
                            <?php echo esc_html($type_option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="refresh_list" class="button">Refresh List</button>
            </div>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 10%;">Rule</th>
                        <th style="width: 10%;">Rule Type</th>
                        <th style="width: 40%;">Object</th>
                        <th style="width: 10%;">Ignore?</th>
                        <th style="width: 30%;">Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rules): ?>
                        <?php foreach ($rules as $index => $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->rule); ?></td>
                                <td><?php echo esc_html($row->ruletype); ?></td>
                                <td><?php echo esc_html($row->object); ?></td>
                                <td>
                                    <input type="checkbox" name="ignore[<?php echo $index; ?>]" value="1">
                                    <input type="hidden" name="rule[<?php echo $index; ?>]" value="<?php echo esc_attr($row->rule); ?>">
                                    <input type="hidden" name="ruletype[<?php echo $index; ?>]" value="<?php echo esc_attr($row->ruletype); ?>">
                                    <input type="hidden" name="object[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($row->object, ENT_QUOTES); ?>">

                                </td>
                                <td>
                                    <input type="text" name="comment[<?php echo $index; ?>]" class="regular-text">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No rules found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Ignore these errors/warnings across entire site</button>
            </p>
        </form>
    </div>
<?php    
}

function leanwi_normalize_html_object($html) {
    // Decode entities first (&lt; becomes <)
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // Remove extra whitespace between tags
    $html = preg_replace('/\s+/', ' ', $html);         // collapse all whitespace
    $html = preg_replace('/\s*(<[^>]+>)\s*/', '$1', $html); // trim spaces around tags

    // Normalize quotes
    $html = str_replace(["'", '"'], '"', $html);

    // Lowercase (if desired)
    $html = strtolower(trim($html));

    return $html;
}
?>