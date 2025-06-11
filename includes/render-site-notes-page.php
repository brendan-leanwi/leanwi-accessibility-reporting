<?php

function leanwi_render_site_notes_page() {
    global $wpdb;
    $notes_table = $wpdb->prefix . 'leanwi_accessibility_notes';
    $snapshot_table = $wpdb->prefix . 'leanwi_snapshot';
    $checker_table = $wpdb->prefix . 'leanwi_accessibility_checker_snapshot';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leanwi_add_note_nonce']) && wp_verify_nonce($_POST['leanwi_add_note_nonce'], 'leanwi_add_note_action')) {
        $entry_user = wp_get_current_user()->user_login;
        $rule_pertaining_to = sanitize_text_field($_POST['rule_pertaining_to']);
        $post_pertaining_to = sanitize_text_field($_POST['post_pertaining_to']);
        $other_pertaining_to = sanitize_text_field($_POST['other_pertaining_to']);
        $snapshot_note = wp_kses_post($_POST['snapshot_note']);

        $last_snapshot_id = $wpdb->get_var("SELECT MAX(id) FROM $snapshot_table");

        $wpdb->insert($notes_table, [
            'note_date' => current_time('mysql'),
            'last_snapshot_id' => $last_snapshot_id,
            'entry_user' => $entry_user,
            'rule_pertaining_to' => $rule_pertaining_to,
            'post_pertaining_to' => $post_pertaining_to,
            'other_pertaining_to' => $other_pertaining_to,
            'snapshot_note' => $snapshot_note,
        ]);

        echo '<div class="notice notice-success"><p>Note added successfully!</p></div>';
    }

    // Get dropdown data
    $rules = $wpdb->get_col("SELECT DISTINCT rule FROM $checker_table ORDER BY rule");
    $posts = $wpdb->get_results("
        SELECT DISTINCT p.ID, p.post_title
        FROM $checker_table c
        JOIN {$wpdb->posts} p ON c.postid = p.ID
        ORDER BY p.post_title
    ");

    ?>
    <div class="wrap">
        <h1>Site Scan Notes</h1>
        <form method="post">
            <?php wp_nonce_field('leanwi_add_note_action', 'leanwi_add_note_nonce'); ?>
            <h2>Add a New Note:</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="rule_pertaining_to">Rule Pertaining To</label></th>
                    <td>
                        <select name="rule_pertaining_to" id="rule_pertaining_to">
                            <option value="">-- Select Rule --</option>
                            <?php foreach ($rules as $rule): ?>
                                <option value="<?php echo esc_attr($rule); ?>"><?php echo esc_html($rule); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_pertaining_to">Post Pertaining To</label></th>
                    <td>
                        <select name="post_pertaining_to" id="post_pertaining_to">
                            <option value="">-- Select Post --</option>
                            <?php foreach ($posts as $post): ?>
                                <option value="<?php echo esc_attr($post->ID); ?>"><?php echo esc_html($post->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="other_pertaining_to">Other Pertaining To</label></th>
                    <td><input type="text" name="other_pertaining_to" id="other_pertaining_to" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="snapshot_note">Snapshot Note</label></th>
                    <td><textarea name="snapshot_note" id="snapshot_note" rows="10" class="large-text code"></textarea></td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Add Note</button>
            </p>
        </form>

        <hr>
        <h2>View Site Notes</h2>

        <?php
        // Get filter dropdown values
        $users = $wpdb->get_col("SELECT DISTINCT entry_user FROM $notes_table ORDER BY entry_user");
        $rules = $wpdb->get_col("SELECT DISTINCT rule_pertaining_to FROM $notes_table ORDER BY rule_pertaining_to");
        $posts = $wpdb->get_results("
            SELECT DISTINCT n.post_pertaining_to, p.post_title
            FROM $notes_table n
            JOIN {$wpdb->posts} p ON n.post_pertaining_to = p.ID
            ORDER BY p.post_title
        ");
        ?>

        <form method="get">
            <input type="hidden" name="page" value="leanwi-site-notes">
            <table class="form-table">
                <?php
                $snapshot_ids = $wpdb->get_col("SELECT DISTINCT last_snapshot_id FROM $notes_table WHERE last_snapshot_id IS NOT NULL ORDER BY last_snapshot_id ASC");
                ?>
                <tr>
                    <th scope="row"><label for="snapshot_id">Snapshot ID (Show Notes After)</label></th>
                    <td>
                        <select name="snapshot_id" id="snapshot_id">
                            <option value="">-- All Snapshots --</option>
                            <?php foreach ($snapshot_ids as $sid): ?>
                                <option value="<?php echo esc_attr($sid); ?>" <?php selected($_GET['snapshot_id'] ?? '', $sid); ?>>
                                    <?php echo esc_html($sid); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="entry_user">Entered By</label></th>
                    <td>
                        <select name="entry_user" id="entry_user">
                            <option value="">-- All Users --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo esc_attr($user); ?>" <?php selected($_GET['entry_user'] ?? '', $user); ?>><?php echo esc_html($user); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="rule_pertaining_to">Rule Pertaining To</label></th>
                    <td>
                        <select name="rule_pertaining_to" id="rule_pertaining_to">
                            <option value="">-- All Rules --</option>
                            <?php foreach ($rules as $rule): ?>
                                <option value="<?php echo esc_attr($rule); ?>" <?php selected($_GET['rule_pertaining_to'] ?? '', $rule); ?>><?php echo esc_html($rule); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_pertaining_to">Post Pertaining To</label></th>
                    <td>
                        <select name="post_pertaining_to" id="post_pertaining_to">
                            <option value="">-- All Posts --</option>
                            <?php foreach ($posts as $post): ?>
                                <option value="<?php echo esc_attr($post->post_pertaining_to); ?>" <?php selected($_GET['post_pertaining_to'] ?? '', $post->post_pertaining_to); ?>><?php echo esc_html($post->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="other_pertaining_to">Other Pertaining To (Partial Match)</label></th>
                    <td><input type="text" name="other_pertaining_to" id="other_pertaining_to" class="regular-text" value="<?php echo esc_attr($_GET['other_pertaining_to'] ?? ''); ?>" /></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button">Search Notes</button>
            </p>
        </form>

        <?php
        // Build query from filters
        $conditions = [];
        $params = [];

        if (!empty($_GET['snapshot_id'])) {
            $conditions[] = 'last_snapshot_id >= %d';
            $params[] = (int) $_GET['snapshot_id'];
        }
        if (!empty($_GET['entry_user'])) {
            $conditions[] = 'entry_user = %s';
            $params[] = $_GET['entry_user'];
        }
        if (!empty($_GET['rule_pertaining_to'])) {
            $conditions[] = 'rule_pertaining_to = %s';
            $params[] = $_GET['rule_pertaining_to'];
        }
        if (!empty($_GET['post_pertaining_to'])) {
            $conditions[] = 'post_pertaining_to = %d';
            $params[] = (int) $_GET['post_pertaining_to'];
        }
        if (!empty($_GET['other_pertaining_to'])) {
            $conditions[] = 'other_pertaining_to LIKE %s';
            $params[] = '%' . $wpdb->esc_like($_GET['other_pertaining_to']) . '%';
        }

        $where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $query = "SELECT * FROM $notes_table $where_sql ORDER BY note_date DESC";

        $prepared_query = $wpdb->prepare($query, $params);
        $notes = $wpdb->get_results($prepared_query);

        if ($notes) {
            echo '<h3>Matching Notes</h3>';
            // Code to display the notes in a text area
            echo '<textarea rows="20" style="width:100%; font-family:monospace;">';
            foreach ($notes as $note) {
                $meta = [];
                if ($note->entry_user) $meta[] = "User: $note->entry_user";
                if ($note->rule_pertaining_to) $meta[] = "Rule: $note->rule_pertaining_to";
                if ($note->post_pertaining_to) {
                    $post_title = get_the_title((int) $note->post_pertaining_to);
                    $meta[] = "Post: $post_title";
                }
                if ($note->other_pertaining_to) $meta[] = "Other: $note->other_pertaining_to";

                echo "* " . implode(" | ", $meta) . " (" . $note->note_date . ")\n";
                echo wp_strip_all_tags(stripslashes($note->snapshot_note)) . "\n\n";
            }
            echo '</textarea>';

            /* Code to display the notes just on the page (maybe a future change?)
            echo '<div style="max-height: 600px; overflow-y: auto; font-family: monospace;">';
            foreach ($notes as $note) {
                $meta = [];
                if ($note->entry_user) $meta[] = "User: $note->entry_user";
                if ($note->rule_pertaining_to) $meta[] = "Rule: $note->rule_pertaining_to";
                if ($note->post_pertaining_to) {
                    $post_title = get_the_title((int) $note->post_pertaining_to);
                    $meta[] = "Post: $post_title";
                }
                if ($note->other_pertaining_to) $meta[] = "Other: $note->other_pertaining_to";

                echo '<p><em>* ' . esc_html(implode(" | ", $meta)) . ' (' . esc_html($note->note_date) . ')</em><br>';
                echo nl2br(esc_html(stripslashes($note->snapshot_note))) . "</p>\n";
            }
            echo '</div>';
            */
        } else {
            echo '<p>No notes found matching that criteria.</p>';
        }
        ?>
    </div>
    <?php
}
