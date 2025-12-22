<?php
if (!defined('ABSPATH')) {
    exit;
}

function sitemu_writer_render_history_page()
{

    // Pagination & Filters
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : null;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    $history = Sitemu_History_DB::get_all_history($status_filter, $per_page, $offset);
    $stats = Sitemu_History_DB::get_statistics();

    // Calculate total pages based on filter
    $total_items = $stats['total'];
    if ($status_filter === 'success')
        $total_items = $stats['success'];
    if ($status_filter === 'failed')
        $total_items = $stats['failed'];

    $total_pages = ceil($total_items / $per_page);

    $current_url = 'admin.php?page=sitemu-writer-history';
    ?>
    <div class="wrap sitemu-writer-wrap">
        <h1 class="wp-heading-inline">Generation History</h1>
        <hr class="wp-header-end">

        <ul class="subsubsub">
            <li class="all"><a href="<?php echo $current_url; ?>"
                    class="<?php echo !$status_filter ? 'current' : ''; ?>">All <span
                        class="count">(<?php echo $stats['total']; ?>)</span></a> |</li>
            <li class="success"><a href="<?php echo $current_url . '&status=success'; ?>"
                    class="<?php echo $status_filter == 'success' ? 'current' : ''; ?>">Success <span
                        class="count">(<?php echo $stats['success']; ?>)</span></a> |</li>
            <li class="failed"><a href="<?php echo $current_url . '&status=failed'; ?>"
                    class="<?php echo $status_filter == 'failed' ? 'current' : ''; ?>">Failed <span
                        class="count">(<?php echo $stats['failed']; ?>)</span></a></li>
        </ul>

        <div class="tablenav top">
            <div class="alignleft actions">
                <!-- Export Buttons (Visual only for now) -->
                <button type="button" class="button" disabled>Export CSV (Pro)</button>
            </div>
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo esc_html($total_items); ?> items</span>
                <span class="pagination-links">
                    <?php if ($page > 1): ?>
                        <a class="prev-page button"
                            href="<?php echo $current_url . ($status_filter ? '&status=' . $status_filter : '') . '&paged=' . ($page - 1); ?>"><span
                                aria-hidden="true">‹</span></a>
                    <?php endif; ?>
                    <span class="paging-input"><span class="current-page"><?php echo $page; ?></span> of <span
                            class="total-pages"><?php echo $total_pages; ?></span></span>
                    <?php if ($page < $total_pages): ?>
                        <a class="next-page button"
                            href="<?php echo $current_url . ($status_filter ? '&status=' . $status_filter : '') . '&paged=' . ($page + 1); ?>"><span
                                aria-hidden="true">›</span></a>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="manage-column column-date">Date</th>
                    <th class="manage-column column-topic">Topic</th>
                    <th class="manage-column column-angle">Angle</th>
                    <th class="manage-column column-status">Status</th>
                    <th class="manage-column column-meta">Meta</th>
                    <th class="manage-column column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr>
                        <td colspan="6">No history found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('M j, Y H:i', strtotime($log->generated_at))); ?></td>
                            <td><strong><?php echo esc_html($log->topic_text); ?></strong></td>
                            <td><?php echo esc_html($log->angle_used); ?></td>
                            <td>
                                <?php if ($log->status == 'success'): ?>
                                    <span class="sitemu-badge sitemu-badge-used">Success</span>
                                <?php else: ?>
                                    <span class="sitemu-badge sitemu-badge-unused"
                                        style="background:#ffebee; color:#c62828;">Failed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log->word_count): ?>
                                    <span class="description">Words: <?php echo $log->word_count; ?></span><br>
                                <?php endif; ?>
                                <?php if ($log->image_generated): ?>
                                    <span class="dashicons dashicons-format-image" title="Image Generated"></span>
                                <?php endif; ?>
                                <?php if ($log->yoast_score): ?>
                                    <span class="sitemu-badge" style="background:#f0f0f1;">SEO: <?php echo $log->yoast_score; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($log->post_id): ?>
                                    <a href="<?php echo get_edit_post_link($log->post_id); ?>" class="button button-small"
                                        target="_blank">View</a>
                                <?php endif; ?>
                                <?php if ($log->status == 'failed'): ?>
                                    <button type="button" class="button button-small" disabled>Retry</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
