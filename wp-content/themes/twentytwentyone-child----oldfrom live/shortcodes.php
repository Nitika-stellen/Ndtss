<?php

function custom_login_logout_button() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $first_name = $current_user->user_firstname;
        $profile_url = home_url('/user-profile/');
        $logout_url = wp_logout_url(home_url());
        $output = '<div class="user-menu">';
        $output .= '<span class="user-initial">' . esc_html(substr($first_name, 0, 1)) . '</span>';
        $output .= '<span class="user-full-name">' . esc_html($first_name) . '</span>';
        $output .= '<div class="dropdown-content">';
        $output .= '<a href="' . esc_url($profile_url) . '">Profile</a>';
        $output .= '<a href="' . esc_url($logout_url) . '">Logout</a>';
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    } else {
        return '';
    }
}
add_shortcode('login_logout_button', 'custom_login_logout_button');

function form_submission_shortcode() {
    ob_start();
   if ( !is_user_logged_in() ) {
        echo 'You need to be logged in to submit the form.';
        wp_redirect(home_url('/sign-in/'));
        return;
    }
    $user_id = get_current_user_id();

    echo do_shortcode('[gravityform id="15" title="false" description="false" ajax="true"]'); 
    return ob_get_clean();
}
add_shortcode('Examination_form', 'form_submission_shortcode');

function Reneal_form_submission_shortcode() {
    ob_start();
    // if ( !is_user_logged_in() ) {
    //     echo 'You need to be logged in to submit the form.';
    //     wp_redirect(home_url('/sign-in/'));
    //     return;
    // }
    // $user_id = get_current_user_id();
    // if ( get_user_meta($user_id, 'renewal_form_entry', true) ) {
    //     echo 'You have already submitted the form. Thank you!';
    //     return; 
    // }
    echo do_shortcode('[gravityform id="7" title="false" description="false" ajax="true"]'); 
    return ob_get_clean();
}
add_shortcode('Renewal_form', 'Reneal_form_submission_shortcode');

function Recertification_form_submission_shortcode() {
    ob_start();
    if ( !is_user_logged_in() ) {
        echo 'You need to be logged in to submit the form.';
        wp_redirect(home_url('/sign-in/'));
        return;
    }
    $user_id = get_current_user_id();
    if ( get_user_meta($user_id, 'recertification_form_entry', true) ) {
        echo 'You have already submitted the form. Thank you!';
        return; 
    }
    echo do_shortcode('[gravityform id="11" title="false" description="false" ajax="true"]'); 
    return ob_get_clean();
}
add_shortcode('Recertification_form', 'Recertification_form_submission_shortcode');

function ndtss_certificate_search_form() {
    ob_start(); ?>
    <div id="ndtss-search-container">
        <h3>NDTSS Certificate Holders</h3>
        <form id="ndtss-search-form">
            <input type="text" id="ndtss_cert_no" name="ndtss_cert_no" placeholder="Enter certificate number">
            <input type="text" id="ndtss_name" name="ndtss_name" placeholder="Name (Given or Family)">
            <button type="submit">Search</button>
          
        </form>
        <div id="ndtss-search-result"></div>
    </div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
    $('#ndtss-search-form').on('submit', function(e) {
        e.preventDefault();
        const certNo = $('#ndtss_cert_no').val();
        const name   = $('#ndtss_name').val();

        $('#ndtss-search-result').html('<p>Searching...</p>');

        $.ajax({
            url: '<?php echo admin_url("admin-ajax.php"); ?>',
            type: 'POST',
            data: {
                action: 'ndtss_search_cert',
                cert_no: certNo,
                name: name,
               // _ajax_nonce: ndtss_ajax_obj.nonce
            },
            success: function(response) {
                if (response.success) {
                    const results = response.data;
                    let html = `<p>Found ${results.length} result(s):</p>`;

                    results.forEach(r => {
                        html += `
                            <div class="ndtss-result">
                                <p><strong>Given Name:</strong> ${r.given_name}</p>
                                <p><strong>Family Name:</strong> ${r.family_name}</p>
                                <p><strong>Certificate Number:</strong> ${r.certificate_number}</p>
                                <p><strong>Method:</strong> ${r.method}</p>
                                <p><strong>Level:</strong> ${r.level}</p>
                                <p><strong>Sector:</strong> ${r.sector}</p>
                                <p><strong>Scope:</strong> ${r.scope}</p>
                                <p><strong>Expiry Date:</strong> ${r.expiry_date}</p>
                                <hr>
                            </div>
                        `;
                    });

                    $('#ndtss-search-result').html(html);
                } else {
                    $('#ndtss-search-result').html(`<p>${response.data.message}</p>`);
                }
            },

            error: function() {
                $('#ndtss-search-result').html('<p>Something went wrong. Please try again.</p>');
            }
            });
        });
    });

    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('ndtss_search', 'ndtss_certificate_search_form');

