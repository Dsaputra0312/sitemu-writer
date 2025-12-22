<?php
if (!defined('ABSPATH')) {
    exit;
}

function sitemu_writer_render_dashboard_page()
{
    $stats = Sitemu_History_DB::get_statistics();
    $recent_activity = Sitemu_History_DB::get_recent(5);
    $topic_stats = Sitemu_Topics_DB::count_by_status();
    $next_run = wp_next_scheduled('sitemu_writer_cron_event');
    $auto_enabled = get_option('sitemu_writer_enable_auto');
    ?>
    <div class="wrap sitemu-writer-wrap">
        <h1 class="wp-heading-inline">Sitemu Writer Dashboard</h1>
        <hr class="wp-header-end">

        <!-- Statistics Cards -->
        <div class="sitemu-dashboard-stats">
            <div class="card sitemu-stat-card">
                <h3>Total Generated</h3>
                <div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
            </div>
            <div class="card sitemu-stat-card stat-success">
                <h3>Success</h3>
                <div class="stat-value"><?php echo esc_html($stats['success']); ?></div>
            </div>
            <div class="card sitemu-stat-card stat-failed">
                <h3>Failed</h3>
                <div class="stat-value"><?php echo esc_html($stats['failed']); ?></div>
            </div>
            <div class="card sitemu-stat-card">
                <h3>Topics Remaining</h3>
                <div class="stat-value"><?php echo esc_html($topic_stats['unused']); ?> /
                    <?php echo esc_html($topic_stats['total']); ?></div>
            </div>
        </div>

        <div class="sitemu-columns">

            <!-- Left Column: Status & Actions -->
            <div class="sitemu-column-left">
                <div class="card sitemu-card">
                    <h2>System Status</h2>
                    <ul>
                        <li>
                            <strong>Auto-Generate:</strong>
                            <?php if ($auto_enabled): ?>
                                <span class="sitemu-badge sitemu-badge-used">Active</span>
                            <?php else: ?>
                                <span class="sitemu-badge sitemu-badge-unused">Disabled</span>
                            <?php endif; ?>
                        </li>
                        <li>
                            <strong>Next Scheduled Run:</strong><br>
                            <?php echo $next_run ? date_i18n('F j, Y g:i a', $next_run) : 'Not scheduled'; ?>
                        </li>
                    </ul>

                    <hr>

                    <h3>Quick Actions</h3>
                    <button type="button" id="sitemu-dashboard-generate-btn" class="button button-primary button-large"
                        style="width:100%;">
                        <span class="dashicons dashicons-controls-play"></span> Generate Now
                    </button>
                    <div id="dashboard-gen-status" style="margin-top:10px;"></div>

                    <div style="margin-top:10px;">
                        <a href="admin.php?page=sitemu-writer-history" class="button button-secondary"
                            style="width:100%;">View Full History</a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Activity -->
            <div class="sitemu-column-right">
                <div class="card sitemu-card">
                    <h2>Recent Activity</h2>
                    <?php if (empty($recent_activity)): ?>
                        <p>No activity yet.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Topic</th>
                                    <th>Status</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activity as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html(date_i18n('M j, H:i', strtotime($log->generated_at))); ?></td>
                                        <td><?php echo esc_html($log->topic_text); ?></td>
                                        <td>
                                            <?php if ($log->status == 'success'): ?>
                                                <span style="color:green;">&#10003; Success</span>
                                            <?php else: ?>
                                                <span style="color:red;">&#10007; Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log->post_id): ?>
                                                <a href="<?php echo get_edit_post_link($log->post_id); ?>" target="_blank">View Post</a>
                                            <?php else: ?>
                                                <span
                                                    class="description"><?php echo esc_html(substr($log->error_message, 0, 30)); ?>...</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
