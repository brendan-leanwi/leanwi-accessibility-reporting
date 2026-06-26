<?php

// The link in the main dashboard just forwards to the accessibility version
function leanwi_render_site_review_request_main_page() {
    leanwi_render_site_review_request_page();
}

function leanwi_render_site_review_request_page() {
    global $wpdb;

    // Default number of items (dropdown)
    $num_items = isset($_POST['leanwi_num_items']) ? intval($_POST['leanwi_num_items']) : 10;
    $provided_context = isset($_GET['leanwi_review_context'])
        ? sanitize_textarea_field(wp_unslash($_GET['leanwi_review_context']))
        : '';

    // Initial fetch (for non-JS fallback)
    $recent_changes = $wpdb->get_results(
        $wpdb->prepare("
            SELECT ID, post_title, post_type, post_modified
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
              AND post_type IN ('post','page')
            ORDER BY post_modified DESC
            LIMIT %d
        ", $num_items)
    );

    // Build default text for textarea
    if ($provided_context !== '') {
        $default_text = $provided_context;
    } else {
        $default_text = "The latest files changed include:\n";
        foreach ($recent_changes as $post) {
            $default_text .= sprintf(
                "- %s (%s), last modified %s\n",
                $post->post_title ?: '(no title)',
                ucfirst($post->post_type),
                date('Y-m-d H:i', strtotime($post->post_modified))
            );
        }
    }

    // Handle form submit
    if (isset($_POST['leanwi_review_submit'])) {
        check_admin_referer('leanwi_review_request_action', 'leanwi_review_request_nonce');

        $user_message = sanitize_textarea_field($_POST['leanwi_review_details']);

        $to      = 'websitehelp@librarieswin.org'; // HARDCODED!! Do I want to have this as a parameter somewhere?
        $subject = 'Accessibility Review Request';
        $message = "A request for an accessibility review has been submitted from the WordPress admin dashboard of "
                   . get_bloginfo('name') . " (" . home_url() . ").\n\n";
        $message .= "Submitted by: " . wp_get_current_user()->user_email . "\n\n";
        $message .= "Details provided:\n" . $user_message . "\n";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $sent = wp_mail($to, $subject, $message, $headers);

        echo $sent
            ? '<div class="notice notice-success is-dismissible"><p>✅ Review request submitted successfully.</p></div>'
            : '<div class="notice notice-error is-dismissible"><p>❌ Failed to send the review request. Please try again.</p></div>';
    }
    ?>

    <div class="wrap">
        <h1>Ask for Accessibility Review</h1>
        <p style="font-size: 1.1em;">Click the <strong>"Submit Request for an Accessibility Review"</strong> button below to request an accessibility review from LEANWI staff of your most recent changes.<br>
        A notification will be sent to the LEANWI team including the information shown below.
        Please edit or add to these details anything you think may be helpful before submitting. Thank you.</p>
        <hr style="margin-top: 20px; margin-bottom: 30px;">

        <form method="post" id="leanwi-review-form">
            <?php wp_nonce_field('leanwi_review_request_action', 'leanwi_review_request_nonce'); ?>

            <p>
                <label for="leanwi_num_items"><strong>Shown number of latest changes:</strong></label>
                <select id="leanwi_num_items" name="leanwi_num_items">
                    <?php foreach ([5, 10, 15, 20, 25, 30] as $option) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($num_items, $option); ?>>
                            <?php echo esc_html($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span>posts/pages</span>
            </p>

            <p>
                <label for="leanwi_review_details"><strong>The latest files changed include:</strong></label><br>
                <textarea name="leanwi_review_details" id="leanwi_review_details" rows="12" cols="80"><?php 
                    echo isset($_POST['leanwi_review_details']) 
                        ? esc_textarea($_POST['leanwi_review_details']) 
                        : esc_textarea($default_text); 
                ?></textarea>
                <div style="color: red; margin-bottom: 30px;">
                    <strong>(Please edit the above text area as you think best represents the changes you would like reviewed)</strong>
                </div>
            </p>
            <hr style="margin-top: 20px; margin-bottom: 30px;">
            <p>
                <input type="submit"
                       name="leanwi_review_submit"
                       class="button button-primary"
                       value="Submit Request for an Accessibility Review" />
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($){
        $('#leanwi_num_items').on('change', function(){
            var numItems = parseInt($(this).val(), 10);

            // Set cursor to "wait" while loading
            $('body').css('cursor', 'wait');

            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                method: 'POST',
                data: {
                    action: 'leanwi_get_latest_items',
                    nonce: '<?php echo wp_create_nonce("leanwi_nonce"); ?>',
                    num_items: numItems
                },
                success: function(response){
                    if(response.success){
                        $('#leanwi_review_details').val(response.data);
                    } else {
                        console.error('Error fetching latest items:', response);
                    }
                },
                error: function(xhr, status, error){
                    console.error('AJAX error fetching latest items:', error);
                },
                complete: function() {
                    // Restore default cursor
                    $('body').css('cursor', 'default');
                }
            });
        });
    });
    </script>

<?php
}
