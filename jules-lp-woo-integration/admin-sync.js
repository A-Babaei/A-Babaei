jQuery(document).ready(function($) {
    'use strict';

    // Object to manage the sync process
    var jlwi_sync_process = {
        total_courses: 0,
        processed_courses: 0,
        courses_to_sync: [],
        batch_size: 10,
        is_syncing: false,

        // Initialize and start the sync
        start: function() {
            if (this.is_syncing) {
                return;
            }

            this.is_syncing = true;
            this.processed_courses = 0;

            $('#jlwi-sync-button').prop('disabled', true);
            $('#jlwi-sync-status').show().html('<p>Starting synchronization... Preparing course list.</p><div class="jlwi-progress-bar"><div class="jlwi-progress"></div></div>');

            var data = {
                action: 'jlwi_start_sync',
                nonce: jlwi_sync_vars.nonce
            };

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    jlwi_sync_process.courses_to_sync = response.data.courses;
                    jlwi_sync_process.total_courses = response.data.courses.length;
                    if (jlwi_sync_process.total_courses > 0) {
                        jlwi_sync_process.process_batch();
                    } else {
                        jlwi_sync_process.complete('No courses found to sync.');
                    }
                } else {
                    jlwi_sync_process.complete(response.data.message || 'Error: Could not retrieve course list.');
                }
            }).fail(function() {
                jlwi_sync_process.complete('Error: The initial request to the server failed.');
            });
        },

        // Process a single batch of courses
        process_batch: function() {
            var batch = this.courses_to_sync.splice(0, this.batch_size);

            if (batch.length === 0) {
                this.complete('Synchronization complete!');
                return;
            }

            var data = {
                action: 'jlwi_process_batch',
                nonce: jlwi_sync_vars.nonce,
                courses: batch
            };

            $.post(ajaxurl, data, function(response) {
                if (response.success) {
                    jlwi_sync_process.processed_courses += response.data.processed_count;
                    jlwi_sync_process.update_progress();
                    jlwi_sync_process.process_batch(); // Process next batch
                } else {
                    jlwi_sync_process.complete(response.data.message || 'An unknown error occurred during batch processing.');
                }
            }).fail(function() {
                 jlwi_sync_process.complete('Error: A batch processing request to the server failed.');
            });
        },

        // Update the progress bar and status text
        update_progress: function() {
            var percentage = (this.processed_courses / this.total_courses) * 100;
            $('.jlwi-progress').css('width', percentage + '%');
            $('#jlwi-sync-status p').html('Syncing... Processed ' + this.processed_courses + ' of ' + this.total_courses + ' courses.');
        },

        // Complete the sync process
        complete: function(message) {
            $('#jlwi-sync-status p').html(message);
            $('#jlwi-sync-button').prop('disabled', false);
            $('.jlwi-progress').css('width', '100%'); // Fill the bar on completion
            this.is_syncing = false;
        }
    };

    // Bind the click event to the sync button
    $('#jlwi-sync-button').on('click', function(e) {
        e.preventDefault();
        jlwi_sync_process.start();
    });
});
