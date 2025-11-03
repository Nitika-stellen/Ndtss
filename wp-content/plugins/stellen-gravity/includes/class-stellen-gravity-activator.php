<?php

/**
 * Fired during plugin activation
 *
 * @since      1.0.0
 * @package    Stellen_Gravity
 * @subpackage Stellen_Gravity/includes
 */

class Stellen_Gravity_Activator {

	public static function activate() {
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		$table_countries = $wpdb->prefix . 'stellen_countries';
		$table_states    = $wpdb->prefix . 'stellen_states';
		$table_cities    = $wpdb->prefix . 'stellen_cities';

		// Create countries table
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_countries'") !== $table_countries) {
			$sql = "CREATE TABLE $table_countries (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				name varchar(100) NOT NULL,
				country_code varchar(5) NOT NULL,
				dial_code varchar(10) NOT NULL,
				phone_length int(2) NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";
			dbDelta($sql);
		}

		// Create states table
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_states'") !== $table_states) {
			$sql = "CREATE TABLE $table_states (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				country_id mediumint(9) NOT NULL,
				name varchar(100) NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";
			dbDelta($sql);
		}

		// Create cities table
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_cities'") !== $table_cities) {
			$sql = "CREATE TABLE $table_cities (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				state_id mediumint(9) NOT NULL,
				name varchar(100) NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;";
			dbDelta($sql);
		}

		// Insert demo data if not present
		if ($wpdb->get_var("SELECT COUNT(*) FROM $table_countries") == 0) {
			$countries = [
				[
					'name' => 'Singapore',
					'country_code' => 'SG',
					'dial_code' => '+65',
					'phone_length' => 8,
					'states' => [
						'Singapore' => ['Singapore']
					]
				],
				[
					'name' => 'Malaysia',
					'country_code' => 'MY',
					'dial_code' => '+60',
					'phone_length' => 9,
					'states' => [
						'Selangor' => ['Shah Alam', 'Petaling Jaya'],
						'Kuala Lumpur' => ['Kuala Lumpur']
					]
				],
				[
					'name' => 'Cambodia',
					'country_code' => 'KH',
					'dial_code' => '+855',
					'phone_length' => 9,
					'states' => [
						'Phnom Penh' => ['Phnom Penh'],
						'Siem Reap' => ['Siem Reap']
					]
				],
				[
					'name' => 'Thailand',
					'country_code' => 'TH',
					'dial_code' => '+66',
					'phone_length' => 9,
					'states' => [
						'Bangkok' => ['Bangkok'],
						'Chiang Mai' => ['Chiang Mai']
					]
				],
				[
					'name' => 'Myanmar',
					'country_code' => 'MM',
					'dial_code' => '+95',
					'phone_length' => 9,
					'states' => [
						'Yangon' => ['Yangon'],
						'Mandalay' => ['Mandalay']
					]
				],
				[
					'name' => 'India',
					'country_code' => 'IN',
					'dial_code' => '+91',
					'phone_length' => 10,
					'states' => [
						'Maharashtra' => ['Mumbai', 'Pune'],
						'Delhi' => ['New Delhi']
					]
				],
				[
					'name' => 'Sri Lanka',
					'country_code' => 'LK',
					'dial_code' => '+94',
					'phone_length' => 9,
					'states' => [
						'Western Province' => ['Colombo'],
						'Central Province' => ['Kandy']
					]
				],
				[
					'name' => 'Vietnam',
					'country_code' => 'VN',
					'dial_code' => '+84',
					'phone_length' => 9,
					'states' => [
						'Hanoi' => ['Hanoi'],
						'Ho Chi Minh City' => ['Ho Chi Minh City']
					]
				],
				[
					'name' => 'Philippines',
					'country_code' => 'PH',
					'dial_code' => '+63',
					'phone_length' => 10,
					'states' => [
						'Metro Manila' => ['Manila', 'Quezon City'],
						'Cebu' => ['Cebu City']
					]
				]
			];

			foreach ($countries as $country) {
				$wpdb->insert($table_countries, [
					'name' => $country['name'],
					'country_code' => $country['country_code'],
					'dial_code' => $country['dial_code'],
					'phone_length' => $country['phone_length']
				]);
				$country_id = $wpdb->insert_id;

				foreach ($country['states'] as $state_name => $cities) {
					$wpdb->insert($table_states, [
						'name' => $state_name,
						'country_id' => $country_id
					]);
					$state_id = $wpdb->insert_id;

					foreach ($cities as $city_name) {
						$wpdb->insert($table_cities, [
							'name' => $city_name,
							'state_id' => $state_id
						]);
					}
				}
			}
		}
	}
}
