<?php

if (!defined('LEANWI_ACR_ENGINE_VERSION')) {
    define('LEANWI_ACR_ENGINE_VERSION', '1.3.0');
}

function leanwi_render_focused_content_report_page() {
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to view this report.', 'leanwi-tutorial'));
    }

    $post_type_filter = isset($_GET['post_type_filter']) ? sanitize_key($_GET['post_type_filter']) : 'all';
    $allowed_post_types = ['all', 'page', 'post'];
    if (!in_array($post_type_filter, $allowed_post_types, true)) {
        $post_type_filter = 'all';
    }

    $max_items = isset($_GET['max_items']) ? absint($_GET['max_items']) : 25;
    if ($max_items < 1) {
        $max_items = 25;
    }
    $max_items = min($max_items, 200);

    $target_url = isset($_GET['target_url']) ? esc_url_raw(wp_unslash($_GET['target_url'])) : '';
    $scan_results = leanwi_acr_get_scan_results($post_type_filter, $max_items, $target_url);
    $summary = leanwi_acr_summarize_results($scan_results['posts']);
    ?>
    <div class="wrap leanwi-focused-report">
        <h1>Focused Content Report</h1>
        <p class="description">
            This report filters the accessibility review down to items content editors can usually fix.
            It also includes an optional image text scan to find likely flyers, posters, schedules, and infographics.
            Focused engine <?php echo esc_html(LEANWI_ACR_ENGINE_VERSION); ?>.
        </p>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="leanwi-focused-filters">
            <input type="hidden" name="page" value="leanwi-focused-content-report">

            <label for="leanwi-target-url"><strong>Review one page URL</strong></label>
            <input
                type="url"
                id="leanwi-target-url"
                name="target_url"
                class="regular-text"
                placeholder="https://example.org/page/"
                value="<?php echo esc_attr($target_url); ?>"
            >

            <label for="leanwi-post-type-filter"><strong>Content type</strong></label>
            <select name="post_type_filter" id="leanwi-post-type-filter">
                <option value="all" <?php selected($post_type_filter, 'all'); ?>>Pages and posts</option>
                <option value="page" <?php selected($post_type_filter, 'page'); ?>>Pages only</option>
                <option value="post" <?php selected($post_type_filter, 'post'); ?>>Posts only</option>
            </select>

            <label for="leanwi-max-items"><strong>Max items</strong></label>
            <select name="max_items" id="leanwi-max-items">
                <?php foreach ([10, 25, 50, 100, 200] as $option) : ?>
                    <option value="<?php echo esc_attr($option); ?>" <?php selected($max_items, $option); ?>>
                        <?php echo esc_html($option); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button button-primary">Run Focused Report</button>
        </form>

        <?php if (!empty($scan_results['notice'])) : ?>
            <div class="notice notice-warning"><p><?php echo esc_html($scan_results['notice']); ?></p></div>
        <?php endif; ?>

        <div class="leanwi-focused-summary" aria-label="Focused report summary">
            <div><strong><?php echo intval($summary['posts_scanned']); ?></strong><span>Items scanned</span></div>
            <div><strong><?php echo intval($summary['total']); ?></strong><span>Total findings</span></div>
            <div><strong><?php echo intval($summary['fix']); ?></strong><span>Fix</span></div>
            <div><strong><?php echo intval($summary['review']); ?></strong><span>Review</span></div>
            <div><strong><?php echo intval($summary['warning']); ?></strong><span>Warnings</span></div>
        </div>

        <div class="leanwi-focused-ocr-panel">
            <div>
                <h2>Image Text Scan</h2>
                <p>
                    Browser OCR can flag images with 10 or more detected words. Use this to catch likely
                    infographics, flyers, event posters, schedules, menus, and other images of text.
                </p>
            </div>
            <button type="button" class="button button-secondary" id="leanwi-run-ocr">
                Run Image Text Scan
            </button>
            <span id="leanwi-ocr-status" aria-live="polite"></span>
        </div>

        <?php if (empty($scan_results['posts'])) : ?>
            <p>No published pages or posts were found for this report.</p>
        <?php endif; ?>

        <?php foreach ($scan_results['posts'] as $post_report) : ?>
            <?php leanwi_acr_render_post_report($post_report); ?>
        <?php endforeach; ?>
    </div>
    <?php
}

