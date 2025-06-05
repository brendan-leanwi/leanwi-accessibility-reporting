<?php

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