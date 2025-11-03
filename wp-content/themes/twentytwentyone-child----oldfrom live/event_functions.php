<?php
function add_member_price_meta_box() {
    add_meta_box(
        'member_price_meta_box',              
        'Member Price',                        
        'display_member_price_meta_box',       
        'tribe_events',                        
        'side',                                
        'low'                                  
    );
}
add_action( 'add_meta_boxes', 'add_member_price_meta_box' );

function display_member_price_meta_box( $post ) {
    $member_price = get_post_meta( $post->ID, 'member_price', true );
    ?>
    <p>
        <label for="member_price"><?php esc_html_e( 'Price for Members', 'tribe-events-calendar' ); ?>:</label><br />
        <input type="text" id="member_price" name="member_price" value="<?php echo esc_attr( $member_price ); ?>" style="width:100%;" />
        <span class="description"><?php esc_html_e( 'Enter a price specifically for members. Leave blank if not applicable.', 'tribe-events-calendar' ); ?></span>
    </p>
    <?php
}

function save_event_member_price( $post_id ) {
     $post_id = get_the_ID();
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( isset( $_POST['post_type'] ) && 'tribe_events' == $_POST['post_type'] ) {
        if ( !current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
    }
    if ( isset( $_POST['member_price'] ) ) {
        update_post_meta( $post_id, 'member_price', sanitize_text_field( $_POST['member_price'] ) );
    }
    else{
        update_post_meta( $post_id, 'member_price', 0 ) ;
    }
}
add_action( 'save_post', 'save_event_member_price' );


function modify_tribe_event_cost($cost, $event_id) {
    $non_member_price = get_post_meta($event_id, '_EventCost', true);
    $member_price = get_post_meta($event_id, 'member_price', true);
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('member', $user->roles) && !empty($member_price)) {
            return '$' . esc_html($member_price);
        }
    }
    return !empty($non_member_price) ? '$' . esc_html($non_member_price) : $cost;
}