function leanwi_acr_get_scan_results($post_type_filter, $max_items, $target_url) {
    $notice = '';
    $posts = [];

    if ($target_url !== '') {
        $target_post_id = leanwi_acr_post_id_from_url($target_url);
        if ($target_post_id) {
            $target_post = get_post($target_post_id);
            if ($target_post && current_user_can('edit_post', $target_post_id)) {
                $posts = [$target_post];
            } else {
                $notice = 'The URL resolved to a post, but your account cannot edit it.';
            }
        } else {
            $notice = 'The page URL could not be matched to a WordPress page or post on this site.';
        }
    }

    if ($target_url === '') {
        $post_types = $post_type_filter === 'all' ? ['page', 'post'] : [$post_type_filter];
        $posts = get_posts([
            'post_type' => $post_types,
            'post_status' => 'publish',
            'numberposts' => $max_items,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
    }

    $reports = [];
    foreach ($posts as $post) {
        if (!current_user_can('edit_post', $post->ID)) {
            continue;
        }
        $reports[] = leanwi_acr_scan_post($post);
    }

    leanwi_acr_add_duplicate_title_issues($reports);

    return [
        'notice' => $notice,
        'posts' => $reports,
    ];
}

function leanwi_acr_post_id_from_url($url) {
    $post_id = url_to_postid($url);
    if ($post_id) {
        return $post_id;
    }

    $parts = wp_parse_url($url);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        if (!empty($query['post'])) {
            return absint($query['post']);
        }
        if (!empty($query['p'])) {
            return absint($query['p']);
        }
        if (!empty($query['page_id'])) {
            return absint($query['page_id']);
        }
    }

    return 0;
}

function leanwi_acr_scan_post($post) {
    $html = leanwi_acr_render_content_for_scan($post);
    $document = leanwi_acr_load_html($html);
    $issues = [];
    $ocr_images = [];

    if (!$document) {
        $issues[] = leanwi_acr_issue(
            'review',
            'Content',
            'Content could not be parsed for focused checks.',
            'The WordPress content parser could not read this page content.',
            'Send this page for review if it contains complex blocks, embeds, or custom HTML.',
            'post_content',
            'embeds'
        );
    } else {
        $xpath = new DOMXPath($document);
        leanwi_acr_check_headings($xpath, $issues);
        leanwi_acr_check_images($xpath, $issues, $ocr_images);
        leanwi_acr_check_links($xpath, $issues);
        leanwi_acr_check_tables($xpath, $issues);
        leanwi_acr_check_forms($xpath, $issues);
        leanwi_acr_check_buttons($xpath, $issues);
        leanwi_acr_check_media_embeds($xpath, $issues);
        leanwi_acr_check_manual_lists($xpath, $issues);
        leanwi_acr_check_color_cues($xpath, $issues);
        leanwi_acr_check_inline_contrast($xpath, $issues);
    }

    return [
        'post' => $post,
        'title' => get_the_title($post),
        'permalink' => get_permalink($post),
        'edit_link' => get_edit_post_link($post->ID, ''),
        'issues' => $issues,
        'ocr_images' => $ocr_images,
    ];
}

function leanwi_acr_render_content_for_scan($post) {
    $previous_post = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = $post;
    setup_postdata($post);
    $original_content = $post->post_content;
    $post->post_content = leanwi_acr_prepare_content_for_scan($original_content);
    $html = apply_filters('the_content', $post->post_content);
    $post->post_content = $original_content;
    wp_reset_postdata();
    $GLOBALS['post'] = $previous_post;

    return (string) $html;
}

function leanwi_acr_prepare_content_for_scan($content) {
    $content = leanwi_acr_strip_ignored_shortcodes($content);
    return leanwi_acr_strip_fully_disabled_divi_shortcodes($content);
}

function leanwi_acr_strip_ignored_shortcodes($content) {
    $ignored_shortcodes = apply_filters('leanwi_acr_ignored_shortcodes', ['tockify']);
    if (empty($ignored_shortcodes) || !is_array($ignored_shortcodes)) {
        return $content;
    }

    foreach ($ignored_shortcodes as $shortcode) {
        $shortcode = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $shortcode);
        if ($shortcode === '') {
            continue;
        }

        $content = preg_replace('/\[' . preg_quote($shortcode, '/') . '\b[^\]]*\](.*?)\[\/' . preg_quote($shortcode, '/') . '\]/is', '', (string) $content);
        $content = preg_replace('/\[' . preg_quote($shortcode, '/') . '\b[^\]]*\/?\]/is', '', (string) $content);
    }

    return $content;
}

function leanwi_acr_strip_fully_disabled_divi_shortcodes($content) {
    if (stripos((string) $content, 'disabled_on') === false) {
        return $content;
    }

    $content = (string) $content;
    $previous = null;
    $passes = 0;

    while ($previous !== $content && $passes < 20) {
        $previous = $content;
        $passes++;

        $content = preg_replace_callback(
            '/\[(et_pb_[a-z0-9_]+)\b(?=[^\]]*\bdisabled_on\s*=\s*(["\'])(?:on|true|1|yes)\s*\|\s*(?:on|true|1|yes)\s*\|\s*(?:on|true|1|yes)(?:\s*\|[^"\']*)?\2)([^\]]*)\](.*?)\[\/\1\]/is',
            'leanwi_acr_remove_divi_shortcode_block',
            $content
        );

        $content = preg_replace_callback(
            '/\[(et_pb_[a-z0-9_]+)\b(?=[^\]]*\bdisabled_on\s*=\s*(["\'])(?:on|true|1|yes)\s*\|\s*(?:on|true|1|yes)\s*\|\s*(?:on|true|1|yes)(?:\s*\|[^"\']*)?\2)([^\]]*)\/\]/is',
            'leanwi_acr_remove_divi_shortcode_block',
            $content
        );
    }

    return $content;
}

function leanwi_acr_remove_divi_shortcode_block($matches) {
    return '';
}

function leanwi_acr_load_html($html) {
    if (!class_exists('DOMDocument')) {
        return null;
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $wrapped = '<div id="leanwi-acr-root">' . $html . '</div>';
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $loaded ? $document : null;
}

function leanwi_acr_check_headings($xpath, &$issues) {
    $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
    $h1_count = $xpath->query('//h1')->length;
    $previous_level = 0;

    foreach ($headings as $heading) {
        $level = intval(substr(strtolower($heading->nodeName), 1));
        $text = leanwi_acr_clean_text($heading->textContent);
        $element = 'h' . $level . ': ' . leanwi_acr_shorten($text, 80);
        $locator = leanwi_acr_node_locator($heading);

        if ($level === 1 && $h1_count > 1) {
            $issues[] = leanwi_acr_issue(
                'warning',
                'Headings',
                'Multiple H1 headings found inside the page content.',
                'This scan found ' . $h1_count . ' H1 headings in the editable content area.',
                'Use one H1 for the page title, then H2 for main sections.',
                $element,
                'headings',
                $locator
            );
        }

        if ($previous_level && $level > $previous_level + 1) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Headings',
                'Heading levels jump out of order.',
                'Found H' . $level . ' after H' . $previous_level . '.',
                'Do not skip heading levels. For example, use H3 after H2, not H4.',
                $element,
                'headings',
                $locator
            );
        }

        if ($text !== '') {
            $previous_level = $level;
        }
    }
}

