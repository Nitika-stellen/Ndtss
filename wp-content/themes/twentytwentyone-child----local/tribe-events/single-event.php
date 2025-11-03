<?php
/**
 * Single Event Template
 * A single event. This displays the event title, description, meta, and
 * optionally, the Google map for the event.
 *
 * Override this template in your own theme by creating a file at [your-theme]/tribe-events/single-event.php
 *
 * @package TribeEventsCalendar
 * @version 4.6.19
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

$events_label_singular = tribe_get_event_label_singular();
$events_label_plural   = tribe_get_event_label_plural();

$event_id = Tribe__Events__Main::postIdHelper( get_the_ID() );

/**
 * Allows filtering of the event ID.
 *
 * @since 6.0.1
 *
 * @param numeric $event_id
 */
$event_id = apply_filters( 'tec_events_single_event_id', $event_id );

/**
 * Allows filtering of the single event template title classes.
 *
 * @since 5.8.0
 *
 * @param array   $title_classes List of classes to create the class string from.
 * @param numeric $event_id      The ID of the displayed event.
 */
$title_classes = apply_filters( 'tribe_events_single_event_title_classes', [ 'tribe-events-single-event-title' ], $event_id );
$title_classes = implode( ' ', tribe_get_classes( $title_classes ) );

/**
 * Allows filtering of the single event template title before HTML.
 *
 * @since 5.8.0
 *
 * @param string  $before   HTML string to display before the title text.
 * @param numeric $event_id The ID of the displayed event.
 */
$before = apply_filters( 'tribe_events_single_event_title_html_before', '<h1 class="' . $title_classes . '">', $event_id );

/**
 * Allows filtering of the single event template title after HTML.
 *
 * @since 5.8.0
 *
 * @param string  $after    HTML string to display after the title text.
 * @param numeric $event_id The ID of the displayed event.
 */
$after = apply_filters( 'tribe_events_single_event_title_html_after', '</h1>', $event_id );

/**
 * Allows filtering of the single event template title HTML.
 *
 * @since 5.8.0
 *
 * @param string  $after    HTML string to display. Return an empty string to not display the title.
 * @param numeric $event_id The ID of the displayed event.
 */
$title = apply_filters( 'tribe_events_single_event_title_html', the_title( $before, $after, false ), $event_id );
$cost  = tribe_get_formatted_cost( $event_id );

?>

<div id="tribe-events-content" class="tribe-events-single">

	<p class="tribe-events-back">
		<a href="<?php echo esc_url( tribe_get_events_link() ); ?>"> <?php printf( '&laquo; ' . esc_html_x( 'All %s', '%s Events plural label', 'the-events-calendar' ), $events_label_plural ); ?></a>
	</p>

	<!-- Notices -->
	<?php tribe_the_notices() ?>

	<?php echo $title; ?>

	<div class="tribe-events-schedule tribe-clearfix">
    <?php 
  

    // Retrieve event cost (non-member price) and member price from post meta
    $non_member_price = get_post_meta( $event_id, '_EventCost', true );
    $member_price = get_post_meta( $event_id, 'member_price', true );
    //echo $member_price;

    // Initialize the cost variable
    $cost = '';

    // Check if the user is logged in
    if ( is_user_logged_in() ) {
        $user = wp_get_current_user();


        // If the user is a member, use the member price
        if ( in_array( 'member', (array) $user->roles ) && !empty($member_price) ) {
            $cost = '$' . esc_html( $member_price );
        } else {
            // Use the non-member price if not a member or if member price is empty
            $cost = '$' . esc_html( $non_member_price );
        }
    } else {
        // If not logged in, display the non-member price
        $cost = '$' . esc_html( $non_member_price );
    }
    ?>

    <!-- Event Schedule -->
    <?php echo tribe_events_event_schedule_details( $event_id, '<h2>', '</h2>' ); ?>


    <!-- Event Cost -->
    <?php if ( ! empty( $member_price ) || ! empty( $non_member_price ) ) : ?>
        <span class="tribe-events-cost"><?php echo $cost; ?></span>
    <?php endif; ?>
</div>


	<!-- Event header -->
	<div id="tribe-events-header" <?php tribe_events_the_header_attributes() ?>>
		<!-- Navigation -->
		<nav class="tribe-events-nav-pagination" aria-label="<?php printf( esc_html__( '%s Navigation', 'the-events-calendar' ), $events_label_singular ); ?>">
			<ul class="tribe-events-sub-nav">
				<li class="tribe-events-nav-previous"><?php tribe_the_prev_event_link( '<span>&laquo;</span> %title%' ) ?></li>
				<li class="tribe-events-nav-next"><?php tribe_the_next_event_link( '%title% <span>&raquo;</span>' ) ?></li>
			</ul>
			<!-- .tribe-events-sub-nav -->
		</nav>
	</div>
	<!-- #tribe-events-header -->

	<?php while ( have_posts() ) :  the_post(); ?>
		<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<!-- Event featured image, but exclude link -->
			<?php echo tribe_event_featured_image( $event_id, 'full', false ); ?>

			<!-- Event content -->
			<?php do_action( 'tribe_events_single_event_before_the_content' ) ?>
			<div class="tribe-events-single-event-description tribe-events-content">
				<?php the_content(); ?>
			</div>
			<div class="event_btns">
			<!-- .tribe-events-single-event-description -->
			<?php do_action( 'tribe_events_single_event_after_the_content' ) ?>
