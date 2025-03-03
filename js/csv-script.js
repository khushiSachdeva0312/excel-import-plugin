jQuery(document).ready(function() {
    // Use event delegation to handle dynamically added elements
    jQuery(document).on('click', '.closeDataTable', function() {
        alert('I am in');
        jQuery('.userDataTable').remove();
    });
    jQuery('.view-data-btn').on('click', function() {
        var user_id = jQuery(this).data('user-id'); // Get user ID from the button

        // Show a loading message
        jQuery('#user-data-container').html('<p>Loading data...</p>');

        // Send the AJAX request
        jQuery.ajax({
            url: ajax_object.ajax_url, // Use the localized AJAX URL
            type: 'POST',
            data: {
                action: 'fetch_user_data', // The AJAX action (must match the PHP handler)
                user_id: user_id          // Send the user ID to the server
            },
            success: function(response) {
                if (response.success) {
                    // Display the returned HTML table
                    jQuery('#user-data-container').html(response.data);
                } else {
                    // Display the error message
                    jQuery('#user-data-container').html('<p>' + response.data + '</p>');
                }
            },
            error: function() {
                // Show error if the AJAX request fails
                jQuery('#user-data-container').html('<p>An error occurred while fetching data.</p>');
            }
        });
    });
});
