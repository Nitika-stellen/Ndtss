<?php
/*
*  Plugin Name: Download Plugin
*  Plugin URI: http://metagauss.com
*  Description: Download any plugin from your WordPress admin panel's Plugins page by just one click! Now, download themes, users, blog posts, pages, custom posts, comments, attachments and much more.
*  Version: 2.3.1
*  Author: Download Plugin
*  Author URI: https://profiles.wordpress.org/downloadplugin/
*  Text Domain: download-plugin
*  Requires at least: 4.8
*  Tested up to: 6.8
*  Requires PHP: 5.6
*/

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

if( !is_admin() ) return;

// plugin version
define('DPWAP_VERSION', '2.3.1');
// directory separator
if ( !defined( 'DS' ) ) define( 'DS', DIRECTORY_SEPARATOR );
// plugin file name
if ( !defined( 'DPWAP_PLUGIN_FILE' ) ) {
    define( 'DPWAP_PLUGIN_FILE', __FILE__ );
}
if ( !defined( 'DPWAP_DIR' ) ) {
    define( 'DPWAP_DIR', dirname( __FILE__ ) );	// Plugin dir
}
if ( !defined( 'DPWAP_URL' ) ) {
    define( 'DPWAP_URL', plugin_dir_url( __FILE__ ) ); // Plugin url
}
if ( !defined( 'DPWAP_PREFIX' ) ) {
    define( 'DPWAP_PREFIX', 'dpwap_' ); // Plugin Prefix
}

$dpwapUploadDir = wp_upload_dir();
if ( !defined( 'DPWAPUPLOADDIR_PATH' ) ) {
    define( 'DPWAPUPLOADDIR_PATH', $dpwapUploadDir['basedir'] );
}    
if ( !defined( 'DPWAP_PLUGINS_TEMP' ) ) {
    define( 'DPWAP_PLUGINS_TEMP', $dpwapUploadDir['basedir'].'/dpwap_plugins' ); // Plugin Prefix
}

require_once dirname( DPWAP_PLUGIN_FILE ) . '/vendor/autoload.php';

add_action( 'plugins_loaded', 'dpwap_plugin_loaded' );

//register_activation_hook( __FILE__, 'dpwap_func_activate' );

register_uninstall_hook( __FILE__, 'dpwap_func_uninstall' );

function dpwap_plugin_loaded() {
    static $instance;
	if ( is_null( $instance ) ) {
		$instance = new DPWAP\Main();
        /**
         * Download plugin loaded.
         *
         * Fires when Download plugin was fully loaded and instantiated.
         *
         */
        do_action( 'dpwap_download_plugin_loaded' );
	}
	return $instance;
}

if( !function_exists( 'dpwap_func_activate' ) ) {
    function dpwap_func_activate() {
        add_option( 'download_plugin_do_activation_redirect', true );
    }
}

if ( !function_exists( 'dpwap_func_uninstall' ) ){
    function dpwap_func_uninstall() {
        //delete_option( 'dpwap_popup_status' );
        $folder = DPWAP_PLUGINS_TEMP;
        $files = glob( "$folder/*" );
        if ( !empty( $files) ) {
            foreach( $files as $file ) {
                if ( is_file( $file) ){
                    unlink( $file );
                }
            }
        }
    }
}

