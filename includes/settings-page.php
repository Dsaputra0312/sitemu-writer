<?php

if (!defined('ABSPATH')) {
    exit;
}

function sitemu_writer_register_settings()
{
    // API Group
    register_setting('sitemu_writer_api_group', 'sitemu_writer_hf_token');
    register_setting('sitemu_writer_api_group', 'sitemu_writer_text_model');
    register_setting('sitemu_writer_api_group', 'sitemu_writer_image_model');

    // Article Group
    register_setting('sitemu_writer_article_group', 'sitemu_writer_language');
    register_setting('sitemu_writer_article_group', 'sitemu_writer_tone');
    register_setting('sitemu_writer_article_group', 'sitemu_writer_min_words');
    register_setting('sitemu_writer_article_group', 'sitemu_writer_max_words');
    register_setting('sitemu_writer_article_group', 'sitemu_writer_post_status');
    register_setting('sitemu_writer_article_group', 'sitemu_writer_default_image_id'); // New Setting
    register_setting('sitemu_writer_article_group', 'sitemu_writer_enable_yoast');

    // Marketing & SEO Group
    register_setting('sitemu_writer_marketing_group', 'sitemu_writer_enable_internal_links');
    register_setting('sitemu_writer_marketing_group', 'sitemu_writer_products_list'); // JSON List of products

    // Schedule Group
    register_setting('sitemu_writer_schedule_group', 'sitemu_writer_enable_auto');
    register_setting('sitemu_writer_schedule_group', 'sitemu_writer_articles_per_day');
    register_setting('sitemu_writer_schedule_group', 'sitemu_writer_schedule_times'); // Array/JSON of times
}
add_action('admin_init', 'sitemu_writer_register_settings');

// Add Admin Menu
// ... (omitted for brevity, assume content is there) ...