<?php
$user_id = get_current_user_id();
$event_id = get_the_ID(); // Assuming this is the event ID
$current_user = wp_get_current_user();
$user_roles = $current_user->roles; 

// Get the event's end date
$event_end_date = tribe_get_end_date($event_id, false, 'Y-m-d H:i:s');
$current_date = current_time('Y-m-d H:i:s');

// Check if the user has already registered for the event
$entry_id = get_user_meta($user_id, 'member_event_form_entry', true);
$can_refill_form = true; // Default assume user can fill

$registered_event_ids = get_user_meta($user_id, 'registered_event_ids', false);

// If the user is registered for the event and the status is not 'rejected', don't show the Join button
$can_join_event = true; // Default assume user can join the event

// Check if the user has registered for the event
if ($registered_event_ids && in_array($event_id, $registered_event_ids)) {
    // Get the approval status for the specific event
    $approval_status = get_user_meta($user_id, 'event_' . $event_id . '_approval_status', true);


    // If approval status is not 'rejected', prevent the user from joining the event again
    if ($approval_status !== 'rejected') {
        $can_join_event = false;
        //var_dump($can_join_event);
    }
}

if ($entry_id) {
    // Get the Gravity Form entry
    $entry = GFAPI::get_entry($entry_id);

    if (!is_wp_error($entry)) {
        // Suppose the approval status is stored in a field (example: field id 5)
        $approval_status = rgar($entry, '5'); // Replace '5' with your actual field ID

       


    }
}

if (!$can_join_event) {
    // User has already registered and is not rejected
    echo '<p>You have already filled the joining form for this event. Please check the status on the profile page.</p>';
    echo '<a href="' . home_url('/user-profile/') . '">View your profile</a>';
} else {
    // Only show the Join button if the event hasn't passed
    if ($current_date < $event_end_date) {
        echo '<button id="join-event-btn" onclick="openJoinPopup()">Join Event</button>';
        echo '<div id="join-event-popup" class="modal">';
        echo '<div class="modal-content">';
        echo '<span class="close" onclick="closeJoinPopup()">&times;</span>';
        echo do_shortcode('[gravityform id="12" title="false" description="false" ajax="true"]');
        echo '</div></div>';
    } else {
        echo '<p>This event has already passed, so joining is no longer available.</p>';
    }
}
?>



</div>

<script type="text/javascript">
    function openJoinPopup(){
        jQuery('#join-event-popup').fadeIn(); // Show the popup form when the button is clicked
    }

    function closeJoinPopup(){
        jQuery('#join-event-popup').fadeOut(); // Hide the popup form when the close button is clicked
    }

    jQuery(document).ready(function($) {
        // Close the popup when clicking outside the form
        $(window).click(function(event) {
            if (event.target.id == 'join-event-popup') {
                $('#join-event-popup').fadeOut();
            }
        });
    });
</script>

<style>

    .modal {
        display: none; 
        position: fixed; 
        z-index: 999; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        padding: 40px;
        background-color: rgba(0, 0, 0, 0.5); 
    }


    .modal-content {
        background-color: #fff;
        margin: auto;
        padding: 20px;
        border: 1px solid #888;
        max-width: 680px;
        position: relative;
        border-radius: 8px;
    }

    .close {
        position: absolute;
        top: 10px;
        right: 20px;
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }
</style>


			<!-- Event meta -->
			<?php do_action( 'tribe_events_single_event_before_the_meta' ) ?>
			<?php //tribe_get_template_part( 'modules/meta' ); ?>
			<?php do_action( 'tribe_events_single_event_after_the_meta' ) ?>
		</div> <!-- #post-x -->
		<?php if ( get_post_type() == Tribe__Events__Main::POSTTYPE && tribe_get_option( 'showComments', false ) ) comments_template() ?>
	<?php endwhile; ?>

	<!-- Event footer -->
	<div id="tribe-events-footer">
		<!-- Navigation -->
		<nav class="tribe-events-nav-pagination" aria-label="<?php printf( esc_html__( '%s Navigation', 'the-events-calendar' ), $events_label_singular ); ?>">
		
			<ul class="tribe-events-sub-nav">
				<li class="tribe-events-nav-previous"><?php tribe_the_prev_event_link( '<span>&laquo;</span> %title%' ) ?></li>
				<li class="tribe-events-nav-next"><?php tribe_the_next_event_link( '%title% <span>&raquo;</span>' ) ?></li>
			</ul>
			<!-- .tribe-events-sub-nav -->
		</nav>
	</div>


	<!-- #tribe-events-footer -->

</div><!-- #tribe-events-content -->