// enhancement start 
// Add download link to post/page row actions
function dpwap_add_download_link($actions, $post) {
    if (current_user_can('manage_options')) {
        $download_url = wp_nonce_url(
            add_query_arg(
                [
                    'dpwap_download' => 1,
                    'post_id' => $post->ID,
                    'type' => $post->post_type,
                ],
                admin_url('edit.php')
            ),
            'dpwap_download_post_' . $post->ID
        );
        $actions['dpwap_download'] = '<a href="' . esc_url($download_url) . '">Download</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'dpwap_add_download_link', 10, 2);
add_filter('page_row_actions', 'dpwap_add_download_link', 10, 2);

// Handle the download request
function dpwap_handle_download() {
    if (isset($_GET['dpwap_download']) && current_user_can('manage_options')) {
        $post_id = intval($_GET['post_id']);
         // Verify the nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dpwap_download_post_' . $post_id)) {
            wp_die(__('Invalid nonce specified', 'dpwap'), __('Error', 'dpwap'), ['response' => 403]);
        }
        
        $post_type = sanitize_text_field($_GET['type']);
        $format = 'csv'; // Default to CSV

        // Fetch the post and its metadata
        $post = get_post($post_id);
        $title = $post->post_title;
        $post_type = $post->post_type;
        $meta_data = get_post_meta($post_id);
        $meta_data = array_combine(array_keys($meta_data), array_column($meta_data, '0'));
        
        $data = array();
        // Prepare the data
        $data[] = [
            'post' => $post,
            'meta' => $meta_data,
        ];
        
        $type = !empty($title)?$title:$post_type;
        $filename  = sanitize_key($type).'.csv';
        
        dpwap_export_bulk_csv($data,$filename);
        exit;
    }
}

add_action('admin_init', 'dpwap_handle_download');
add_action('admin_init', 'dpwap_add_bulk_filters');

function dpwap_add_bulk_filters()
{
    $post_types = get_post_types();
    if(!empty($post_types))
    {
        foreach ($post_types as $post_type) {
            add_filter('bulk_actions-edit-'.$post_type, 'dpwap_register_bulk_download');
            add_filter('bulk_actions-edit-'.$post_type, 'dpwap_register_bulk_download');
            add_filter('handle_bulk_actions-edit-'.$post_type, 'dpwap_handle_bulk_download', 10, 3);
            add_filter('handle_bulk_actions-edit-'.$post_type, 'dpwap_handle_bulk_download', 10, 3);
        }
    }
}

// Register bulk action for posts/pages
function dpwap_register_bulk_download($bulk_actions) {
    if (current_user_can('manage_options')) {
        $bulk_actions['dpwap_bulk_download'] = 'Download';
    }
    return $bulk_actions;
}

// Handle the bulk download
function dpwap_handle_bulk_download($redirect_to, $doaction, $post_ids) {
    if ($doaction === 'dpwap_bulk_download' && current_user_can('manage_options')) {
        check_admin_referer('bulk-posts');
        $data = [];
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            $post_type = $post->post_type;
            $meta_data = get_post_meta($post_id);
            $meta_data = array_combine(array_keys($meta_data), array_column($meta_data, '0'));
            $data[] = ['post' => $post, 'meta' => $meta_data];
        }
        $type = !empty($post_type)?$post_type:'post';
        $filename  = sanitize_key($type).'.csv';
        dpwap_export_bulk_csv($data,$filename);
        exit;
     }
     return $redirect_to;
}

function dpwap_export_bulk_csv($data,$file_name) {
    // Collect all unique meta keys
    //echo $file_name;die;
    $all_post_keys = [];
    $all_meta_keys = [];
    foreach ($data as $item) {
        
        $post = $item['post'];
        foreach ($post as $key => $value) {
            if (!in_array($key, $all_post_keys)) {
                $all_post_keys[] = $key;
            }
        }
        
        $meta = $item['meta'];
        foreach ($meta as $key => $value) {
            if (!in_array($key, $all_meta_keys)) {
                $all_meta_keys[] = $key;
            }
        }
    }
    $filename = !empty($file_name)?$file_name:'bulk_export.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename='.$filename);
    $output = fopen('php://output', 'w');

    // CSV Headers
    $headers = array_merge($all_post_keys, $all_meta_keys);
    fputcsv($output, $headers);

    foreach ($data as $item) {
        $post = $item['post'];
        $meta = $item['meta'];

        // Basic post data
        $row = array();
        
        // Add meta data in the order of the headers
        foreach ($all_post_keys as $key) {
            $unserialized_value = isset($post->$key)?$post->$key:'';
            if (is_array($unserialized_value) || is_object($unserialized_value)) {
                $unserialized_value = maybe_serialize($unserialized_value);
            }
            $row[] = $unserialized_value;
        }

        // Add meta data in the order of the headers
        foreach ($all_meta_keys as $key) {
            $unserialized_value = isset($meta[$key])?$meta[$key]:'';
            if (is_array($unserialized_value) || is_object($unserialized_value)) {
                $unserialized_value = maybe_serialize($unserialized_value);
            }
            $row[] = $unserialized_value;
        }

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function dpwap_handle_download_comment() {
    if (isset($_GET['dpwap_download_comment']) && current_user_can('manage_options')) {
        $comment_id = intval($_GET['comment_id']);

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dpwap_download_comment_' . $comment_id)) {
            wp_die(__('Invalid nonce specified', 'dpwap'), __('Error', 'dpwap'), ['response' => 403]);
        }

        dpwap_export_comments([$comment_id]);
        exit;
    }
    
}

