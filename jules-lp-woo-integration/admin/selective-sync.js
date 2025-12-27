jQuery(document).ready(function($) {
    'use strict';

    // Handle "Select All" checkbox
    $('#cb-select-all-1').on('click', function() {
        $('input[name="course_ids[]"]').prop('checked', $(this).prop('checked'));
    });

    // Main sync object
    var jlwi_selective_sync = {
        is_syncing: false,

        // Function to perform an action (sync or unsync)
        perform_action: function(action_type) {
            if (this.is_syncing) {
                alert('A sync process is already running. Please wait.');
                return;
            }

            var selected_courses = $('input[name="course_ids[]"]:checked').map(function() {
                return $(this).val();
            }).get();

            if (selected_courses.length === 0) {
                alert('Please select at least one course.');
                return;
            }

            this.is_syncing = true;
            $('.jlwi-bulk-actions .button').prop('disabled', true);
            var feedback = $('#jlwi-feedback-message');
            feedback.removeClass('notice-error notice-success').addClass('notice-info').html('<p>Processing... Please wait.</p>').show();

            var data = {
                action: 'jlwi_' + action_type + '_selected',
                nonce: jlwi_ajax_vars.nonce,
                course_ids: selected_courses
            };

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    feedback.removeClass('notice-info notice-error').addClass('notice-success').html('<p>' + response.data.message + '</p>');
                    // Reload the page to show the updated status
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    feedback.removeClass('notice-info notice-success').addClass('notice-error').html('<p>' + (response.data.message || 'An unknown error occurred.') + '</p>');
                    jlwi_selective_sync.is_syncing = false;
                    $('.jlwi-bulk-actions .button').prop('disabled', false);
                }
            }).fail(function() {
                feedback.removeClass('notice-info notice-success').addClass('notice-error').html('<p>An error occurred while communicating with the server.</p>');
                jlwi_selective_sync.is_syncing = false;
                $('.jlwi-bulk-actions .button').prop('disabled', false);
            });
        }
    };

    // Bind click events to buttons
    $('#jlwi-sync-selected').on('click', function(e) {
        e.preventDefault();
        jlwi_selective_sync.perform_action('sync');
    });

    $('#jlwi-unsync-selected').on('click', function(e) {
        e.preventDefault();
        jlwi_selective_sync.perform_action('unsync');
    });
});
