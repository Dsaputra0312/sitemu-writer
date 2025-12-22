<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_ajax_sitemu_generate_article', 'sitemu_writer_handle_generation');
add_action('wp_ajax_sitemu_generate_article_manual', 'sitemu_writer_handle_generation');
add_action('wp_ajax_sitemu_test_connection', 'sitemu_writer_handle_test_connection');

function sitemu_writer_handle_test_connection()
{
    check_ajax_referer('sitemu_writer_nonce', 'nonce');

    $token = sanitize_text_field($_POST['token']);
    $model = sanitize_text_field($_POST['model']);

    if (empty($token)) {
        wp_send_json_error(array('message' => 'Token is empty.'));
    }

    $api_url = "https://openrouter.ai/api/v1/auth/key";

    $response = wp_remote_get($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
        ),
        'timeout' => 10
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => $response->get_error_message()));
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code === 200) {
        wp_send_json_success(array('message' => 'Connected successfully to Open Router!'));
    } else {
        // Try to parse JSON error from body
        $json = json_decode($body, true);
        $msg = isset($json['error']) ? (is_array($json['error']) ? json_encode($json['error']) : $json['error']) : $body;
        wp_send_json_error(array('message' => 'API Error (' . $code . '): ' . $msg));
    }
}

/**
 * Main Generation Logic (Can be called via AJAX or Cron)
 */
