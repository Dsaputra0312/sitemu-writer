<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create custom database tables
 */
function sitemu_writer_create_tables()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $topics_table = $wpdb->prefix . 'sitemu_topics';
    $history_table = $wpdb->prefix . 'sitemu_history';

    // Topics table
    $sql_topics = "CREATE TABLE IF NOT EXISTS $topics_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        topic varchar(255) NOT NULL,
        keywords text NOT NULL,
        status enum('unused','used') DEFAULT 'unused',
        used_angles longtext,
        last_used datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY status (status),
        KEY last_used (last_used)
    ) $charset_collate;";

    // History table
    $sql_history = "CREATE TABLE IF NOT EXISTS $history_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        topic_id bigint(20) DEFAULT NULL,
        post_id bigint(20) DEFAULT NULL,
        topic_text varchar(255) NOT NULL,
        keywords_used text,
        angle_used text,
        status enum('success','failed') DEFAULT 'failed',
        error_message text,
        word_count int(11) DEFAULT NULL,
        image_generated tinyint(1) DEFAULT 0,
        yoast_score int(11) DEFAULT NULL,
        generated_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY topic_id (topic_id),
        KEY post_id (post_id),
        KEY status (status),
        KEY generated_at (generated_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_topics);
    dbDelta($sql_history);
}

/**
 * CRUD Helper Functions for Topics
 */
class Sitemu_Topics_DB
{

    public static function add_topic($topic, $keywords)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        return $wpdb->insert(
            $table,
            array(
                'topic' => sanitize_text_field($topic),
                'keywords' => sanitize_textarea_field($keywords),
                'status' => 'unused',
                'used_angles' => json_encode(array())
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    public static function get_topic($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }

    public static function get_all_topics($status = null, $search = '')
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        $where = array('1=1');

        if ($status && in_array($status, array('unused', 'used'))) {
            $where[] = $wpdb->prepare("status = %s", $status);
        }

        if (!empty($search)) {
            $where[] = $wpdb->prepare(
                "topic LIKE %s OR keywords LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        $where_clause = implode(' AND ', $where);

        return $wpdb->get_results("SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC");
    }

    public static function update_topic($id, $topic, $keywords)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        return $wpdb->update(
            $table,
            array(
                'topic' => sanitize_text_field($topic),
                'keywords' => sanitize_textarea_field($keywords)
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }

    public static function delete_topic($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        return $wpdb->delete($table, array('id' => $id), array('%d'));
    }

    public static function mark_as_used($id, $angle)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        $topic = self::get_topic($id);
        if (!$topic) {
            return false;
        }

        $used_angles = json_decode($topic->used_angles, true);
        if (!is_array($used_angles)) {
            $used_angles = array();
        }

        $used_angles[] = array(
            'angle' => $angle,
            'used_at' => current_time('mysql')
        );

        return $wpdb->update(
            $table,
            array(
                'status' => 'used',
                'used_angles' => json_encode($used_angles),
                'last_used' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }

    public static function reset_all_topics()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        return $wpdb->query("UPDATE $table SET status = 'unused'");
    }

    public static function get_random_unused()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        return $wpdb->get_row("SELECT * FROM $table WHERE status = 'unused' ORDER BY RAND() LIMIT 1");
    }

    public static function count_by_status()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_topics';

        return array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'unused' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'unused'"),
            'used' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'used'")
        );
    }
}

/**
 * CRUD Helper Functions for History
 */
class Sitemu_History_DB
{

    public static function add_log($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_history';

        return $wpdb->insert(
            $table,
            array(
                'topic_id' => isset($data['topic_id']) ? $data['topic_id'] : null,
                'post_id' => isset($data['post_id']) ? $data['post_id'] : null,
                'topic_text' => sanitize_text_field($data['topic_text']),
                'keywords_used' => sanitize_textarea_field($data['keywords_used']),
                'angle_used' => sanitize_text_field($data['angle_used']),
                'status' => $data['status'],
                'error_message' => isset($data['error_message']) ? sanitize_textarea_field($data['error_message']) : null,
                'word_count' => isset($data['word_count']) ? intval($data['word_count']) : null,
                'image_generated' => isset($data['image_generated']) ? (bool) $data['image_generated'] : false,
                'yoast_score' => isset($data['yoast_score']) ? intval($data['yoast_score']) : null
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d')
        );
    }

    public static function get_all_history($status = null, $limit = 100, $offset = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_history';

        $where = '1=1';
        if ($status && in_array($status, array('success', 'failed'))) {
            $where = $wpdb->prepare("status = %s", $status);
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY generated_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    public static function get_recent($limit = 5)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_history';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY generated_at DESC LIMIT %d",
            $limit
        ));
    }

    public static function get_statistics()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sitemu_history';

        return array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
            'success' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'success'"),
            'failed' => $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'failed'")
        );
    }
}