function leanwi_acr_check_images($xpath, &$issues, &$ocr_images) {
    $images = $xpath->query('//img');

    foreach ($images as $image) {
        $src = leanwi_acr_get_image_source($image);
        $alt_present = $image->hasAttribute('alt');
        $alt = leanwi_acr_clean_text($image->getAttribute('alt'));
        $element = 'img: ' . leanwi_acr_shorten($src, 100);
        $suspicious = leanwi_acr_is_suspicious_image($image, $src, $alt);
        $locator = leanwi_acr_node_locator($image);

        if ($src && leanwi_acr_is_ocr_candidate($src)) {
            $ocr_images[] = [
                'src' => esc_url_raw($src),
                'alt' => $alt,
                'element' => $element,
                'locator' => $locator,
            ];
        }

        if (!$alt_present) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Images',
                'Image is missing alt text.',
                'Image source: ' . leanwi_acr_shorten($src, 140),
                'Add concise alt text, or mark the image decorative if it truly adds no information.',
                $element,
                'alt-text',
                $locator
            );
        } elseif ($alt === '' && $suspicious) {
            $issues[] = leanwi_acr_issue(
                'review',
                'Images',
                'Image looks like content but has empty alt text.',
                'Image source: ' . leanwi_acr_shorten($src, 140),
                'If this is a flyer, chart, map, schedule, or infographic, add nearby real text with the same information.',
                $element,
                'infographics',
                $locator
            );
        } elseif ($alt !== '' && preg_match('/^(image|photo|picture|graphic|screenshot|img|dsc|untitled)([\s_-]?\d+)?$/i', $alt)) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Images',
                'Image alt text is too generic.',
                'Current alt text: "' . $alt . '"',
                'Describe the purpose or information in the image, not just that it is an image.',
                $element,
                'alt-text',
                $locator
            );
        } elseif ($alt !== '' && preg_match('/\.(jpg|jpeg|png|gif|webp|svg|pdf)$/i', $alt)) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Images',
                'Image alt text appears to be a file name.',
                'Current alt text: "' . $alt . '"',
                'Replace file names with useful descriptions.',
                $element,
                'alt-text',
                $locator
            );
        } elseif (strlen($alt) > 160) {
            $issues[] = leanwi_acr_issue(
                'warning',
                'Images',
                'Image alt text may be too long.',
                'Alt text is ' . strlen($alt) . ' characters.',
                'Keep ordinary alt text concise. Put long explanations in nearby page text.',
                $element,
                'alt-text',
                $locator
            );
        }

        if ($suspicious && strlen($alt) < 40) {
            $issues[] = leanwi_acr_issue(
                'review',
                'Images',
                'Image may be an infographic, flyer, chart, map, or schedule.',
                'Image source: ' . leanwi_acr_shorten($src, 140),
                'Confirm that all important information in the image is also available as real text on the page.',
                $element,
                'infographics',
                $locator
            );
        }
    }
}

function leanwi_acr_check_links($xpath, &$issues) {
    $links = $xpath->query('//a[@href]');
    $vague = [
        'click here',
        'here',
        'read more',
        'more',
        'more info',
        'more information',
        'learn more',
        'details',
        'download',
        'view',
        'this link',
        'link',
        'continue',
    ];
    $document_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'rtf'];
    $link_map = [];

    foreach ($links as $link) {
        $href = trim($link->getAttribute('href'));
        if ($href === '' || strpos($href, '#') === 0 || preg_match('/^(mailto|tel|javascript|data):/i', $href)) {
            continue;
        }

        $text = leanwi_acr_link_accessible_text($link);
        $lower = strtolower($text);
        $element = 'a: ' . leanwi_acr_shorten($href, 100);
        $locator = leanwi_acr_node_locator($link);

        if ($text === '' && !leanwi_acr_has_named_duplicate_link($link, $href)) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Links',
                'Link has no accessible text.',
                'Destination: ' . leanwi_acr_shorten($href, 140),
                'Add descriptive link text or an accessible label.',
                $element,
                'links',
                $locator
            );
        } elseif (in_array($lower, $vague, true)) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Links',
                'Link text is vague.',
                'Current link text: "' . $text . '"',
                'Use link text that makes sense out of context, such as the document, page, or action name.',
                $element,
                'links',
                $locator
            );
        } elseif (preg_match('/^https?:\/\/\S+$/i', $text)) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Links',
                'Link text is a raw URL.',
                'Current link text: "' . leanwi_acr_shorten($text, 120) . '"',
                'Replace raw URLs with descriptive link text.',
                $element,
                'links',
                $locator
            );
        }

        $extension = strtolower(pathinfo(parse_url($href, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        if (in_array($extension, $document_extensions, true)) {
            $issues[] = leanwi_acr_issue(
                'review',
                'Documents',
                'Linked document should be checked for accessibility.',
                'Document link: ' . leanwi_acr_shorten($href, 140),
                'Confirm this file is accessible, or convert the information into a web page when possible.',
                $element,
                'documents',
                $locator
            );
        }

        if ($text !== '') {
            if (!isset($link_map[$lower])) {
                $link_map[$lower] = [
                    'hrefs' => [],
                    'locator' => $locator,
                ];
            }
            $link_map[$lower]['hrefs'][esc_url_raw($href)] = true;
        }
    }

    foreach ($link_map as $text => $data) {
        if (count($data['hrefs']) > 1 && !in_array($text, $vague, true)) {
            $issues[] = leanwi_acr_issue(
                'warning',
                'Links',
                'Same link text points to different places.',
                'Link text "' . $text . '" goes to ' . count($data['hrefs']) . ' different destinations.',
                'Make each link label specific enough to distinguish its destination.',
                'a',
                'links',
                $data['locator']
            );
        }
    }
}

function leanwi_acr_has_named_duplicate_link($link, $href) {
    $target_href = esc_url_raw(leanwi_acr_absolute_url($href));
    if ($target_href === '') {
        return false;
    }

    $xpath = new DOMXPath($link->ownerDocument);
    foreach ($xpath->query('//a[@href]') as $candidate) {
        if ($candidate === $link || (method_exists($candidate, 'isSameNode') && $candidate->isSameNode($link))) {
            continue;
        }

        $candidate_href = esc_url_raw(leanwi_acr_absolute_url($candidate->getAttribute('href')));
        if ($candidate_href !== $target_href) {
            continue;
        }

        if (leanwi_acr_link_accessible_text($candidate) !== '') {
            return true;
        }
    }

    return false;
}

function leanwi_acr_link_accessible_text($link) {
    $label = leanwi_acr_aria_labelledby_text($link);
    if ($label !== '') {
        return $label;
    }

    $aria_label = leanwi_acr_clean_text($link->getAttribute('aria-label'));
    if ($aria_label !== '') {
        return $aria_label;
    }

    $text = leanwi_acr_clean_text($link->textContent);
    if ($text !== '') {
        return $text;
    }

    $parts = [];
    $xpath = new DOMXPath($link->ownerDocument);
    foreach ($xpath->query('.//img[@alt]', $link) as $image) {
        $alt = leanwi_acr_clean_text($image->getAttribute('alt'));
        if ($alt !== '') {
            $parts[] = $alt;
        }
    }

    $image_alt = leanwi_acr_clean_text(implode(' ', $parts));
    if ($image_alt !== '') {
        return $image_alt;
    }

    return leanwi_acr_clean_text($link->getAttribute('title'));
}

function leanwi_acr_aria_labelledby_text($node) {
    $ids = preg_split('/\s+/', trim($node->getAttribute('aria-labelledby')));
    if (empty($ids)) {
        return '';
    }

    $parts = [];
    $xpath = new DOMXPath($node->ownerDocument);
    foreach ($ids as $id) {
        $safe_id = preg_replace('/[^A-Za-z0-9_\-:.]/', '', $id);
        if ($safe_id === '') {
            continue;
        }
        $matches = $xpath->query('//*[@id="' . $safe_id . '"]');
        if ($matches && $matches->length > 0) {
            $parts[] = leanwi_acr_clean_text($matches->item(0)->textContent);
        }
    }

    return leanwi_acr_clean_text(implode(' ', $parts));
}

function leanwi_acr_check_tables($xpath, &$issues) {
    $tables = $xpath->query('//table');
    foreach ($tables as $table) {
        $role = strtolower($table->getAttribute('role'));
        if (in_array($role, ['presentation', 'none'], true)) {
            continue;
        }

        $locator = leanwi_acr_node_locator($table);
        $table_xpath = new DOMXPath($table->ownerDocument);
        $has_th = $table_xpath->query('.//th', $table)->length > 0;
        $has_caption = $table_xpath->query('.//caption', $table)->length > 0;
        $rows = $table_xpath->query('.//tr', $table)->length;
        if (leanwi_acr_should_skip_table($table, $table_xpath, $rows, $has_th, $has_caption)) {
            continue;
        }

        if ($rows > 1 && !$has_th) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Tables',
                'Table does not appear to have header cells.',
                'No table header cells were found.',
                'Use a real header row or column so screen readers can associate data cells with headings.',
                'table',
                'tables',
                $locator
            );
        }

        if ($rows > 2 && !$has_caption) {
            $issues[] = leanwi_acr_issue(
                'warning',
                'Tables',
                'Data table may need a caption or short introduction.',
                'No table caption was found.',
                'Add a short caption or nearby sentence that explains what the table contains.',
                'table',
                'tables',
                $locator
            );
        }
    }
}