function render_user_address_sections() {
    if (!is_user_logged_in()) return '<p>You must be logged in to view this section.</p>';

    $user_id = get_current_user_id();

    // Personal (Home) Address
    $home = [
        'address' => esc_html(get_user_meta($user_id, 'home_address', true)),
        'city'    => esc_html(get_user_meta($user_id, 'home_city', true)),
        'state'   => esc_html(get_user_meta($user_id, 'home_state', true)),
        'country'   => esc_html(get_user_meta($user_id, 'home_country', true)),
        'postal'  => esc_html(get_user_meta($user_id, 'home_postal_code', true)),
    ];

    // Work Address
    $work = [
        'address' => esc_html(get_user_meta($user_id, 'work_address', true)),
        'city'    => esc_html(get_user_meta($user_id, 'work_city', true)),
        'state'   => esc_html(get_user_meta($user_id, 'work_state', true)),
        'country'   => esc_html(get_user_meta($user_id, 'work_country', true)),
        'postal'  => esc_html(get_user_meta($user_id, 'work_postal_code', true)),
    ];

    // Correspondence Preference
    $correspondence = get_user_meta($user_id, 'correspondence_address', true);
    $correspondence_label = $correspondence === 'Please use Work  address for correspondence'
        ? 'Work Address'
        : 'Personal Address';

    ob_start();
    ?>

    <div id="correspondence-info">
        <h3>Correspondence Address Type:</h3>
        <p><strong>Currently using:</strong> <?= esc_html($correspondence_label); ?></p>
    </div>

    <div id="address-blocks">
        <hr>
        <h4>🏡 Personal Address</h4>
        <p><strong>Address:</strong> <span id="home_address"><?= $home['address'] ?></span></p>
        <p><strong>City:</strong> <span id="home_city"><?= $home['city'] ?></span></p>
        
        <p><strong>State:</strong> <span id="home_state"><?= $home['state'] ?></span></p>
        <p><strong>Postal Code:</strong> <span id="home_postal"><?= $home['postal'] ?></span></p>
        <button class="edit-btn" data-type="home">Edit Personal Address</button>

    </div>

    <div class="work_address">
        
            <h4>🏢 Work Address</h4>
            <p><strong>Address:</strong> <span id="work_address"><?= $work['address'] ?></span></p>
            <p><strong>City:</strong> <span id="work_city"><?= $work['city'] ?></span></p>
            <p><strong>State:</strong> <span id="work_state"><?= $work['state'] ?></span></p>
            
            <p><strong>Postal Code:</strong> <span id="work_postal"><?= $work['postal'] ?></span></p>
            <button class="edit-btn" data-type="work">Edit Work Address</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    jQuery(document).ready(function($) {
        $('.edit-btn').on('click', function() {
            const type = $(this).data('type'); // home or work
            const prefix = type === 'home' ? '#home_' : '#work_';

            const address = $(prefix + 'address').text().trim();
            const city    = $(prefix + 'city').text().trim();
            const state   = $(prefix + 'state').text().trim();
            const postal  = $(prefix + 'postal').text().trim();

            Swal.fire({
                title: 'Edit ' + (type === 'home' ? 'Personal' : 'Work') + ' Address',
                html:
                    '<input id="swal_address" class="swal2-input" placeholder="Address" value="' + address + '">' +
                    '<input id="swal_city" class="swal2-input" placeholder="City" value="' + city + '">' +
                    '<input id="swal_state" class="swal2-input" placeholder="State" value="' + state + '">' +
                    '<input id="swal_postal" class="swal2-input" placeholder="Postal Code" value="' + postal + '">',
                showCancelButton: true,
                confirmButtonText: 'Update',
                focusConfirm: false,
                preConfirm: () => {
                    const a = $('#swal_address').val().trim();
                    const c = $('#swal_city').val().trim();
                    const s = $('#swal_state').val().trim();
                    const p = $('#swal_postal').val().trim();

                    if (!a || !c || !s || !p) {
                        Swal.showValidationMessage('All fields are required');
                        return false;
                    }
                    if (!/^\d{5,6}$/.test(p)) {
                        Swal.showValidationMessage('Postal Code must be 5 or 6 digits');
                        return false;
                    }

                    return { address: a, city: c, state: s, postal: p, type: type };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '<?= admin_url('admin-ajax.php') ?>',
                        type: 'POST',
                        data: {
                            action: 'update_user_address_block',
                            type: result.value.type,
                            address: result.value.address,
                            city: result.value.city,
                            state: result.value.state,
                            postal: result.value.postal
                        },
                        success: function(response) {
                            if (response.success) {
                                const prefix = result.value.type === 'home' ? '#home_' : '#work_';
                                $(prefix + 'address').text(result.value.address);
                                $(prefix + 'city').text(result.value.city);
                                $(prefix + 'state').text(result.value.state);
                                $(prefix + 'postal').text(result.value.postal);
                                Swal.fire('Updated!', 'Address updated successfully.', 'success');
                            } else {
                                Swal.fire('Error', response.data || 'Something went wrong.', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'AJAX error occurred.', 'error');
                        }
                    });
                }
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('user_address_section', 'render_user_address_sections');

function load_cpd_form_shortcode() {
    $renew_method = isset($_GET['renew_method']) ? strtolower(sanitize_text_field($_GET['renew_method'])) : '';

    if ($renew_method === 'cpd') {
        $form_id = 31;
    } elseif ($renew_method === 'exam') {
        $form_id = 36;
    } else {
        return '<p style="color:red;">Invalid or missing renewal method.</p>';
    }

    return do_shortcode('[gravityform id="' . intval($form_id) . '" title="false" description="false" ajax="true"]');
}
add_shortcode('load_cpd_form', 'load_cpd_form_shortcode');



