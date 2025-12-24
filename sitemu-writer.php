<?php
/**
 * Plugin Name: Sitemu Writer
 * Description: Automated AI content writer with Open Router integration.
 * Version: 2.3.1
 * Author: Sitemu
 * Text Domain: sitemu-writer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SITEMU_WRITER_VERSION', '2.3.1');
define('SITEMU_WRITER_PATH', plugin_dir_path(__FILE__));
define('SITEMU_WRITER_URL', plugin_dir_url(__FILE__));

// Include core files
require_once SITEMU_WRITER_PATH . 'includes/database.php';
require_once SITEMU_WRITER_PATH . 'includes/dashboard-page.php';
require_once SITEMU_WRITER_PATH . 'includes/topics-page.php';
require_once SITEMU_WRITER_PATH . 'includes/topics-handler.php';
require_once SITEMU_WRITER_PATH . 'includes/settings-page.php';
require_once SITEMU_WRITER_PATH . 'includes/history-page.php';
require_once SITEMU_WRITER_PATH . 'includes/api-handler.php';
require_once SITEMU_WRITER_PATH . 'includes/cron-handler.php';
require_once SITEMU_WRITER_PATH . 'includes/yoast-integration.php';

// Activation hook
register_activation_hook(__FILE__, 'sitemu_writer_activate');
function sitemu_writer_activate()
{
    sitemu_writer_create_tables();

    // Set default options
    if (!get_option('sitemu_writer_text_model')) {
        update_option('sitemu_writer_text_model', 'mistralai/mistral-7b-instruct:free');
    }
    if (!get_option('sitemu_writer_image_model')) {
        update_option('sitemu_writer_image_model', 'stabilityai/stable-diffusion-xl-base-1.0');
    }
    if (!get_option('sitemu_writer_language')) {
        update_option('sitemu_writer_language', 'indonesian');
    }
    if (!get_option('sitemu_writer_tone')) {
        update_option('sitemu_writer_tone', 'professional');
    }
    if (!get_option('sitemu_writer_min_words')) {
        update_option('sitemu_writer_min_words', 500);
    }
    if (!get_option('sitemu_writer_max_words')) {
        update_option('sitemu_writer_max_words', 1500);
    }
    if (!get_option('sitemu_writer_post_status')) {
        update_option('sitemu_writer_post_status', 'draft');
    }
    if (!get_option('sitemu_writer_generate_image')) {
        update_option('sitemu_writer_generate_image', '0');
    }
    if (!get_option('sitemu_writer_enable_yoast')) {
        update_option('sitemu_writer_enable_yoast', '1');
    }
}

// Enqueue scripts and styles
add_action('admin_enqueue_scripts', 'sitemu_writer_enqueue_assets');
function sitemu_writer_enqueue_assets($hook)
{
    if (strpos($hook, 'sitemu-writer') === false) {
        return;
    }

    wp_enqueue_media(); // Required for Media Uploader

    wp_enqueue_script('sitemu-writer-script', SITEMU_WRITER_URL . 'assets/js/script.js', array('jquery'), SITEMU_WRITER_VERSION, true);
    wp_localize_script('sitemu-writer-script', 'sitemuWriterAjax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sitemu_writer_nonce')
    ));

    wp_enqueue_style('sitemu-writer-style', SITEMU_WRITER_URL . 'assets/css/style.css', array(), SITEMU_WRITER_VERSION);
}

// Add Admin Menu
add_action('admin_menu', 'sitemu_writer_add_admin_menu');
function sitemu_writer_add_admin_menu()
{
    // Main Dashboard
    add_menu_page(
        'Sitemu Writer',
        'Sitemu Writer',
        'manage_options',
        'sitemu-writer',
        'sitemu_writer_render_dashboard_page',
        'dashicons-edit',
        20
    );

    // Dashboard submenu (rename first item)
    add_submenu_page(
        'sitemu-writer',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'sitemu-writer',
        'sitemu_writer_render_dashboard_page'
    );

    // Topics
    add_submenu_page(
        'sitemu-writer',
        'Topics & Keywords',
        'Topics',
        'manage_options',
        'sitemu-writer-topics',
        'sitemu_writer_render_topics_page'
    );

    // Settings
    add_submenu_page(
        'sitemu-writer',
        'Settings',
        'Settings',
        'manage_options',
        'sitemu-writer-settings',
        'sitemu_writer_render_settings_page'
    );

    // History
    add_submenu_page(
        'sitemu-writer',
        'Generation History',
        'History',
        'manage_options',
        'sitemu-writer-history',
        'sitemu_writer_render_history_page'
    );
}