function leanwi_acr_should_skip_table($table, $table_xpath, $rows, $has_th, $has_caption) {
    if ($rows < 1) {
        return true;
    }

    if (leanwi_acr_is_hidden_content($table)) {
        return true;
    }

    if ($has_th || $has_caption) {
        return false;
    }

    $max_cells_in_row = 0;
    foreach ($table_xpath->query('.//tr', $table) as $row) {
        $cell_count = $table_xpath->query('./th|./td', $row)->length;
        $max_cells_in_row = max($max_cells_in_row, $cell_count);
    }

    return $max_cells_in_row <= 1;
}

function leanwi_acr_is_hidden_content($node) {
    while ($node instanceof DOMElement) {
        if ($node->hasAttribute('hidden') || strtolower($node->getAttribute('aria-hidden')) === 'true') {
            return true;
        }

        $styles = leanwi_acr_parse_style($node->getAttribute('style'));
        if (($styles['display'] ?? '') === 'none' || ($styles['visibility'] ?? '') === 'hidden') {
            return true;
        }

        if (leanwi_acr_has_all_device_hidden_classes($node)) {
            return true;
        }

        $node = $node->parentNode;
    }

    return false;
}

function leanwi_acr_has_all_device_hidden_classes($node) {
    $class = strtolower($node->getAttribute('class'));
    if ($class === '') {
        return false;
    }

    $classes = preg_split('/\s+/', $class);
    $class_map = array_fill_keys($classes, true);

    foreach (['et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_module'] as $prefix) {
        if (isset($class_map[$prefix . '_hidden'], $class_map[$prefix . '_hidden_tablet'], $class_map[$prefix . '_hidden_phone'])) {
            return true;
        }
    }

    foreach ($classes as $class_name) {
        if (preg_match('/^(.+)_hidden$/', $class_name, $matches)) {
            $prefix = $matches[1];
            if (isset($class_map[$prefix . '_hidden_tablet'], $class_map[$prefix . '_hidden_phone'])) {
                return true;
            }
        }
    }

    $desktop_hidden = false;
    $tablet_hidden = false;
    $phone_hidden = false;

    foreach ($classes as $class_name) {
        if (preg_match('/(^|[_-])(desktop|computer)(_)?hidden$|(^|[_-])hidden(_|-)?desktop$|^et_pb_hide_on_desktop$/', $class_name)) {
            $desktop_hidden = true;
        }
        if (preg_match('/(^|[_-])tablet(_)?hidden$|(^|[_-])hidden(_|-)?tablet$|^et_pb_hide_on_tablet$/', $class_name)) {
            $tablet_hidden = true;
        }
        if (preg_match('/(^|[_-])(phone|mobile)(_)?hidden$|(^|[_-])hidden(_|-)?(phone|mobile)$|^et_pb_hide_on_phone$/', $class_name)) {
            $phone_hidden = true;
        }
    }

    return $desktop_hidden && $tablet_hidden && $phone_hidden;
}

function leanwi_acr_check_forms($xpath, &$issues) {
    $labels = [];
    foreach ($xpath->query('//label[@for]') as $label) {
        $for = trim($label->getAttribute('for'));
        if ($for !== '') {
            $labels[$for] = true;
        }
    }

    $fields = $xpath->query('//input|//select|//textarea');
    foreach ($fields as $field) {
        if (leanwi_acr_is_ignored_form_control($field)) {
            continue;
        }
        if (leanwi_acr_is_hidden_content($field)) {
            continue;
        }
        if (leanwi_acr_is_known_labeled_widget_field($field)) {
            continue;
        }

        $id = trim($field->getAttribute('id'));
        $has_label = ($id && isset($labels[$id]))
            || $field->hasAttribute('aria-label')
            || $field->hasAttribute('aria-labelledby')
            || $field->hasAttribute('title')
            || leanwi_acr_has_ancestor_tag($field, 'label')
            || leanwi_acr_has_nearby_label($field);

        if (!$has_label) {
            $locator = leanwi_acr_node_locator($field);
            $detail = $field->hasAttribute('placeholder')
                ? 'Placeholder text is "' . $field->getAttribute('placeholder') . '".'
                : 'No label, aria-label, aria-labelledby, or title was found.';

            $issues[] = leanwi_acr_issue(
                'fix',
                'Forms',
                'Form field may be missing a label.',
                $detail,
                'Add a visible label connected to the field. Placeholder text should not be the only label.',
                $field->nodeName,
                'forms',
                $locator
            );
        }
    }
}

function leanwi_acr_has_nearby_label($field) {
    if (leanwi_acr_has_previous_label($field)) {
        return true;
    }

    return leanwi_acr_has_preceding_label_for_next_form_control($field);
}

