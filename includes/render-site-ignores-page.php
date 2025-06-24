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

            foreach ($rows as $row) {
                $db_normalized = leanwi_normalize_html_object($row->object);
                $submitted_normalized = leanwi_normalize_html_object($submitted_objects[$index]);

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

    // Get all non ignored rules and group them
    $rules = $wpdb->get_results("
        SELECT rule, ruletype, object
        FROM $accessibility_table
        WHERE ignre = 0
        GROUP BY rule, ruletype, object
        ORDER BY rule
    ");
?>

    <div class="wrap">
        <h1>Site Ignores</h1>
        <form method="post">
            <?php wp_nonce_field('leanwi_add_ignore_action', 'leanwi_add_ignore_nonce'); ?>
            <h2>Site-wide Non-Ignored Errors/Warnings</h2>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Rule</th>
                        <th>Rule Type</th>
                        <th>Object</th>
                        <th>Ignore?</th>
                        <th>Comment</th>
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