<?php

add_filter('zw_webapp_send_notification', 'zw_webapp_send_notification', 10, 2);
add_filter('zw_webapp_title', 'zw_webapp_push_title', 10, 2);

add_action('post_submitbox_misc_actions', function () {
    $post = get_post();
    if (!$post || $post->post_type !== 'post' || !current_user_can('edit_others_posts')) {
        return;
    }
    $enabled = (bool) get_post_meta($post->ID, 'push_post', true);
    wp_nonce_field('zw_push_post_nonce', 'zw_push_post_nonce');
    ?>
    <div class="misc-pub-section misc-pub-push">
        <span class="dashicons dashicons-bell" style="color: #82878c;"></span>
        <?php echo esc_html__('Pushbericht:', 'streekomroep'); ?>
        <input type="hidden" id="push-post-value" name="push_post" value="<?php echo $enabled ? '1' : '0'; ?>" />
        <a href="#" id="push-toggle" role="button"><b><?php echo $enabled ? esc_html__('Ja', 'streekomroep') : esc_html__('Nee', 'streekomroep'); ?></b></a>
    </div>
    <script>
    jQuery(function($) {
        $('#push-toggle').on('click', function(e) {
            e.preventDefault();
            var $input = $('#push-post-value');
            var on = $input.val() === '1';
            $input.val(on ? '0' : '1');
            $(this).find('b').text(on ? 'Nee' : 'Ja');
        });
    });
    </script>
    <?php
});

add_action('save_post_post', function ($post_id) {
    if (!isset($_POST['zw_push_post_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['zw_push_post_nonce'])), 'zw_push_post_nonce')) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if (!current_user_can('edit_others_posts')) {
        return;
    }
    update_post_meta($post_id, 'push_post', !empty($_POST['push_post']) ? '1' : '0');
});

/*
 * Block editor support for the push toggle. The classic submit box above keeps
 * working as is; it stores '1'/'0' in the same meta key, and its save handler needs
 * the classic nonce, so the block editor's meta-box-loader request never touches it.
 */
add_action('init', function () {
    register_post_meta('post', 'push_post', [
        'type' => 'boolean',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
        // Same storage format as the classic toggle.
        'sanitize_callback' => fn ($value) => $value ? '1' : '0',
        // Editing the post is enough to send the field along unchanged. Changing
        // the value is restricted in zw_push_post_guard_rest_change().
        'auth_callback' => fn ($allowed, $meta_key, $post_id) => current_user_can('edit_post', $post_id),
    ]);
});

/**
 * Only users who may use the classic toggle can change push_post over REST.
 * Checked before the post is saved so a refusal does not leave a half-saved post.
 * An unchanged or absent value is always accepted, because the block editor
 * may send the value along with other meta edits.
 */
function zw_push_post_guard_rest_change($prepared_post, WP_REST_Request $request)
{
    $meta = $request['meta'];
    if (!is_array($meta) || !array_key_exists('push_post', $meta) || current_user_can('edit_others_posts')) {
        return $prepared_post;
    }

    $stored = !empty($prepared_post->ID) && (bool) get_post_meta($prepared_post->ID, 'push_post', true);
    if (rest_sanitize_boolean($meta['push_post']) === $stored) {
        return $prepared_post;
    }

    return new WP_Error(
        'rest_cannot_edit_push_post',
        __('Sorry, you are not allowed to change the push notification setting.', 'streekomroep'),
        ['status' => rest_authorization_required_code()]
    );
}
add_filter('rest_pre_insert_post', 'zw_push_post_guard_rest_change', 10, 2);

add_action('enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'post' || !current_user_can('edit_others_posts')) {
        return;
    }
    wp_enqueue_script(
        'streekomroep-push-toggle',
        get_theme_file_uri('static/editor-push-toggle.js'),
        ['wp-plugins', 'wp-editor', 'wp-core-data', 'wp-components', 'wp-element'],
        wp_get_theme()->get('Version'),
        true
    );
});

function zw_webapp_send_notification($do_send, $post_id)
{
    return (bool) get_post_meta($post_id, 'push_post', true);
}

function zw_webapp_push_title($title, $post_id)
{
    if (has_term('breaking', 'ranking', $post_id)) {
        return 'Breaking';
    }
    if (has_term('leestip', 'ranking', $post_id)) {
        return 'Leestip';
    }

    // External callers may pass a page or CPT without region().
    $post = Timber::get_post($post_id);
    $region = $post instanceof \Streekomroep\Post ? $post->region() : null;

    return $region ? $region->name : $title;
}