function leanwi_acr_has_previous_label($field) {
    $node = $field->previousSibling;

    while ($node) {
        if ($node instanceof DOMCharacterData) {
            if (leanwi_acr_clean_text($node->textContent) !== '') {
                return false;
            }
            $node = $node->previousSibling;
            continue;
        }

        if ($node instanceof DOMElement) {
            $tag = strtolower($node->nodeName);
            if ($tag === 'label') {
                return leanwi_acr_clean_text($node->textContent) !== '';
            }
            if (in_array($tag, ['br', 'script', 'style'], true)) {
                $node = $node->previousSibling;
                continue;
            }
            return false;
        }

        $node = $node->previousSibling;
    }

    return false;
}

function leanwi_acr_has_preceding_label_for_next_form_control($field) {
    if (!($field instanceof DOMElement) || !method_exists($field, 'getNodePath')) {
        return false;
    }

    $form = leanwi_acr_nearest_ancestor_element($field, 'form');
    if (!$form) {
        return false;
    }

    $field_path = $field->getNodePath();
    $xpath = new DOMXPath($field->ownerDocument);
    $last_label = '';

    foreach ($xpath->query('.//label|.//input|.//select|.//textarea', $form) as $node) {
        if ($node instanceof DOMElement && method_exists($node, 'getNodePath') && $node->getNodePath() === $field_path) {
            return $last_label !== '';
        }

        $tag = strtolower($node->nodeName);
        if ($tag === 'label') {
            $last_label = leanwi_acr_clean_text($node->textContent);
            continue;
        }

        if (in_array($tag, ['input', 'select', 'textarea'], true) && !leanwi_acr_is_ignored_form_control($node)) {
            $last_label = '';
        }
    }

    return false;
}

function leanwi_acr_is_known_labeled_widget_field($field) {
    if (!($field instanceof DOMElement)) {
        return false;
    }

    $form = leanwi_acr_nearest_ancestor_element($field, 'form');
    if (!$form) {
        return false;
    }

    $class = ' ' . strtolower($form->getAttribute('class')) . ' ';
    $action = strtolower($form->getAttribute('action'));
    $is_biblio_search = strpos($class, ' biblio-search ') !== false
        || strpos($action, 'bibliocommons.com/search') !== false;

    if (!$is_biblio_search) {
        return false;
    }

    $xpath = new DOMXPath($field->ownerDocument);
    foreach ($xpath->query('.//label', $form) as $label) {
        if (leanwi_acr_clean_text($label->textContent) !== '') {
            return true;
        }
    }

    return false;
}

function leanwi_acr_check_buttons($xpath, &$issues) {
    $vague = ['submit', 'go', 'click', 'next', 'continue'];
    foreach ($xpath->query('//button') as $button) {
        $name = leanwi_acr_clean_text($button->textContent ?: $button->getAttribute('aria-label') ?: $button->getAttribute('title'));
        if ($name !== '' && in_array(strtolower($name), $vague, true)) {
            $locator = leanwi_acr_node_locator($button);
            $issues[] = leanwi_acr_issue(
                'warning',
                'Buttons',
                'Button text may be vague.',
                'Current button text: "' . $name . '"',
                'Use action-specific button text when possible.',
                'button',
                'buttons',
                $locator
            );
        }
    }
}

function leanwi_acr_check_media_embeds($xpath, &$issues) {
    foreach ($xpath->query('//iframe') as $iframe) {
        $src = $iframe->getAttribute('src');
        $locator = leanwi_acr_node_locator($iframe);
        if (!$iframe->hasAttribute('title')) {
            $issues[] = leanwi_acr_issue(
                'fix',
                'Embeds',
                'Embedded frame is missing a title.',
                'Frame source: ' . leanwi_acr_shorten($src, 140),
                'Add a short title that describes the embedded content, such as map, calendar, or video.',
                'iframe',
                'embeds',
                $locator
            );
        }
        if (preg_match('/calendar|maps|youtube|vimeo|facebook|twitter/i', $src)) {
            $issues[] = leanwi_acr_issue(
                'review',
                'Embeds',
                'Embedded third-party content may need review.',
                'Frame source: ' . leanwi_acr_shorten($src, 140),
                'Confirm the embed can be used with a keyboard and has an accessible name.',
                'iframe',
                'embeds',
                $locator
            );
        }
    }

    foreach ($xpath->query('//video') as $video) {
        $locator = leanwi_acr_node_locator($video);
        $tracks = (new DOMXPath($video->ownerDocument))->query('.//track[contains(" captions subtitles ", concat(" ", translate(@kind, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), " "))]', $video);
        if ($video->hasAttribute('autoplay')) {
            $issues[] = leanwi_acr_issue('fix', 'Media', 'Media is set to autoplay.', 'Autoplay can interfere with screen readers and keyboard users.', 'Remove autoplay unless there is a strong accessibility-supported reason.', 'video', 'media', $locator);
        }
        if (!$tracks || $tracks->length === 0) {
            $issues[] = leanwi_acr_issue('review', 'Media', 'Video may need captions or a transcript.', 'No captions or subtitles track was found in the video element.', 'Provide accurate captions for spoken content and meaningful audio.', 'video', 'media', $locator);
        }
    }

    foreach ($xpath->query('//audio') as $audio) {
        $locator = leanwi_acr_node_locator($audio);
        if ($audio->hasAttribute('autoplay')) {
            $issues[] = leanwi_acr_issue('fix', 'Media', 'Media is set to autoplay.', 'Autoplay can interfere with screen readers and keyboard users.', 'Remove autoplay unless there is a strong accessibility-supported reason.', 'audio', 'media', $locator);
        }
        $issues[] = leanwi_acr_issue('review', 'Media', 'Audio content may need a transcript.', 'Audio elements should have a nearby transcript when they include meaningful speech.', 'Confirm a transcript is available near the audio.', 'audio', 'media', $locator);
    }
}

function leanwi_acr_check_manual_lists($xpath, &$issues) {
    foreach ($xpath->query('//p') as $paragraph) {
        $text = leanwi_acr_text_with_breaks($paragraph);
        if (preg_match('/(^|\n)\s*(?:[-*]|\d+[.)])\s+\S+/m', $text)) {
            $locator = leanwi_acr_node_locator($paragraph);
            $issues[] = leanwi_acr_issue(
                'warning',
                'Lists',
                'Text may be a manually typed list.',
                'A paragraph looks like a list typed with manual markers.',
                'Use the WordPress bullet or numbered list block instead of typing list markers by hand.',
                'p',
                'lists',
                $locator
            );
        }
    }
}

