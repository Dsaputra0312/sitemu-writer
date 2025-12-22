<?php
if (!defined('ABSPATH')) {
    exit;
}

function sitemu_writer_render_generator_page()
{
    ?>
    <div class="wrap sitemu-writer-wrap">
        <h1>Write Article with AI</h1>

        <div class="card sitemu-writer-card">
            <h2>New Article Details</h2>
            <div class="sitemu-form-group">
                <label for="sitemu-topic">Article Topic / Title</label>
                <input type="text" id="sitemu-topic" name="topic" class="large-text"
                    placeholder="e.g. The Future of Artificial Intelligence in WordPress" />
            </div>

            <div class="sitemu-form-group">
                <button type="button" id="sitemu-generate-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-edit"></span> Generate & Draft Article
                </button>
                <div id="sitemu-spinner" class="spinner"></div>
            </div>

            <div id="sitemu-status-message"></div>
        </div>

        <div id="sitemu-results" style="display:none;">
            <h2>Generated Result</h2>
            <div id="sitemu-preview-content" class="card"></div>
            <p>
                <a href="#" id="sitemu-edit-link" class="button button-secondary">Edit Draft</a>
            </p>
        </div>
    </div>
    <?php
}
