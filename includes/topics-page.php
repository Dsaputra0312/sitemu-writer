<?php
if (!defined('ABSPATH')) {
    exit;
}

function sitemu_writer_render_topics_page()
{
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Get stats for filters
    $stats = Sitemu_Topics_DB::count_by_status();

    // Pagination (Simple implementation for now, fetching all)
    // For large datasets, we should implement proper SQL pagination
    $topics = Sitemu_Topics_DB::get_all_topics($status_filter, $search_query);
    ?>
    <div class="wrap sitemu-topics-wrap">
        <h1 class="wp-heading-inline">Topics & Keywords</h1>
        <hr class="wp-header-end">

        <div class="sitemu-columns">
            <!-- Left Column: Add New -->
            <div class="sitemu-column-left">
                <div class="card sitemu-card">
                    <h2>Add New Topic</h2>
                    <form id="sitemu-add-topic-form">
                        <div class="sitemu-form-group">
                            <label for="topic">Topic Title</label>
                            <input type="text" id="topic" name="topic" class="large-text" required
                                placeholder="e.g. Benefits of Yoga">
                            <p class="description">The main subject of the article.</p>
                        </div>

                        <div class="sitemu-form-group">
                            <label for="keywords">Keywords</label>
                            <textarea id="keywords" name="keywords" rows="3" class="large-text"
                                placeholder="e.g. yoga, health, mindfulness (comma separated)"></textarea>
                            <p class="description">Keywords to include in the article.</p>
                        </div>

                        <div class="sitemu-form-group">
                            <button type="submit" class="button button-primary">Add Topic</button>
                            <span class="spinner" id="add-topic-spinner"></span>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: List -->
            <div class="sitemu-column-right">

                <!-- Filters -->
                <ul class="subsubsub">
                    <li class="all"><a href="admin.php?page=sitemu-writer-topics"
                            class="<?php echo empty($status_filter) ? 'current' : ''; ?>">All <span
                                class="count">(<?php echo esc_html($stats['total']); ?>)</span></a> |</li>
                    <li class="unused"><a href="admin.php?page=sitemu-writer-topics&status=unused"
                            class="<?php echo $status_filter === 'unused' ? 'current' : ''; ?>">Unused <span
                                class="count">(<?php echo esc_html($stats['unused']); ?>)</span></a> |</li>
                    <li class="used"><a href="admin.php?page=sitemu-writer-topics&status=used"
                            class="<?php echo $status_filter === 'used' ? 'current' : ''; ?>">Used <span
                                class="count">(<?php echo esc_html($stats['used']); ?>)</span></a></li>
                </ul>

                <!-- Search -->
                <form method="get">
                    <input type="hidden" name="page" value="sitemu-writer-topics" />
                    <?php if ($status_filter): ?><input type="hidden" name="status"
                            value="<?php echo esc_attr($status_filter); ?>" /><?php endif; ?>
                    <p class="search-box">
                        <label class="screen-reader-text" for="post-search-input">Search Topics:</label>
                        <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search_query); ?>">
                        <input type="submit" id="search-submit" class="button" value="Search Topics">
                    </p>
                </form>

                <div class="clear"></div>

                <!-- Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td id="cb" class="manage-column column-cb check-column"><input type="checkbox"></td>
                            <th scope="col" class="manage-column column-title">Topic</th>
                            <th scope="col" class="manage-column column-keywords">Keywords</th>
                            <th scope="col" class="manage-column column-status">Status</th>
                            <th scope="col" class="manage-column column-date">Date</th>
                            <th scope="col" class="manage-column column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        <?php if (empty($topics)): ?>
                            <tr>
                                <td colspan="6">No topics found. Add one to get started!</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topics as $topic): ?>
                                <tr id="topic-<?php echo esc_attr($topic->id); ?>">
                                    <th scope="row" class="check-column"><input type="checkbox" name="topic[]"
                                            value="<?php echo esc_attr($topic->id); ?>"></th>
                                    <td class="topic-title column-title has-row-actions">
                                        <strong><?php echo esc_html($topic->topic); ?></strong>
                                    </td>
                                    <td class="topic-keywords column-keywords"><?php echo esc_html($topic->keywords); ?></td>
                                    <td class="topic-status column-status">
                                        <span class="sitemu-badge sitemu-badge-<?php echo esc_attr($topic->status); ?>">
                                            <?php echo esc_html(ucfirst($topic->status)); ?>
                                        </span>
                                    </td>
                                    <td class="topic-date column-date">
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($topic->created_at))); ?>
                                    </td>
                                    <td class="topic-actions column-actions">
                                        <button type="button" class="button button-small sitemu-delete-topic"
                                            data-id="<?php echo esc_attr($topic->id); ?>" aria-label="Delete">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                        <?php if ($topic->status === 'unused'): ?>
                                            <button type="button" class="button button-small sitemu-edit-topic"
                                                data-id="<?php echo esc_attr($topic->id); ?>" aria-label="Edit">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