function leanwi_acr_check_color_cues($xpath, &$issues) {
    foreach ($xpath->query('//p|//li') as $node) {
        $text = leanwi_acr_clean_text($node->textContent);
        if (preg_match('/\b(?:items?|fields?|links?|buttons?|text|rows?)\s+(?:in|marked|shown as|shown in)\s+(?:red|green|blue|yellow|orange|purple)\b|\bclick\s+(?:the\s+)?(?:red|green|blue|yellow|orange|purple)\b|\b(?:red|green|blue|yellow|orange|purple)\s+(?:button|link|text|box|row)\b/i', $text, $match)) {
            $locator = leanwi_acr_node_locator($node);
            $issues[] = leanwi_acr_issue(
                'fix',
                'Color',
                'Text may rely on color alone.',
                'Found: "' . leanwi_acr_shorten($match[0], 120) . '"',
                'Do not use color as the only way to identify required items, status, or actions. Add text or icons too.',
                $node->nodeName,
                'color',
                $locator
            );
        }
    }
}

function leanwi_acr_check_inline_contrast($xpath, &$issues) {
    foreach ($xpath->query('//*[@style]') as $node) {
        $text = leanwi_acr_clean_text($node->textContent);
        if ($text === '') {
            continue;
        }

        $styles = leanwi_acr_parse_style($node->getAttribute('style'));
        $foreground = leanwi_acr_extract_color($styles['color'] ?? '');
        $background = leanwi_acr_extract_color($styles['background-color'] ?? ($styles['background'] ?? ''));
        if (!$foreground || !$background) {
            continue;
        }

        $ratio = leanwi_acr_contrast_ratio($foreground, $background);
        if ($ratio < 4.5) {
            $locator = leanwi_acr_node_locator($node);
            $issues[] = leanwi_acr_issue(
                'fix',
                'Color contrast',
                'Inline text color may not have enough contrast.',
                'Estimated contrast ratio is ' . number_format($ratio, 2) . ':1.',
                'Use a text/background color combination with at least 4.5:1 contrast for ordinary text.',
                $node->nodeName,
                'contrast',
                $locator
            );
        }
    }
}

function leanwi_acr_add_duplicate_title_issues(&$reports) {
    $title_map = [];
    foreach ($reports as $index => $report) {
        $title = strtolower(leanwi_acr_clean_text($report['title']));
        if ($title !== '') {
            $title_map[$title][] = $index;
        }
    }

    foreach ($title_map as $title => $indexes) {
        if (count($indexes) < 2) {
            continue;
        }
        foreach ($indexes as $index) {
            $reports[$index]['issues'][] = leanwi_acr_issue(
                'warning',
                'Page title',
                'Page title is duplicated in this report.',
                'The title "' . $title . '" appeared on ' . count($indexes) . ' scanned items.',
                'Give each page a unique title that describes its specific content.',
                'title',
                'page-title'
            );
        }
    }
}

function leanwi_acr_summarize_results($reports) {
    $summary = [
        'posts_scanned' => count($reports),
        'total' => 0,
        'fix' => 0,
        'review' => 0,
        'warning' => 0,
    ];

    foreach ($reports as $report) {
        foreach ($report['issues'] as $issue) {
            $summary['total']++;
            if (isset($summary[$issue['severity']])) {
                $summary[$issue['severity']]++;
            }
        }
    }

    return $summary;
}

function leanwi_acr_render_post_report($post_report) {
    $post = $post_report['post'];
    $issues = $post_report['issues'];
    $ocr_images = array_map(function ($image) use ($post_report) {
        if (!empty($image['locator'])) {
            $image['highlight_url'] = leanwi_acr_highlight_url_for_locator($post_report, $image['locator']);
        }
        return $image;
    }, $post_report['ocr_images']);
    ?>
    <section class="leanwi-focused-post" data-post-id="<?php echo esc_attr($post->ID); ?>">
        <header class="leanwi-focused-post-header">
            <div>
                <h2><?php echo esc_html($post_report['title'] ?: '(no title)'); ?></h2>
                <p>
                    <span><?php echo esc_html(ucfirst($post->post_type)); ?></span>
                    <span>Last modified <?php echo esc_html(get_the_modified_date('Y-m-d g:i a', $post)); ?></span>
                </p>
            </div>
            <div class="leanwi-focused-actions">
                <?php if ($post_report['edit_link']) : ?>
                    <a class="button" href="<?php echo esc_url($post_report['edit_link']); ?>">Edit</a>
                <?php endif; ?>
                <a class="button" href="<?php echo esc_url($post_report['permalink']); ?>" target="_blank" rel="noopener">Open Page</a>
                <a class="button" href="<?php echo esc_url(leanwi_acr_review_request_url($post_report)); ?>">Ask for Review</a>
            </div>
        </header>

        <div class="leanwi-focused-issue-list">
            <?php if (empty($issues)) : ?>
                <p class="leanwi-focused-empty">No focused content findings were found before OCR.</p>
            <?php endif; ?>

            <?php foreach ($issues as $issue) : ?>
                <?php leanwi_acr_render_issue($issue, $post_report); ?>
            <?php endforeach; ?>
        </div>

        <div class="leanwi-focused-ocr-results" aria-live="polite"></div>
        <script type="application/json" class="leanwi-focused-ocr-data"><?php echo wp_json_encode($ocr_images); ?></script>
    </section>
    <?php
}

function leanwi_acr_review_request_url($post_report) {
    $lines = [
        'Focused accessibility review requested for:',
        ($post_report['title'] ?: '(no title)') . ' - ' . $post_report['permalink'],
        '',
        'Focused report findings:',
    ];

    $issues = array_slice($post_report['issues'], 0, 12);
    foreach ($issues as $issue) {
        $lines[] = '- [' . strtoupper($issue['severity']) . '] ' . $issue['category'] . ': ' . $issue['message'];
    }

    if (count($post_report['issues']) > count($issues)) {
        $lines[] = '- Plus ' . (count($post_report['issues']) - count($issues)) . ' more findings.';
    }

    $lines[] = '';
    $lines[] = 'Please review this page after I make updates.';

    return add_query_arg(
        [
            'page' => 'leanwi-site-review-request',
            'leanwi_review_context' => implode("\n", $lines),
        ],
        admin_url('admin.php')
    );
}

function leanwi_acr_highlight_url_for_locator($post_report, $locator) {
    if (empty($locator) || !is_array($locator)) {
        return '';
    }

    $post = $post_report['post'];
    return add_query_arg(
        [
            'leanwi_acr_highlight' => '1',
            'leanwi_acr_post' => $post->ID,
            'leanwi_acr_locator' => leanwi_acr_encode_locator($locator),
            'leanwi_acr_nonce' => wp_create_nonce('leanwi_acr_highlight_' . $post->ID),
        ],
        $post_report['permalink']
    );
}