function sitemu_writer_render_settings_page()
{
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';
    ?>
    <div class="wrap mcp-settings-wrap">
        <h1>Sitemu Writer Settings</h1>

        <h2 class="nav-tab-wrapper">
            <a href="?page=sitemu-writer-settings&tab=api"
                class="nav-tab <?php echo $active_tab == 'api' ? 'nav-tab-active' : ''; ?>">API Configuration</a>
            <a href="?page=sitemu-writer-settings&tab=article"
                class="nav-tab <?php echo $active_tab == 'article' ? 'nav-tab-active' : ''; ?>">Article Configuration</a>
            <a href="?page=sitemu-writer-settings&tab=marketing"
                class="nav-tab <?php echo $active_tab == 'marketing' ? 'nav-tab-active' : ''; ?>">Marketing & SEO</a>
            <a href="?page=sitemu-writer-settings&tab=schedule"
                class="nav-tab <?php echo $active_tab == 'schedule' ? 'nav-tab-active' : ''; ?>">Schedule</a>
        </h2>

        <form method="post" action="options.php">
            <?php
            if ($active_tab == 'api') {
                settings_fields('sitemu_writer_api_group');
                sitemu_writer_render_api_tab();
            } elseif ($active_tab == 'article') {
                settings_fields('sitemu_writer_article_group');
                sitemu_writer_render_article_tab();
            } elseif ($active_tab == 'marketing') {
                settings_fields('sitemu_writer_marketing_group');
                sitemu_writer_render_marketing_tab();
            } elseif ($active_tab == 'schedule') {
                settings_fields('sitemu_writer_schedule_group');
                sitemu_writer_render_schedule_tab();
            }
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function sitemu_writer_render_api_tab()
{
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Open Router API Key</th>
            <td>
                <input type="password" name="sitemu_writer_hf_token"
                    value="<?php echo esc_attr(get_option('sitemu_writer_hf_token')); ?>" class="regular-text" />
                <button type="button" id="sitemu-test-connection" class="button button-secondary">Test Connection</button>
                <div id="connection-status" style="margin-top: 5px;"></div>
                <p class="description">Enter your API key from <a href="https://openrouter.ai/keys" target="_blank">Open
                        Router</a>.</p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Text Generation Model</th>
            <td>
                <input type="text" name="sitemu_writer_text_model"
                    value="<?php echo esc_attr(get_option('sitemu_writer_text_model', 'mistralai/mistral-7b-instruct:free')); ?>"
                    class="regular-text" />
                <p class="description">Recommended: <code>mistralai/mistral-7b-instruct:free</code>,
                    <code>google/gemma-7b-it:free</code>, <code>meta-llama/llama-3-8b-instruct:free</code>
                </p>
            </td>
        </tr>
        <!--
        <tr valign="top">
            <th scope="row">Image Generation Model</th>
            <td>
                <input type="text" name="sitemu_writer_image_model"
                    value="<?php echo esc_attr(get_option('sitemu_writer_image_model', 'stabilityai/stable-diffusion-xl-base-1.0')); ?>"
                    class="regular-text" />
                <p class="description">Recommended: <code>stabilityai/stable-diffusion-xl-base-1.0</code>,
                    <code>black-forest-labs/FLUX.1-schnell</code></p>
            </td>
        </tr>
        -->
    </table>
    <?php
}

function sitemu_writer_render_article_tab()
{
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Language</th>
            <td>
                <select name="sitemu_writer_language">
                    <option value="indonesian" <?php selected(get_option('sitemu_writer_language'), 'indonesian'); ?>>
                        Indonesian</option>
                    <option value="english" <?php selected(get_option('sitemu_writer_language'), 'english'); ?>>English
                    </option>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Tone</th>
            <td>
                <select name="sitemu_writer_tone">
                    <option value="professional" <?php selected(get_option('sitemu_writer_tone'), 'professional'); ?>>
                        Professional</option>
                    <option value="casual" <?php selected(get_option('sitemu_writer_tone'), 'casual'); ?>>Casual
                    </option>
                    <option value="seo" <?php selected(get_option('sitemu_writer_tone'), 'seo'); ?>>SEO Optimized
                    </option>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Word Count</th>
            <td>
                <input type="number" name="sitemu_writer_min_words"
                    value="<?php echo esc_attr(get_option('sitemu_writer_min_words', 500)); ?>" class="small-text" />
                Min
                &nbsp;&nbsp;
                <input type="number" name="sitemu_writer_max_words"
                    value="<?php echo esc_attr(get_option('sitemu_writer_max_words', 1500)); ?>" class="small-text" />
                Max
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Post Status</th>
            <td>
                <select name="sitemu_writer_post_status">
                    <option value="draft" <?php selected(get_option('sitemu_writer_post_status'), 'draft'); ?>>Draft
                    </option>
                    <option value="publish" <?php selected(get_option('sitemu_writer_post_status'), 'publish'); ?>>
                        Publish</option>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Default Featured Image</th>
            <td>
                <?php
                $default_image_id = get_option('sitemu_writer_default_image_id');
                $image_url = $default_image_id ? wp_get_attachment_url($default_image_id) : '';
                ?>
                <input type="hidden" name="sitemu_writer_default_image_id" id="sitemu_writer_default_image_id"
                    value="<?php echo esc_attr($default_image_id); ?>" />
                <div id="sitemu-default-image-preview">
                    <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>"
                            style="max-width: 150px; margin-top: 10px; border: 1px solid #ddd; padding: 5px;" />
                    <?php endif; ?>
                </div>
                <button type="button" class="button button-secondary" id="sitemu-select-image-btn">Select Image</button>
                <button type="button" class="button button-link-delete" id="sitemu-remove-image-btn"
                    style="<?php echo $image_url ? '' : 'display:none;'; ?>">Remove Image</button>
                <p class="description">Select a default image from your Media Library to be used as the featured image for
                    all
                    generated articles.</p>
                <br>
                <label>
                    <input type="checkbox" name="sitemu_writer_enable_yoast" value="1" <?php checked(get_option('sitemu_writer_enable_yoast'), '1'); ?> /> Enable Yoast SEO Integration
                </label>
            </td>
        </tr>

    </table>
    <?php
}

function sitemu_writer_render_marketing_tab()
{
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Smart Internal Linking</th>
            <td>
                <label>
                    <input type="checkbox" name="sitemu_writer_enable_internal_links" value="1" <?php checked(get_option('sitemu_writer_enable_internal_links'), '1'); ?> />
                    Allow AI to read your recent posts and link to them naturally within the content.
                </label>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">Product Promotions (CTA)</th>
            <td>
                <p class="description" style="margin-bottom:10px;">Managed list of products AI can recommend. (AI will pick
                    the most relevant one).</p>

                <textarea name="sitemu_writer_products_list" id="sitemu_writer_products_list" class="large-text"
                    style="display:none;"><?php echo esc_attr(get_option('sitemu_writer_products_list', '[]')); ?></textarea>

                <div id="sitemu-products-wrapper">
                    <!-- Products will be rendered here by JS -->
                </div>

                <div
                    style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-top: 10px; max-width: 600px;">
                    <h4>Add New Product</h4>
                    <p>
                        <input type="text" id="new-product-name" placeholder="Product Name" class="regular-text"
                            style="width: 100%; margin-bottom: 5px;">
                        <input type="url" id="new-product-url" placeholder="Product URL (https://...)" class="regular-text"
                            style="width: 100%; margin-bottom: 5px;">
                        <textarea id="new-product-context"
                            placeholder="Selling Point / Context (e.g. Best for stomach issues...)" class="large-text"
                            rows="2"></textarea>
                    </p>
                    <button type="button" class="button button-secondary" id="add-product-btn">Add Product</button>
                </div>
            </td>
        </tr>
    </table>
    <?php
}

function sitemu_writer_render_schedule_tab()
{
    $times_json = get_option('sitemu_writer_schedule_times', '["09:00"]');
    $times = json_decode($times_json, true);
    if (!is_array($times))
        $times = array('09:00');
    ?>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Auto-Generate</th>
            <td>
                <label>
                    <input type="checkbox" name="sitemu_writer_enable_auto" value="1" <?php checked(get_option('sitemu_writer_enable_auto'), '1'); ?> /> Enable Automated Generation
                </label>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Articles per Day</th>
            <td>
                <input type="number" name="sitemu_writer_articles_per_day"
                    value="<?php echo esc_attr(get_option('sitemu_writer_articles_per_day', 3)); ?>" class="small-text" />
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Schedule Times</th>
            <td>
                <div id="schedule-times-wrapper">
                    <?php foreach ($times as $time): ?>
                        <div class="schedule-time-row" style="margin-bottom: 5px;">
                            <input type="time" name="sitemu_writer_schedule_times_input[]"
                                value="<?php echo esc_attr($time); ?>" />
                            <button type="button" class="button remove-time-btn"><span class="dashicons dashicons-trash"
                                    style="margin-top: 4px;"></span></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" id="add-time-btn">Add Time Slot</button>
                <input type="hidden" name="sitemu_writer_schedule_times" id="sitemu_writer_schedule_times"
                    value="<?php echo esc_attr($times_json); ?>">
            </td>
        </tr>
    </table>
    <script>     jQuery(document).ready(function ($) {
            function updateJson() { var times = []; $('input[name="sitemu_writer_schedule_times_input[]"]').each(function () { if ($(this).val()) times.push($(this).val()); }); $('#sitemu_writer_schedule_times').val(JSON.stringify(times)); }
            $('#add-time-btn').click(function () { $('#schedule-times-wrapper').append('<div class="schedule-time-row" style="margin-bottom: 5px;"><input type="time" name="sitemu_writer_schedule_times_input[]" /> <button type="button" class="button remove-time-btn"><span class="dashicons dashicons-trash" style="margin-top: 4px;"></span></button></div>'); });
            $(document).on('click', '.remove-time-btn', function () { $(this).parent().remove(); updateJson(); });
            $(document).on('change', 'input[name="sitemu_writer_schedule_times_input[]"]', function () { updateJson(); });
            $('form').submit(function () { updateJson(); });
        });
    </script>
    <?php
}