add_action('admin_init', 'dpwap_handle_download_comment');

function dpwap_handle_download_user() {
    if (isset($_GET['dpwap_download_user']) && current_user_can('manage_options')) {
        $user_id = intval($_GET['user_id']);

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'dpwap_download_user_' . $user_id)) {
            wp_die(__('Invalid nonce specified', 'dpwap'), __('Error', 'dpwap'), ['response' => 403]);
        }

        dpwap_export_users([$user_id]);
        exit;
    }
}

add_action('admin_init', 'dpwap_handle_download_user');

function add_download_button_to_comment_row($actions, $comment) {
     if (current_user_can('manage_options')) {
    $download_link_csv = wp_nonce_url(
            add_query_arg(
        [
            'dpwap_download_comment' => 1,
            'comment_id' => $comment->comment_ID,
            'format' => 'csv',
        ],
        admin_url('edit-comments.php')
        ),
            'dpwap_download_comment_' . $comment->comment_ID
    );
    
    $actions['download_comment'] = '<a href="' . esc_url($download_link_csv) . '">Download</a>';
     }
    return $actions;
}
add_filter('comment_row_actions', 'add_download_button_to_comment_row', 10, 2);

function add_download_button_to_user_row($actions, $user) {
     if (current_user_can('manage_options')) {
        $download_link_csv = wp_nonce_url(
            add_query_arg([
        'dpwap_download_user' => 1,
        'user_id' => $user->ID,
        'format' => 'csv',
    ], admin_url('users.php')),
            'dpwap_download_user_' . $user->ID
        );

    $actions['download_user'] = '<a href="' . esc_url($download_link_csv) . '">Download</a>';
     }
    return $actions;
}
add_filter('user_row_actions', 'add_download_button_to_user_row', 10, 2);

// Add bulk action for exporting comments
function dpwap_register_comment_bulk_action($bulk_actions) {
    $bulk_actions['export_comments_to_csv'] = __('Download', 'dpwap');
    return $bulk_actions;
}
add_filter('bulk_actions-edit-comments', 'dpwap_register_comment_bulk_action');