function leanwi_acr_encode_locator($locator) {
    $json = wp_json_encode($locator);
    if (!is_string($json) || $json === '') {
        return '';
    }

    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

function leanwi_acr_render_issue($issue, $post_report = null) {
    $tutorials = leanwi_acr_tutorial_links();
    $tutorial = $tutorials[$issue['tutorial_key']] ?? '';
    $highlight_url = $post_report ? leanwi_acr_highlight_url_for_locator($post_report, $issue['locator'] ?? []) : '';
    ?>
    <article class="leanwi-focused-issue leanwi-focused-<?php echo esc_attr($issue['severity']); ?>">
        <div>
            <span class="leanwi-focused-badge"><?php echo esc_html($issue['severity']); ?></span>
            <strong><?php echo esc_html($issue['category']); ?></strong>
        </div>
        <h3><?php echo esc_html($issue['message']); ?></h3>
        <?php if (!empty($issue['detail'])) : ?>
            <p class="leanwi-focused-detail"><?php echo esc_html($issue['detail']); ?></p>
        <?php endif; ?>
        <?php if (!empty($issue['suggestion'])) : ?>
            <p><?php echo esc_html($issue['suggestion']); ?></p>
        <?php endif; ?>
        <?php if (!empty($issue['element'])) : ?>
            <p class="leanwi-focused-detail">Element: <?php echo esc_html($issue['element']); ?></p>
        <?php endif; ?>
        <p class="leanwi-focused-issue-actions">
            <?php if ($highlight_url) : ?>
                <a class="button button-small" href="<?php echo esc_url($highlight_url); ?>" target="_blank" rel="noopener">View on Page</a>
            <?php endif; ?>
            <?php if ($tutorial) : ?>
                <a class="button button-small" href="<?php echo esc_url($tutorial); ?>" target="_blank" rel="noopener">Tutorial</a>
            <?php endif; ?>
        </p>
    </article>
    <?php
}

function leanwi_acr_issue($severity, $category, $message, $detail = '', $suggestion = '', $element = '', $tutorial_key = '', $locator = []) {
    return [
        'severity' => $severity,
        'category' => $category,
        'message' => $message,
        'detail' => $detail,
        'suggestion' => $suggestion,
        'element' => $element,
        'tutorial_key' => $tutorial_key,
        'locator' => is_array($locator) ? $locator : [],
    ];
}

function leanwi_acr_node_locator($node) {
    if (!($node instanceof DOMElement)) {
        return [];
    }

    $tag = strtolower($node->nodeName);
    $attributes = [];
    foreach (['id', 'class', 'href', 'src', 'data-src', 'data-lazy-src', 'alt', 'name', 'type', 'title', 'aria-label', 'style'] as $attribute) {
        if (!$node->hasAttribute($attribute)) {
            continue;
        }
        $value = leanwi_acr_clean_text($node->getAttribute($attribute));
        if ($value === '') {
            continue;
        }
        $attributes[$attribute] = leanwi_acr_shorten($value, 240);
    }

    if ($tag === 'img') {
        $src = leanwi_acr_get_image_source($node);
        if ($src !== '') {
            $attributes['src'] = $src;
        }
    }

    if ($tag === 'a' && !empty($attributes['href'])) {
        $attributes['href'] = leanwi_acr_absolute_url($attributes['href']);
    }

    if ($tag === 'a') {
        $child_alt = [];
        $xpath = new DOMXPath($node->ownerDocument);
        foreach ($xpath->query('.//img[@alt]', $node) as $image) {
            $alt = leanwi_acr_clean_text($image->getAttribute('alt'));
            if ($alt !== '') {
                $child_alt[] = $alt;
            }
        }
        if (!empty($child_alt)) {
            $attributes['child_alt'] = leanwi_acr_shorten(implode(' ', $child_alt), 180);
        }
    }

    return [
        'tag' => $tag,
        'index' => leanwi_acr_node_tag_index($node),
        'text' => leanwi_acr_shorten(leanwi_acr_clean_text($node->textContent), 180),
        'attrs' => $attributes,
        'context' => leanwi_acr_node_context($node),
    ];
}

function leanwi_acr_node_context($node) {
    $context = [];
    $parent = $node->parentNode;

    while ($parent instanceof DOMElement && count($context) < 6) {
        if ($parent->getAttribute('id') === 'leanwi-acr-root') {
            break;
        }

        $item = [
            'tag' => strtolower($parent->nodeName),
        ];

        foreach (['id', 'class', 'role', 'aria-labelledby', 'aria-controls'] as $attribute) {
            if (!$parent->hasAttribute($attribute)) {
                continue;
            }
            $value = leanwi_acr_clean_text($parent->getAttribute($attribute));
            if ($value !== '') {
                $item[$attribute] = leanwi_acr_shorten($value, 180);
            }
        }

        if (count($item) > 1) {
            $context[] = $item;
        }

        $parent = $parent->parentNode;
    }

    return $context;
}

function leanwi_acr_node_tag_index($node) {
    if (!($node instanceof DOMElement)) {
        return 0;
    }

    $tag = strtolower($node->nodeName);
    $xpath = new DOMXPath($node->ownerDocument);
    $nodes = $xpath->query('//*[@id="leanwi-acr-root"]//' . $tag);
    if (!$nodes || $nodes->length === 0) {
        $nodes = $xpath->query('//' . $tag);
    }

    $index = 0;
    foreach ($nodes as $candidate) {
        if ($candidate === $node || (method_exists($candidate, 'isSameNode') && $candidate->isSameNode($node))) {
            return $index;
        }
        $index++;
    }

    return 0;
}

function leanwi_acr_tutorial_links() {
    return [
        'alt-text' => 'https://www.w3.org/WAI/tutorials/images/',
        'infographics' => 'https://www.w3.org/WAI/tutorials/images/complex/',
        'headings' => 'https://www.w3.org/WAI/tutorials/page-structure/headings/',
        'links' => 'https://www.w3.org/WAI/tips/writing/#write-meaningful-link-text',
        'documents' => 'https://www.w3.org/WAI/teach-advocate/accessibility-training/',
        'tables' => 'https://www.w3.org/WAI/tutorials/tables/',
        'forms' => 'https://www.w3.org/WAI/tutorials/forms/labels/',
        'media' => 'https://www.w3.org/WAI/media/av/',
        'color' => 'https://www.w3.org/WAI/tips/designing/#dont-use-color-alone-to-convey-information',
        'contrast' => 'https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html',
        'embeds' => 'https://www.w3.org/WAI/tutorials/page-structure/',
        'lists' => 'https://www.w3.org/WAI/tutorials/page-structure/content/',
        'page-title' => 'https://www.w3.org/WAI/tutorials/page-structure/page-titles/',
        'buttons' => 'https://www.w3.org/WAI/tutorials/forms/',
    ];
}

function leanwi_acr_get_image_source($image) {
    foreach (['src', 'data-src', 'data-lazy-src'] as $attribute) {
        $value = trim($image->getAttribute($attribute));
        if ($value !== '') {
            return leanwi_acr_absolute_url($value);
        }
    }

    $srcset = trim($image->getAttribute('srcset'));
    if ($srcset !== '') {
        $first = trim(explode(',', $srcset)[0]);
        $parts = preg_split('/\s+/', $first);
        return leanwi_acr_absolute_url($parts[0] ?? '');
    }

    return '';
}

function leanwi_acr_absolute_url($url) {
    $url = leanwi_acr_extract_url_like($url);
    if ($url === '' || preg_match('/^(https?:)?\/\//i', $url) || strpos($url, 'data:') === 0) {
        return $url;
    }

    return esc_url_raw(home_url($url[0] === '/' ? $url : '/' . $url));
}

function leanwi_acr_extract_url_like($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('/https?:\/\/[^\s\]\)\'"<>]+/i', $url, $match)) {
        return $match[0];
    }

    if (preg_match('/\/\/[^\s\]\)\'"<>]+/i', $url, $match)) {
        return $match[0];
    }

    return $url;
}

