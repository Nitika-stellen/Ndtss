<?php
// Ensure WordPress functions are available
if (!function_exists('add_action')) require_once(ABSPATH . 'wp-includes/plugin.php');
if (!function_exists('sanitize_text_field')) require_once(ABSPATH . 'wp-includes/formatting.php');
if (!function_exists('plugin_dir_path')) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (!function_exists('wp_die')) require_once(ABSPATH . 'wp-includes/functions.php');
class GFCWS_Countrywisestate_Field_Ajax
{
    function __construct()
    {
        add_action('wp_ajax_Ajax_GFCWS_Filter_Record', array($this, 'Ajax_GFCWS_Filter_Record'));
        add_action('wp_ajax_nopriv_Ajax_GFCWS_Filter_Record', array($this, 'Ajax_GFCWS_Filter_Record'));
        add_action('wp_ajax_Ajax_GFCWS_Filter', array($this, 'Ajax_GFCWS_Filter'));
        add_action('wp_ajax_nopriv_Ajax_GFCWS_Filter', array($this, 'Ajax_GFCWS_Filter'));
    }
    // AJAX handler for state dropdown based on selected country
    function Ajax_GFCWS_Filter_Record()
    {
        $country_request = sanitize_text_field($_GET['country']);
        $csvFile = file(plugin_dir_path(__DIR__) . 'assets/data/states.csv');
        $datas = [];
        foreach ($csvFile as $line) {
            $datas[] = str_getcsv($line);
        }
        $newcountry = array();
        foreach ($datas as $values) {
            $state = $values[0];
            $key = $values[3];
            if (!empty($state)) {
                $newcountry[$key][] = $state;
            }
        }
        $states_option = "<option value=''>Select State</option>";
        $found = false;
        foreach ($newcountry as $key_country => $val_country) {
            if ($key_country === $country_request) {
                foreach ($val_country as $val_states) {
                    if (!empty($val_states)) {
                        $states_option .= "<option value='" . esc_attr($val_states) . "'>" . esc_html($val_states) . "</option>";
                        $found = true;
                    }
                }
                break;
            }
        }
        // If no states found, only output the placeholder
        if (!$found) {
            $states_option = "<option value=''>Select State</option>";
        }
        echo $states_option;
        wp_die();
    }

    // AJAX handler for country dropdown and state selection
    function Ajax_GFCWS_Filter()
    {
        $country_request = sanitize_text_field($_GET['country']);
        $state_request = sanitize_text_field($_GET['state']);
        $csvFile = file(plugin_dir_path(__DIR__) . 'assets/data/states.csv');
        $datas = [];
        foreach ($csvFile as $line) {
            $datas[] = str_getcsv($line);
        }
        $newcountry = array();
        foreach ($datas as $values) {
            $state = $values[0];
            $key = $values[3];
            $newcountry[$key][] = $state;
        }
        ksort($newcountry);
        $country_option = "<option value=''>Empty (no choices selected)</option>";
        if ($country_request === 'country') {
            foreach ($newcountry as $countryname => $val_country) {
                $country_option .= "<option value='{$countryname}'>{$countryname}</option>";
            }
        } elseif (!empty($country_request) && isset($newcountry[$country_request])) {
            ksort($newcountry[$country_request]);
            foreach ($newcountry[$country_request] as $val_country) {
                $selected = ($val_country === $state_request) ? 'selected' : '';
                $country_option .= "<option value='{$val_country}' {$selected}>{$val_country}</option>";
            }
        }
        echo json_encode([$country_option]);
        wp_die();
    }
}
new GFCWS_Countrywisestate_Field_Ajax;