// Handle the bulk action for comments
function dpwap_handle_comment_bulk_action($redirect_to, $doaction, $comment_ids) {
    if ($doaction === 'export_comments_to_csv') {
        dpwap_export_comments($comment_ids, ($doaction === 'export_comments_to_csv') ? 'csv' : 'json');
    }
    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-comments', 'dpwap_handle_comment_bulk_action', 10, 3);

// Add bulk action for exporting users
function dpwap_register_user_bulk_action($bulk_actions) {
    $bulk_actions['export_users_to_csv'] = __('Download', 'dpwap');
    return $bulk_actions;
}
add_filter('bulk_actions-users', 'dpwap_register_user_bulk_action');

// Handle the bulk action for users
function dpwap_handle_user_bulk_action($redirect_to, $doaction, $user_ids) {
    if ($doaction === 'export_users_to_csv') {
        dpwap_export_users($user_ids);
    }
    return $redirect_to;
}
add_filter('handle_bulk_actions-users', 'dpwap_handle_user_bulk_action', 10, 3);

function dpwap_export_users($user_ids) {
    $data = [];

    foreach ($user_ids as $user_id) {
        $user = get_userdata($user_id);
        $meta = get_user_meta($user_id);

        $data[] = [
            'user' => $user,
            'meta' => $meta,
        ];
    }
    dpwap_export_users_csv($data);
}






function dpwap_export_comments($comment_ids) {
    $data = [];

    foreach ($comment_ids as $comment_id) {
        $comment = get_comment($comment_id);
        $meta = get_comment_meta($comment_id);

        $data[] = [
            'comment' => $comment,
            'meta' => $meta,
        ];
    }

    dpwap_export_comments_csv($data);
}

function dpwap_export_users_csv($data)
{
    // Collect all unique meta keys
    //echo $file_name;die;
    $all_user_keys = [];
    $all_meta_keys = [];
    foreach ($data as $item) {
        
        $post = $item['user']->data;
        foreach ($post as $key => $value) {
            if (!in_array($key, $all_user_keys)) {
                $all_user_keys[] = $key;
            }
        }
        
        $meta = $item['meta'];
        foreach ($meta as $key => $value) {
            if (!in_array($key, $all_meta_keys)) {
                $all_meta_keys[] = $key;
            }
        }
    }
    
    $filename = 'users_export.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename='.$filename);
    $output = fopen('php://output', 'w');

    // CSV Headers
    $headers = array_merge($all_user_keys, $all_meta_keys);
    fputcsv($output, $headers);

    foreach ($data as $item) {
        $post = $item['user']->data;
        $meta = $item['meta'];
        // Basic post data
        $row = array();
       
        // Add meta data in the order of the headers
        foreach ($all_user_keys as $key) {
            $unserialized_value = isset($post->$key)?$post->$key:'';
            
            $row[] = $unserialized_value;
        }

        // Add meta data in the order of the headers
        foreach ($all_meta_keys as $key) {
            $unserialized_value = isset($meta[$key][0])?$meta[$key][0]:'';
           
            $row[] = $unserialized_value;
        }

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function dpwap_export_comments_csv($data) {
    
    // Collect all unique meta keys
    //echo $file_name;die;
    $all_comment_keys = [];
    $all_meta_keys = [];
    foreach ($data as $item) {
        
        $post = $item['comment'];
        foreach ($post as $key => $value) {
            if (!in_array($key, $all_comment_keys)) {
                $all_comment_keys[] = $key;
            }
        }
        
        $meta = $item['meta'];
        foreach ($meta as $key => $value) {
            if (!in_array($key, $all_meta_keys)) {
                $all_meta_keys[] = $key;
            }
        }
    }
    
    $filename = 'comments_export.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename='.$filename);
    $output = fopen('php://output', 'w');

    // CSV Headers
    $headers = array_merge($all_comment_keys, $all_meta_keys);
    fputcsv($output, $headers);

    foreach ($data as $item) {
        $post = $item['comment'];
        $meta = $item['meta'];

        // Basic post data
        $row = array();
        
        // Add meta data in the order of the headers
        foreach ($all_comment_keys as $key) {
            $unserialized_value = isset($post->$key)?$post->$key:'';
            if (is_array($unserialized_value) || is_object($unserialized_value)) {
                $unserialized_value = maybe_serialize($unserialized_value);
            }
            $row[] = $unserialized_value;
        }

        // Add meta data in the order of the headers
        foreach ($all_meta_keys as $key) {
            $unserialized_value = isset($meta[$key])?$meta[$key]:'';
            if (is_array($unserialized_value) || is_object($unserialized_value)) {
                $unserialized_value = maybe_serialize($unserialized_value);
            }
            $row[] = $unserialized_value;
        }

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}