function leanwi_acr_is_ocr_candidate($src) {
    if ($src === '' || strpos($src, 'data:') === 0) {
        return false;
    }
    $extension = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'], true);
}

function leanwi_acr_is_suspicious_image($image, $src, $alt) {
    $combined = strtolower(implode(' ', [
        $src,
        $alt,
        $image->getAttribute('class'),
        $image->getAttribute('id'),
        $image->getAttribute('title'),
    ]));
    $keywords = ['infographic', 'info-graphic', 'chart', 'graph', 'diagram', 'flyer', 'flier', 'poster', 'schedule', 'calendar', 'menu', 'map', 'announcement', 'event', 'newsletter', 'banner'];
    foreach ($keywords as $keyword) {
        if (strpos($combined, $keyword) !== false) {
            return true;
        }
    }

    $width = intval($image->getAttribute('width'));
    $height = intval($image->getAttribute('height'));
    return $width >= 500 && $height >= 250;
}

function leanwi_acr_has_ancestor_tag($node, $tag) {
    $parent = $node->parentNode;
    while ($parent) {
        if (strtolower($parent->nodeName) === strtolower($tag)) {
            return true;
        }
        $parent = $parent->parentNode;
    }
    return false;
}

function leanwi_acr_nearest_ancestor_element($node, $tag) {
    $parent = $node->parentNode;
    $target = strtolower($tag);

    while ($parent) {
        if ($parent instanceof DOMElement && strtolower($parent->nodeName) === $target) {
            return $parent;
        }
        $parent = $parent->parentNode;
    }

    return null;
}

function leanwi_acr_is_ignored_form_control($field) {
    if (!($field instanceof DOMElement)) {
        return true;
    }

    $tag = strtolower($field->nodeName);
    $type = strtolower($field->getAttribute('type'));

    return $tag === 'input' && in_array($type, ['hidden', 'submit', 'button', 'reset', 'image'], true);
}

function leanwi_acr_text_with_breaks($node) {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    return html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
}

function leanwi_acr_clean_text($value) {
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

function leanwi_acr_shorten($value, $limit) {
    $value = leanwi_acr_clean_text($value);
    if (strlen($value) <= $limit) {
        return $value;
    }
    return rtrim(substr($value, 0, max(0, $limit - 1))) . '...';
}

function leanwi_acr_parse_style($style) {
    $styles = [];
    foreach (explode(';', $style) as $part) {
        if (strpos($part, ':') === false) {
            continue;
        }
        [$key, $value] = explode(':', $part, 2);
        $styles[strtolower(trim($key))] = strtolower(trim($value));
    }
    return $styles;
}

function leanwi_acr_extract_color($value) {
    $value = strtolower(trim($value));
    if ($value === '' || in_array($value, ['transparent', 'inherit', 'initial', 'unset'], true)) {
        return null;
    }

    if (preg_match('/#([0-9a-f]{3}|[0-9a-f]{6})\b/i', $value, $match)) {
        $raw = $match[1];
        if (strlen($raw) === 3) {
            $raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
        }
        return [
            hexdec(substr($raw, 0, 2)),
            hexdec(substr($raw, 2, 2)),
            hexdec(substr($raw, 4, 2)),
        ];
    }

    if (preg_match('/rgba?\(([^)]+)\)/i', $value, $match)) {
        $parts = array_slice(array_map('trim', explode(',', $match[1])), 0, 3);
        if (count($parts) === 3) {
            return array_map('intval', $parts);
        }
    }

    $named = [
        'black' => [0, 0, 0],
        'white' => [255, 255, 255],
        'red' => [255, 0, 0],
        'green' => [0, 128, 0],
        'blue' => [0, 0, 255],
        'yellow' => [255, 255, 0],
        'orange' => [255, 165, 0],
        'purple' => [128, 0, 128],
        'gray' => [128, 128, 128],
        'grey' => [128, 128, 128],
        'lightgray' => [211, 211, 211],
        'lightgrey' => [211, 211, 211],
        'darkgray' => [169, 169, 169],
        'darkgrey' => [169, 169, 169],
    ];

    foreach ($named as $name => $rgb) {
        if (preg_match('/\b' . preg_quote($name, '/') . '\b/', $value)) {
            return $rgb;
        }
    }

    return null;
}

function leanwi_acr_contrast_ratio($foreground, $background) {
    $l1 = leanwi_acr_relative_luminance($foreground);
    $l2 = leanwi_acr_relative_luminance($background);
    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);
    return ($lighter + 0.05) / ($darker + 0.05);
}

function leanwi_acr_relative_luminance($rgb) {
    $channels = [];
    foreach ($rgb as $value) {
        $srgb = $value / 255;
        $channels[] = $srgb <= 0.03928 ? $srgb / 12.92 : pow(($srgb + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}
