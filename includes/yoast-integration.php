<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sitemu_Yoast_Helper
{

    public static function is_yoast_active()
    {
        return defined('WPSEO_VERSION');
    }

    public static function set_meta($post_id, $data)
    {
        if (!self::is_yoast_active()) {
            return false;
        }

        if (isset($data['title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', $data['title']);
        }

        if (isset($data['description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $data['description']);
        }

        if (isset($data['focus_keyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $data['focus_keyword']);
        }

        return true;
    }

    public static function get_score($post_id)
    {
        if (!self::is_yoast_active()) {
            return 0;
        }
        return get_post_meta($post_id, '_yoast_wpseo_linkdex', true);
    }
}