function sitemu_writer_handle_generation()
{
    // Check if called via AJAX
    if (defined('DOING_AJAX') && DOING_AJAX) {
        check_ajax_referer('sitemu_writer_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        $manual_topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
    } else {
        $manual_topic = '';
    }

    $result = sitemu_writer_generate_article_core($manual_topic);

    if (defined('DOING_AJAX') && DOING_AJAX) {
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}

function sitemu_writer_generate_article_core($manual_topic = '')
{
    $token = get_option('sitemu_writer_hf_token');
    if (empty($token)) {
        return array('success' => false, 'message' => 'API Token not configured');
    }

    // 2. Text Generation Settings (Moved up for language detection)
    $text_model = get_option('sitemu_writer_text_model', 'mistralai/Mistral-7B-Instruct-v0.2');
    $lang = get_option('sitemu_writer_language', 'indonesian');
    $tone = get_option('sitemu_writer_tone', 'professional');
    $min_words = get_option('sitemu_writer_min_words', 500);

    if (!empty($manual_topic)) {
        $topic_text = $manual_topic;
        $keywords = '';
        $angle = 'Manual Request';
        $full_title = $topic_text; // Simple fallback for manual
    } else {
        // Smart Selection
        $topic_data = Sitemu_Topics_DB::get_random_unused();

        if (!$topic_data) {
            // Auto Reset Check
            Sitemu_Topics_DB::reset_all_topics();
            $topic_data = Sitemu_Topics_DB::get_random_unused();
            if (!$topic_data) {
                return array('success' => false, 'message' => 'No topics available even after reset.');
            }
        }

        $topic_text = $topic_data->topic;
        $keywords = $topic_data->keywords;

        // Dynamic Title Templates based on Language
        $templates = array();

        if ($lang === 'indonesian') {
            $templates = array(
                "Panduan Lengkap %s",
                "Cara Memilih %s yang Baik",
                "Pentingnya %s di Era Digital",
                "5 Kesalahan Umum dalam %s",
                "Mengenal Lebih Dalam tentang %s",
                "Strategi %s untuk Pemula",
                "Rahasia Sukses di Dunia %s",
                "Tren %s Terbaru Tahun " . date('Y'),
                "Apa Itu %s dan Manfaatnya",
                "Tips dan Trik %s yang Efektif"
            );
        } else {
            // English Defaults
            $templates = array(
                "The Ultimate Guide to %s",
                "How to Choose the Best %s",
                "Why %s Matters in the Digital Age",
                "5 Common Mistakes in %s",
                "Deep Dive into %s",
                "%s Strategies for Beginners",
                "Secrets of Success in %s",
                "Latest %s Trends in " . date('Y'),
                "What is %s and Its Benefits",
                "Effective Tips and Tricks for %s"
            );
        }

        $used_angles = json_decode($topic_data->used_angles, true);
        $used_template_strings = array_column($used_angles ?? [], 'angle');

        $available_templates = array_diff($templates, $used_template_strings);

        if (empty($available_templates)) {
            // Fallback if all templates used
            $template = "%s: A Comprehensive Review " . rand(100, 999);
        } else {
            $template = $available_templates[array_rand($available_templates)];
        }

        $angle = $template; // Store the template pattern as the 'angle' for uniqueness tracking
        $full_title = sprintf($template, $topic_text);
    }

    // Advanced Role-Based Prompt
    $prompt = "You are an experienced content marketing expert. Write a comprehensive, updated, and high-quality article with the exact title: '{$full_title}'.\n\n";

    $prompt .= "Topic Focus: {$topic_text}. detailed, informative, and relevant for the current year (" . date('Y') . ").\n";

    if ($keywords) {
        $prompt .= "Primary Keywords to integrate naturally: {$keywords}.\n";
        $prompt .= "Secondary Keywords (include relevantly): " . $topic_text . ", trends, benefits, strategies.\n";
    }

    $prompt .= "Language: {$lang} (Full).\n";
    $prompt .= "Tone: {$tone} (professional but easy to understand for beginners to intermediate).\n";
    $prompt .= "Target Length: Minimum {$min_words} words (aim for comprehensive coverage).\n\n";

    $prompt .= "Mandatory Article Structure:\n";
    $prompt .= "- **Introduction**: Engaging hook, overview of the topic, and why it matters now.\n";
    $prompt .= "- **Main Content**: Use <h2> for main subtopics (e.g., What is it, Core Strategies, Key Benefits, Latest Trends for " . date('Y') . ", How to Implement).\n";
    $prompt .= "- **Sub-points**: Use <h3> for deeper details within sections.\n";
    $prompt .= "- **Lists**: Include <ul> or <ol> for tips, steps, or examples to make it readable.\n";
    $prompt .= "- **FAQ Section**: Add an 'Frequently Asked Questions' section at the end with 5-8 common questions using <h2>FAQ</h2>.\n";
    $prompt .= "- **Conclusion**: Summary and actionable closing thoughts.\n\n";

    // Marketing: Product Promotion (CTA)
    // Marketing: Product Promotion (CTA)
    $products_json = get_option('sitemu_writer_products_list', '[]');
    $products = json_decode($products_json, true);

    if (!empty($products) && is_array($products)) {
        // Optimize: If user has many products, only send a RANDOM subset (e.g., 5) to save tokens and keep AI focused.
        if (count($products) > 5) {
            shuffle($products);
            $products = array_slice($products, 0, 5);
        }

        $products_str = "";
        foreach ($products as $p) {
            $products_str .= "- Name: " . $p['name'] . " | URL: " . $p['url'] . " | Context/USP: " . $p['context'] . "\n";
        }

        $prompt .= "Start Marketing Integration:\n";
        $prompt .= "Here is a list of my products/services:\n{$products_str}\n";
        $prompt .= "You MUST select ONE (singular) product from the list above that is MOST RELEVANT to the article topic.\n";
        $prompt .= "Naturally integrate a recommendation for that chosen product within the content (preferably in the 'How to' or 'Solution' section).\n";
        $prompt .= "Highlight its specific USP (Selling Point) mentioned in the list.\n";
        $prompt .= "Call to Action: Encourage readers to check it out at the provided URL.\n";
        $prompt .= "IMPORTANT: Do NOT make it sound like a hard generic ad. weave it into the advice smoothly.\n\n";
    }

    // Marketing: Internal Linking
    if (get_option('sitemu_writer_enable_internal_links')) {
        // Fetch RANDOM posts instead of recent ones to revitalize old content
        $random_posts = get_posts(array(
            'numberposts' => 5,
            'post_status' => 'publish',
            'orderby' => 'rand', // Randomize
            'exclude' => array(get_the_ID())
        ));

        if (!empty($random_posts)) {
            $links_str = "";
            foreach ($random_posts as $p) {
                // get_posts returns distinct WP_Post objects, wp_get_recent_posts returned arrays
                $links_str .= "- Title: " . $p->post_title . " | URL: " . get_permalink($p->ID) . "\n";
            }
            $prompt .= "Internal Linking Requirement:\n";
            $prompt .= "Here is a list of existing articles on this site:\n{$links_str}\n";
            $prompt .= "If any of these topics are relevant to the current article, finding a natural place to link to them using an HTML <a> tag is MANDATORY.\n\n";
        }
    }

    $prompt .= "SEO Optimization Requirements:\n";
    $prompt .= "- Distribute keywords naturally in the title, intro, headers, and conclusion.\n";
    $prompt .= "- Keep paragraphs short and readable.\n";
    $prompt .= "- At the very end, provide a **Meta Title** (max 60 chars) and **Meta Description** (max 160 chars) for this article.\n\n";

    $prompt .= "Output Format:\n";
    $prompt .= "Provide ONLY the HTML content. Use <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong> tags. Do NOT use markdown (```html or ```). Do NOT include an H1 tag (the title is already the H1).";

    // Call Text API (Open Router)
    $api_url = "https://openrouter.ai/api/v1/chat/completions";
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => get_site_url(), // Recommended by OpenRouter
            'X-Title' => get_bloginfo('name') // Recommended by OpenRouter
        ),
        'body' => json_encode(array(
            'model' => $text_model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        )),
        'timeout' => 300 // Increase timeout to 5 minutes (Open Router can be slow for long content)
    ));

    if (is_wp_error($response)) {
        Sitemu_History_DB::add_log(array(
            'topic_id' => $topic_data ? $topic_data->id : null,
            'topic_text' => $topic_text,
            'status' => 'failed',
            'error_message' => $response->get_error_message()
        ));
        return array('success' => false, 'message' => $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $content = '';

    // Open Router / OpenAI format
    if (isset($body['choices'][0]['message']['content'])) {
        $content = $body['choices'][0]['message']['content'];
    } else {
        $error_msg = isset($body['error']['message']) ? $body['error']['message'] : (isset($body['error']) ? json_encode($body['error']) : 'Unknown API error');
        Sitemu_History_DB::add_log(array(
            'topic_id' => $topic_data ? $topic_data->id : null,
            'topic_text' => $topic_text,
            'status' => 'failed',
            'error_message' => $error_msg
        ));
        return array('success' => false, 'message' => "API Error: " . $error_msg);
    }

    // 3. Featured Image (Default User Selection)
    $image_id = get_option('sitemu_writer_default_image_id');
    if (!$image_id) {
        $image_id = null; // Ensure clear fallbacks if setting is empty
    }

    // 4. Create Post
    $post_status = get_option('sitemu_writer_post_status', 'draft');
    $post_id = wp_insert_post(array(
        'post_title' => $full_title,
        'post_content' => $content,
        'post_status' => $post_status,
        'post_type' => 'post',
        'post_author' => get_current_user_id() ? get_current_user_id() : 1,
    ));

    if (is_wp_error($post_id)) {
        return array('success' => false, 'message' => 'Failed to create post.');
    }

    if ($image_id) {
        set_post_thumbnail($post_id, $image_id);
    }

    // 5. Yoast Integration
    if (get_option('sitemu_writer_enable_yoast')) {
        Sitemu_Yoast_Helper::set_meta($post_id, array(
            'title' => $full_title,
            'description' => wp_trim_words(strip_tags($content), 25),
            'focus_keyword' => explode(',', $keywords)[0] ?? $topic_text
        ));
    }

    // 6. Update Status
    if ($topic_data) {
        Sitemu_Topics_DB::mark_as_used($topic_data->id, $angle);
    }

    // 7. Log History
    Sitemu_History_DB::add_log(array(
        'topic_id' => $topic_data ? $topic_data->id : 0,
        'post_id' => $post_id,
        'topic_text' => $topic_text,
        'keywords_used' => $keywords,
        'angle_used' => $angle,
        'status' => 'success',
        'word_count' => str_word_count(strip_tags($content)),
        'image_generated' => (bool) $image_id,
        'yoast_score' => Sitemu_Yoast_Helper::get_score($post_id)
    ));

    return array(
        'success' => true,
        'message' => 'Generated successfully.',
        'edit_url' => get_edit_post_link($post_id, 'raw'),
        'preview' => wp_trim_words($content, 20)
    );
}
