<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_sitemu_add_topic', 'sitemu_writer_ajax_add_topic');
add_action('wp_ajax_sitemu_delete_topic', 'sitemu_writer_ajax_delete_topic');

function sitemu_writer_ajax_add_topic()
{
    check_ajax_referer('sitemu_writer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'));
    }

    $topic = sanitize_text_field($_POST['topic']);
    $keywords = sanitize_textarea_field($_POST['keywords']);

    if (empty($topic)) {
        wp_send_json_error(array('message' => 'Topic is required.'));
    }

    $result = Sitemu_Topics_DB::add_topic($topic, $keywords);

    if ($result) {
        wp_send_json_success(array('message' => 'Topic added successfully!'));
    } else {
        wp_send_json_error(array('message' => 'Failed to add topic.'));
    }
}

function sitemu_writer_ajax_delete_topic()
{
    check_ajax_referer('sitemu_writer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied.'));
    }

    $id = intval($_POST['id']);

    $result = Sitemu_Topics_DB::delete_topic($id);

    if ($result) {
        wp_send_json_success(array('message' => 'Topic deleted.'));
    } else {
        wp_send_json_error(array('message' => 'Failed to delete topic.'));
    }
}
