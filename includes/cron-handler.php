<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'sitemu_writer_schedule_cron');
add_action('sitemu_writer_cron_event', 'sitemu_writer_cron_handler');

// Add custom cron intervals if needed (e.g., every 5 minutes for testing, but we rely on daily/hourly for now)
add_filter('cron_schedules', 'sitemu_writer_custom_intervals');
function sitemu_writer_custom_intervals($schedules)
{
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display' => __('Every Minute')
    );
    return $schedules;
}

/**
 * Schedule the cron event based on settings
 * This logic is tricky with multiple specific times.
 * Approach: We run a single "Checker" cron every hour that checks if we need to generate.
 * OR we schedule single events for each time slot.
 * 
 * Let's go with: Hourly Checker.
 */
function sitemu_writer_schedule_cron()
{
    if (!wp_next_scheduled('sitemu_writer_cron_event')) {
        wp_schedule_event(time(), 'hourly', 'sitemu_writer_cron_event');
    }
}

/**
 * Main Cron Handler
 * Checks if current time matches any of the scheduled times.
 */
function sitemu_writer_cron_handler()
{
    $enabled = get_option('sitemu_writer_enable_auto');
    if (!$enabled) {
        return;
    }

    $times_json = get_option('sitemu_writer_schedule_times', '["09:00"]');
    $times = json_decode($times_json, true);
    if (!is_array($times))
        return;

    // Get current time in WP Timezone
    $current_time = current_time('H:i');
    $current_hour = explode(':', $current_time)[0];

    // Check if we should run now
    // Since we run hourly, we check if any scheduled time falls within this hour
    // e.g. if scheduled 09:30, and logic runs at 09:00, we might want to be precise or just run if hours match.
    // For simplicity: If the HOUR matches.

    $should_run = false;
    foreach ($times as $time) {
        $scheduled_hour = explode(':', $time)[0];
        // If current hour matches scheduled hour
        if ($scheduled_hour === $current_hour) {
            $should_run = true;
            break;
        }
    }

    if ($should_run) {
        // Run generation
        // How many articles? 
        // Logic: The "Articles Per Day" setting is broad.
        // If user set "Articles Per Day" = 3, and "Schedule Times" = [09:00, 14:00, 20:00]
        // Then we generate 1 article per scheduled time slot? Or split?
        // Let's assume 1 article per time slot for now to keep it synced.
        // OR: Loop 'Articles Per Day' / 'Count of Times'.

        // Simpler approach: 1 Article per Trigger.
        // If user wants 3 articles, they should add 3 time slots.

        sitemu_writer_generate_article_core();
    }
}
