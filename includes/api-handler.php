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

    $or_token = isset($_POST['openrouter_token']) ? sanitize_text_field($_POST['openrouter_token']) : '';
    $hf_token = isset($_POST['hf_token']) ? sanitize_text_field($_POST['hf_token']) : ''; // This is actually the Hugging Face token

    $messages = [];
    $has_error = false;

    // Test OpenRouter
    if ($or_token) {
        $response = wp_remote_get("https://openrouter.ai/api/v1/auth/key", array(
            'headers' => array('Authorization' => 'Bearer ' . $or_token),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            $messages[] = "OpenRouter: Error (" . $response->get_error_message() . ").";
            $has_error = true;
        } elseif (wp_remote_retrieve_response_code($response) !== 200) {
            $messages[] = "OpenRouter: Failed (" . wp_remote_retrieve_response_code($response) . ").";
            $has_error = true;
        } else {
            $messages[] = "OpenRouter: Connected.";
        }
    }

    // Test Hugging Face
    if ($hf_token) {
        $response = wp_remote_get("https://huggingface.co/api/whoami", array(
            'headers' => array('Authorization' => 'Bearer ' . $hf_token),
            'timeout' => 10
        ));

        if (is_wp_error($response)) {
            $messages[] = "HF: Error (" . $response->get_error_message() . ").";
            $has_error = true;
        } elseif (wp_remote_retrieve_response_code($response) !== 200) {
            $messages[] = "HF: Failed (" . wp_remote_retrieve_response_code($response) . ").";
            $has_error = true;
        } else {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $user = isset($data['name']) ? $data['name'] : 'Unknown';
            $messages[] = "HF: Connected as $user.";
        }
    }

    if (empty($messages)) {
        wp_send_json_error(array('message' => 'No tokens provided.'));
    }

    if ($has_error) {
        wp_send_json_error(array('message' => implode(' ', $messages)));
    } else {
        wp_send_json_success(array('message' => implode(' ', $messages)));
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
    // 2. Text Generation Settings
    $text_provider = get_option('sitemu_writer_text_provider', 'openrouter');
    $model_name = get_option('sitemu_writer_text_model', 'mistralai/Mistral-7B-Instruct-v0.2');

    if ($text_provider === 'huggingface') {
        $token = get_option('sitemu_writer_huggingface_token');
        $api_url = "https://router.huggingface.co/v1/chat/completions";
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        );
    } else {
        // OpenRouter
        $token = get_option('sitemu_writer_hf_token'); // Legacy name for OpenRouter token
        $api_url = "https://openrouter.ai/api/v1/chat/completions";
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => get_site_url(),
            'X-Title' => get_bloginfo('name')
        );
    }

    if (empty($token)) {
        return array('success' => false, 'message' => 'API Token not configured for ' . $text_provider);
    }

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

    // Call Text API
    $response = wp_remote_post($api_url, array(
        'headers' => $headers,
        'body' => json_encode(array(
            'model' => $model_name,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            // For HF, parameters might need 'max_tokens' or similar if defaulting to low
        )),
        'timeout' => 300
    ));

    if (is_wp_error($response)) {
        $error_msg = "Text Gen Error (" . $text_provider . "): " . $response->get_error_message();
        Sitemu_History_DB::add_log(array(
            'topic_id' => $topic_data ? $topic_data->id : null,
            'topic_text' => $topic_text,
            'keywords_used' => isset($keywords) ? $keywords : null,
            'angle_used' => isset($angle) ? $angle : null,
            'status' => 'failed',
            'error_message' => $error_msg
        ));
        return array('success' => false, 'message' => $error_msg);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $content = '';

    // Open Router / OpenAI / HF compatible format
    if (isset($body['choices'][0]['message']['content'])) {
        $content = $body['choices'][0]['message']['content'];
    } else {
        $raw_error = isset($body['error']['message']) ? $body['error']['message'] : (isset($body['error']) ? json_encode($body['error']) : 'Unknown API response');
        $error_msg = "Text Gen API Error (" . $text_provider . "): " . $raw_error;

        Sitemu_History_DB::add_log(array(
            'topic_id' => $topic_data ? $topic_data->id : null,
            'topic_text' => $topic_text,
            'keywords_used' => isset($keywords) ? $keywords : null,
            'angle_used' => isset($angle) ? $angle : null,
            'status' => 'failed',
            'error_message' => $error_msg
        ));
        return array('success' => false, 'message' => $error_msg);
    }

    // 3. Featured Image Logic
    $image_id = null;
    $image_source = get_option('sitemu_writer_image_source', 'default');

    if ($image_source === 'default') {
        $image_id = get_option('sitemu_writer_default_image_id');
    } elseif ($image_source === 'ai') {
        $img_provider = get_option('sitemu_writer_image_provider', 'openrouter');
        $img_model = get_option('sitemu_writer_image_model', 'stabilityai/stable-diffusion-xl-base-1.0');

        $img_prompt = "Buat thumbnail artikel dengan ukuran 16:9 yang menggambarkan tema utama artikel berdasarkan judulnya yaitu " . $topic_text . ". Desain thumbnail harus menggunakan ilustrasi 2D yang sederhana dan menarik, dengan warna pastel. Pastikan thumbnail tidak lebih dari 200KB dan simpan dalam format .webp. Tambahkan watermark dengan teks 'sitemu.id' di sudut kanan bawah. Gunakan elemen visual yang relevan dengan tema artikel, misalnya gambar terkait topik yang dibahas dalam artikel. Pastikan desainnya bersih dan mudah dipahami, dengan ruang yang cukup untuk teks judul yang jelas terbaca.";
        // Truncate prompt to safe limit (increased for longer instruction)
        $img_prompt = substr($img_prompt, 0, 1000);

        if ($img_provider === 'huggingface') {
            $hf_token = get_option('sitemu_writer_huggingface_token');
            if ($hf_token) {
                $image_binary = sitemu_call_hf_inference_image($img_model, $hf_token, $img_prompt);
                if ($image_binary) {
                    $image_id = sitemu_upload_image_from_binary($image_binary, $topic_text);
                }
            }
        }
        // OpenRouter Image Gen implementation would go here (requires specific endpoint support)
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

// Helper Functions for Image Generation
function sitemu_call_hf_inference_image($model, $token, $prompt)
{
    // Updated Endpoint as per user request
    $api_url = "https://router.huggingface.co/nebius/v1/images/generations";

    // New Payload Structure
    $payload = array(
        "response_format" => "b64_json",
        "prompt" => $prompt,
        "model" => $model
    );

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($payload),
        'timeout' => 60
    ));

    if (is_wp_error($response)) {
        // Optional: log error
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        // Optional: log error body for debugging
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    // Parse standard OpenAI-like image response structure with b64_json
    if (isset($json['data'][0]['b64_json'])) {
        return base64_decode($json['data'][0]['b64_json']);
    }

    return false;
}

function sitemu_upload_image_from_binary($binary_data, $title)
{
    if (empty($binary_data))
        return null;

    $upload_dir = wp_upload_dir();
    // Sanitize title for filename
    $filename = sanitize_title($title) . '-' . time() . '.jpg';

    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }

    file_put_contents($file, $binary_data);

    $wp_filetype = wp_check_filetype($filename, null);

    $attachment = array(
        'post_mime_type' => 'image/jpeg', // Force jpeg/png based on expectation, or detect
        'post_title' => $title,
        'post_content' => '',
        'post_status' => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $file);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}
