<?php
/*
Plugin Name: Calendar Post Filter
Description: A plugin that displays a calendar and filters posts or events based on the selected date.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue necessary scripts and styles
function cpf_enqueue_datepicker_scripts() {
    // Define the URL to the CSS file
    $plugin_css_url = plugins_url('/css/cpf-sytle.css', __FILE__);
    // Register the stylesheet
    wp_register_style('my-plugin-styles', $plugin_css_url, array(), '1.0', 'all');
    // Enqueue the stylesheet
    wp_enqueue_style('my-plugin-styles');

    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    wp_enqueue_script('cpf-ajax-script', plugin_dir_url(__FILE__) . 'js/cpf-ajax.js', array('jquery', 'jquery-ui-datepicker'), null, true);
    wp_localize_script('cpf-ajax-script', 'cpf_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'cpf_enqueue_datepicker_scripts');




// Shortcode to display calendar and posts/events
function cpf_small_calendar_shortcode() {
    ob_start();
    ?>
    <div id="cpf-small-calendar"></div>
    <div id="cpf-posts-events">
        <?php
        // Display all events on initial load
        // Events - Arrange in order of closest date. At moment, closest date is last in the list.
        $args = array(
            'post_type' => 'event',
            'posts_per_page' => -1, // Show all events
            'meta_key' => '_event_start_date',
            'orderby' => 'meta_value',
            'order' => 'ASC', // Ascending order, closest date first
            'meta_query' => array(
                array(
                    'key' => '_event_start_date',
                    'compare' => 'EXISTS',
                    'type' => 'DATE'
                )
            ),
        );

        $query = new WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // Retrieve custom fields (you can use ACF or get_post_meta)
                $event_start_date = get_post_meta(get_the_ID(), '_event_start_date', true);
                $event_end_date = get_post_meta(get_the_ID(), '_event_end_date', true);
                $location = get_field('event_location');
                $website = get_field('event_website');
                $tickets = get_field('event_tickets');
                $event_permalink = get_permalink();

                // Countdown calculation
                $event_start_timestamp = strtotime($event_start_date);
                $now = time();
                $diff_in_seconds = $event_start_timestamp - $now;
                $days_remaining = floor($diff_in_seconds / (60 * 60 * 24));

                $countdown_output = $days_remaining > 0 ? "<div class='event-countdown'><strong>$days_remaining days</strong></div>" : "<div class='event-countdown'><strong>The event has started!</strong></div>";
                ?>
                <div class="cpf-post-event-item">
                    <div class="left_box">
                        <?php if (has_post_thumbnail()) : ?>
                            <img src="<?php echo get_the_post_thumbnail_url(); ?>" alt="<?php the_title(); ?>">
                        <?php else: ?>
                            <img src="<?php echo plugin_dir_url(__FILE__) . 'images/default-image.jpg'; ?>" alt="Default Image">
                        <?php endif; ?>
                    </div>
                    <div class="right_box">
                        <h3><a href="<?php echo esc_url($event_permalink); ?>"><?php the_title(); ?></a></h3> <!-- Title as permalink -->
                        <p>Start Date: <?php echo esc_html($event_start_date); ?></p>
                        <p>End Date: <?php echo esc_html($event_end_date); ?></p>
                        <p>Location: <?php echo !empty($location) ? esc_html($location) : 'N/A'; ?></p>
                        <p>Website: <?php echo !empty($website) ? '<a href="' . esc_url($website) . '">' . esc_url($website) . '</a>' : 'N/A'; ?></p>
                        <p><?php the_excerpt(); ?></p>
                        <p>Tickets: <?php echo !empty($tickets) ? esc_html($tickets) : 'N/A'; ?></p>
                        <?php echo $countdown_output; ?>
                    </div>
                </div>
                <?php
            }
            wp_reset_postdata();
        } else {
           $nofoundeventimg = plugins_url('/css/cpf-sytle.css', __FILE__);
            echo '<div class="no_event_found"><div>
                    <div class="nofound_img">
                        <div class="left">
                            <img src="'.$nofoundeventimg.'" alt="photo">
                        </div>
                        <div class="right">
                            <p>No events found.</p>
                        </div>
                    </div>
                 </div></div>';
        }
        ?>
    </div>
    <script>
    jQuery(document).ready(function($) {
    $('#cpf-small-calendar').datepicker({
        onSelect: function(dateText) {
            // First AJAX request to get event dates
            $.ajax({
                type: 'POST',
                url: cpf_ajax_object.ajax_url,
                data: {
                    action: 'cpf_get_event_dates',
                    selected_date: dateText
                },
                success: function(response) {
                    var eventDates = response.data;
                    initializeDatepicker(eventDates);
                }
            });

            // Second AJAX request to filter posts/events
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

    // Initialize the datepicker with event dates and custom day names
    function initializeDatepicker(eventDates) {
        $('#cpf-small-calendar').datepicker({
            beforeShowDay: function(date) {
                var formattedDate = $.datepicker.formatDate('yy-mm-dd', date);
                var isEventDate = eventDates.indexOf(formattedDate) !== -1;
                console.log('Checking date:', formattedDate, 'Is event date:', isEventDate);
                return [true, isEventDate ? 'highlight-event' : ''];
            },
            onSelect: function(dateText) {
                // AJAX request for filtering posts/events
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

        // Rebuild datepicker to reflect changes
        $('#cpf-small-calendar').datepicker('refresh');

        // Apply class directly to the correct anchor tag within the cells
        setTimeout(function() {
            $('#cpf-small-calendar td a').each(function() {
                var date = $(this).text();
                var currentDate = new Date($('#cpf-small-calendar').datepicker('getDate'));
                currentDate.setDate(parseInt(date));
                var formattedDate = $.datepicker.formatDate('yy-mm-dd', currentDate);

                if (eventDates.indexOf(formattedDate) !== -1) {
                    $(this).addClass('highlight-event');  // Add the highlight class to <a> tags
                }
            });
        }, 10); // Small delay to ensure Datepicker is rendered before applying classes
    }
});

    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('cpf_small_calendar', 'cpf_small_calendar_shortcode');


// End Short Code 
// Event date start
function cpf_get_event_dates() {
    $args = array(
        'post_type' => 'event',
        'posts_per_page' => -1,  // Fetch all events
        'meta_query' => array(
            array(
                'key' => '_event_start_date',
                'compare' => 'EXISTS',
                'type' => 'DATE'
            )
        ),
    );

    $query = new WP_Query($args);
    $event_dates = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            // Retrieve event start date and ensure it's in Y-m-d format
            $event_start_date = get_post_meta(get_the_ID(), '_event_start_date', true);
            if ($event_start_date) {
                $event_dates[] = gmdate('Y-m-d', strtotime($event_start_date));  // Convert to Y-m-d
            }
        }
        wp_reset_postdata();
    }

    wp_send_json_success($event_dates);  // Return event dates in correct format
}

add_action('wp_ajax_cpf_get_event_dates', 'cpf_get_event_dates');
add_action('wp_ajax_nopriv_cpf_get_event_dates', 'cpf_get_event_dates');


// Handle AJAX request to filter posts/events
function cpf_filter_posts_events() {
    if (!isset($_POST['selected_date'])) {
        wp_send_json_error('Invalid date');
    }

    $selected_date = isset($_POST['selected_date']) ? wp_unslash($_POST['selected_date']) : '';
    $selected_date = sanitize_text_field($selected_date);

    $formatted_date = gmdate('Y-m-d', strtotime($selected_date)); // Format the date as needed

    $args = array(
        'post_type' => 'event',
        'meta_query' => array(
            array(
                'key' => '_event_start_date',
                'value' => $formatted_date,
                'compare' => '<=',
                'type' => 'DATE'
            ),
            array(
                'key' => '_event_end_date',
                'value' => $formatted_date,
                'compare' => '>=',
                'type' => 'DATE'
            ),
        ),
    );

    $query = new WP_Query($args);
    if ($query->have_posts()) {
        ob_start();
        while ($query->have_posts()) {
            $query->the_post();

            // Retrieve custom fields
            $event_start_date = get_post_meta(get_the_ID(), '_event_start_date', true);
            $event_end_date = get_post_meta(get_the_ID(), '_event_end_date', true);

            // $location = get_post_meta(get_the_ID(), 'event_location', true);
            // $tickets = get_post_meta(get_the_ID(), 'event_tickets', true);
            // $website = get_post_meta(get_the_ID(), 'event_website', true);

            $location = get_field('event_location');
            $website = get_field('event_website');
            $tickets = get_field('event_tickets');

            // echo '<pre>';
            // $post_meta = get_post_meta(get_the_ID());
            // print_r($post_meta);
            // echo '</pre>';

            // Countdown calculation
            $event_start_timestamp = strtotime($event_start_date);
            $now = time();
            $diff_in_seconds = $event_start_timestamp - $now;
            $days_remaining = floor($diff_in_seconds / (60 * 60 * 24));

            if ($days_remaining > 0) {
                $countdown_output = "<div class='event-countdown'><strong>$days_remaining days</strong></div>";
            } else {
                $countdown_output = "<div class='event-countdown'><strong>The event has started!</strong></div>";
            }

            // Output with default values if empty
            ?>
            <div class="cpf-post-event-item">
                <div class="left_box">
                    <?php if (has_post_thumbnail()) : ?>
                        <img src="<?php echo get_the_post_thumbnail_url(); ?>" alt="<?php the_title(); ?>">
                    <?php else: ?>
                        <img src="<?php echo plugin_dir_url(__FILE__) . 'images/default-image.jpg'; ?>" alt="Default Image">
                    <?php endif; ?>
                </div>
                <div class="right_box">
                    <h3>Name: <?php the_title(); ?></h3>
                    <p>Start Date: <?php echo esc_html($event_start_date); ?></p>
                    <p>End Date: <?php echo esc_html($event_end_date); ?></p>
                    <p>Location: <?php echo !empty($location) ? esc_html($location) : 'N/A'; ?></p>
                    <p>Website: <?php echo !empty($website) ? '<a href="' . esc_url($website) . '">' . esc_url($website) . '</a>' : 'N/A'; ?></p>
                    <p><?php the_excerpt(); ?></p>
                    <p>Tickets: <?php echo !empty($tickets) ? esc_html($tickets) : 'N/A'; ?></p>
                    <?php echo $countdown_output; ?>
                </div>
            </div>
            <?php
        }
        wp_reset_postdata();
        $response = ob_get_clean();
        wp_send_json_success($response);
    } else {
        wp_send_json_error('No posts or events found.');
    }
}

add_action('wp_ajax_cpf_filter_posts_events', 'cpf_filter_posts_events');
add_action('wp_ajax_nopriv_cpf_filter_posts_events', 'cpf_filter_posts_events');