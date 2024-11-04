jQuery(document).ready(function($) {
        // console.log("Bangladesh");
    // alert("Bangladesh");

    // Function to fetch event dates and initialize datepicker
    function highlightEventDates() {
        $.ajax({
            type: 'POST',
            url: cpf_ajax_object.ajax_url,
            data: {
                action: 'cpf_get_event_dates'
            },
            success: function(response) {
                console.log('AJAX Success:', response); // Check if the success callback is called
                if (response.success) {
                    var eventDates = response.data;
                    console.log('Event dates received:', eventDates); // Check received dates
                    initializeDatepicker(eventDates); // Initialize datepicker with received dates
                } else {
                    console.error("No event dates returned.");
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX request failed. Status:", status, "Error:", error);
            }
        });
    }

    // Initialize the datepicker with event dates and custom day names
    function initializeDatepicker(eventDates) {
        $('#cpf-small-calendar').datepicker({
            beforeShowDay: function(date) {
                var formattedDate = $.datepicker.formatDate('yy-mm-dd', date);
                var isEventDate = eventDates.indexOf(formattedDate) !== -1;
                console.log('Checking date:', formattedDate, 'Is event date:', isEventDate);
                // Return [true, "class-name"] to add a custom class
                return [true, isEventDate ? 'highlight-event' : ''];
            },
            onSelect: function(dateText) {
                $.ajax({
                    type: 'POST',
                    url: cpf_ajax_object.ajax_url,
                    data: {
                        action: 'cpf_filter_posts_events',
                        selected_date: dateText
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#cpf-posts-events').html(response.data);
                        } else {
                            $('#cpf-posts-events').html('<p>No events found for this date.</p>');
                        }
                    }
                });
            }
        });

        // Force custom 3-letter day names
        $.datepicker.setDefaults({
            dayNamesMin: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] // Custom 3-character day names
        });

        // Rebuild datepicker to reflect changes
        $('#cpf-small-calendar').datepicker('refresh');

        // Apply class directly to the correct anchor tag within the cells
        setTimeout(function() {
            $('#cpf-small-calendar td a').each(function() {
                var date = $(this).text();  // Get the text (date number) from the <a> tag
                var currentDate = new Date($('#cpf-small-calendar').datepicker('getDate'));
                currentDate.setDate(parseInt(date));  // Set the correct date on the calendar
                var formattedDate = $.datepicker.formatDate('yy-mm-dd', currentDate);

                if (eventDates.indexOf(formattedDate) !== -1) {
                    $(this).addClass('highlight-event');  // Add the highlight class to <a> tags
                }
            });
        }, 10); // Small delay to ensure Datepicker is rendered before applying classes
    }

    // Initialize the calendar and highlight event dates
    highlightEventDates();
});
