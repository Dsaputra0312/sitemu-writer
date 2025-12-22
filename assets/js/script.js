jQuery(document).ready(function ($) {
    // Media Uploader Logic
    var mediaUploader;

    $('#sitemu-select-image-btn').on('click', function (e) {
        e.preventDefault();
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Select Default Featured Image',
            button: {
                text: 'Use this image'
            },
            multiple: false
        });

        mediaUploader.on('select', function () {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#sitemu_writer_default_image_id').val(attachment.id);
            $('#sitemu-default-image-preview').html('<img src="' + attachment.url + '" style="max-width: 150px; margin-top: 10px; border: 1px solid #ddd; padding: 5px;">');
            $('#sitemu-remove-image-btn').show();
        });

        mediaUploader.open();
    });

    $('#sitemu-remove-image-btn').on('click', function (e) {
        e.preventDefault();
        $('#sitemu_writer_default_image_id').val('');
        $('#sitemu-default-image-preview').html('');
        $(this).hide();
    });

    // Test Connection Logic
    $('#sitemu-test-connection').on('click', function (e) {
        e.preventDefault();
        var token = $('input[name="sitemu_writer_hf_token"]').val();
        var model = $('input[name="sitemu_writer_text_model"]').val();
        var statusDiv = $('#connection-status');

        if (!token) {
            statusDiv.html('<span style="color:red;">Please enter a token first.</span>');
            return;
        }

        statusDiv.html('<span style="color:blue;">Testing connection...</span>');

        $.ajax({
            url: sitemuWriterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'sitemu_test_connection',
                nonce: sitemuWriterAjax.nonce,
                token: token,
                model: model
            },
            success: function (response) {
                if (response.success) {
                    statusDiv.html('<span style="color:green;">' + response.data.message + '</span>');
                } else {
                    statusDiv.html('<span style="color:red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function () {
                statusDiv.html('<span style="color:red;">Ajax Error. Check console.</span>');
            }
        });
    });

    // Schedule Times Logic
    function updateJson() {
        var times = [];
        $('input[name="sitemu_writer_schedule_times_input[]"]').each(function () {
            if ($(this).val()) times.push($(this).val());
        });
        $('#sitemu_writer_schedule_times').val(JSON.stringify(times));
    }

    $('#add-time-btn').click(function () {
        $('#schedule-times-wrapper').append('<div class="schedule-time-row" style="margin-bottom: 5px;"><input type="time" name="sitemu_writer_schedule_times_input[]" /> <button type="button" class="button remove-time-btn"><span class="dashicons dashicons-trash" style="margin-top: 4px;"></span></button></div>');
    });

    $(document).on('click', '.remove-time-btn', function () {
        $(this).parent().remove();
        updateJson();
    });

    $(document).on('change', 'input[name="sitemu_writer_schedule_times_input[]"]', function () {
        updateJson();
    });

    // Topic Management Logic
    $('#sitemu-add-topic-form').on('submit', function (e) {
        e.preventDefault();
        var form = $(this);
        var spinner = $('#add-topic-spinner');
        var topic = $('#topic').val();
        var keywords = $('#keywords').val();

        spinner.addClass('is-active');

        $.ajax({
            url: sitemuWriterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'sitemu_add_topic',
                nonce: sitemuWriterAjax.nonce,
                topic: topic,
                keywords: keywords
            },
            success: function (response) {
                spinner.removeClass('is-active');
                if (response.success) {
                    alert(response.data.message);
                    location.reload(); // Reload to show new topic
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                spinner.removeClass('is-active');
                alert('Ajax Error');
            }
        });
    });

    $(document).on('click', '.sitemu-delete-topic', function (e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this topic?')) return;

        var btn = $(this);
        var id = btn.data('id');

        $.ajax({
            url: sitemuWriterAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'sitemu_delete_topic',
                nonce: sitemuWriterAjax.nonce,
                id: id
            },
            success: function (response) {
                if (response.success) {
                    $('#topic-' + id).remove();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('Ajax Error');
            }
        });
    });
});

// Manual Generation Logic (Dashboard & Generator Page)
jQuery(document).ready(function ($) {
    function handleGeneration(btnId, outputId, topicInputId) {
        $(btnId).on('click', function (e) {
            e.preventDefault();
            var btn = $(this);
            var output = $(outputId);
            var topic = topicInputId ? $(topicInputId).val() : '';

            if (topicInputId && !topic) {
                alert('Please enter a topic.');
                return;
            }

            btn.prop('disabled', true);
            output.html('<span style="color:blue;">Generating content... this may take a minute.</span>');

            $.ajax({
                url: sitemuWriterAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sitemu_generate_article_manual',
                    nonce: sitemuWriterAjax.nonce,
                    topic: topic
                },
                success: function (response) {
                    btn.prop('disabled', false);
                    if (response.success) {
                        var msg = '<span style="color:green;">' + response.data.message + '</span>';
                        if (response.data.post_id) {
                            msg += ' <a href="post.php?post=' + response.data.post_id + '&action=edit" target="_blank">Edit Post</a>';
                        }
                        output.html(msg);
                    } else {
                        output.html('<span style="color:red;">Error: ' + response.data.message + '</span>');
                    }
                },
                error: function () {
                    btn.prop('disabled', false);
                    output.html('<span style="color:red;">Ajax Error. Check console/logs.</span>');
                }
            });
        });
    }

    // Initialize for Dashboard
    handleGeneration('#sitemu-dashboard-generate-btn', '#dashboard-gen-status', null);

    // Initialize for Manual Generator Page
    handleGeneration('#sitemu-generate-btn', '#sitemu-status-message', '#sitemu-topic');

    // Product List Management Logic
    var productsTextarea = $('#sitemu_writer_products_list');
    if (productsTextarea.length > 0) {
        var products = [];
        try {
            products = JSON.parse(productsTextarea.val() || '[]');
        } catch (e) {
            products = [];
        }

        function renderProducts() {
            var html = '';
            if (products.length === 0) {
                html = '<p>No products added yet.</p>';
            } else {
                $.each(products, function (index, p) {
                    html += '<div class="sitemu-product-item" style="background:#fff; border:1px solid #ddd; padding:10px; margin-bottom:10px;">';
                    html += '<strong>' + p.name + '</strong> <a href="' + p.url + '" target="_blank" style="font-size:12px;">' + p.url + '</a><br>';
                    html += '<p style="margin:5px 0; font-size:13px; color:#555;">' + p.context + '</p>';
                    html += '<button type="button" class="button remove-product-btn" data-index="' + index + '" style="color:#b32d2e; border-color:#b32d2e;">Remove</button>';
                    html += '</div>';
                });
            }
            $('#sitemu-products-wrapper').html(html);
            productsTextarea.val(JSON.stringify(products));
        }

        renderProducts();

        $('#add-product-btn').on('click', function () {
            var name = $('#new-product-name').val();
            var url = $('#new-product-url').val();
            var context = $('#new-product-context').val();

            if (!name || !url) {
                alert('Please fill product name and URL.');
                return;
            }

            products.push({
                name: name,
                url: url,
                context: context
            });

            // Clear inputs
            $('#new-product-name').val('');
            $('#new-product-url').val('');
            $('#new-product-context').val('');

            renderProducts();
        });

        $(document).on('click', '.remove-product-btn', function () {
            var index = $(this).data('index');
            products.splice(index, 1);
            renderProducts();
        });
    }
});
