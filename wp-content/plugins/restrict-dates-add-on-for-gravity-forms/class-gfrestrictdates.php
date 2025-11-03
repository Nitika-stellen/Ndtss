<?php

GFForms::include_addon_framework();

class GFRestrictDatesAddOn extends GFAddOn {

	protected $_version = GF_RESTRICT_DATES_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug = 'gf-restrict-dates';
	protected $_path = 'gravityforms-restrict-dates/restrict-dates.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Restrict Dates Add-On';
	protected $_short_title = 'GF Restrict Dates Add-On';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFRestrictDatesAddOn
	 */
	public static function get_instance() {
		if (self::$_instance == null) {
			self::$_instance = new GFRestrictDatesAddOn();
		}

		return self::$_instance;
	}

	/**
	 * Handles hooks and loading of language files.
	 */
	public function init() {
		parent::init();

		add_filter('gform_tooltips', array($this, 'gfrda_add_tooltips'));
		add_action('gform_editor_js', array($this, 'gfrda_editor_script'));
		add_action('gform_enqueue_scripts', array($this, 'gfrda_frontend_enqueue_scripts'), 10, 2);
		if (GFIC_GF_MIN_2_5) {
			add_filter('gform_field_settings_tabs', array($this, 'gcafe_fields_settings_tab'), 10, 2);
			add_action('gform_field_settings_tab_content_restrict_date_tab', array($this, 'gcafe_fields_settings_tab_content'), 10, 2);
		} else {
			add_action('gform_field_advanced_settings', array($this, 'gfrda_advanced_settings'), 10, 2);
		}
	}


	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'gfrd_admin_script',
				'src'     => $this->get_base_url() . '/assets/js/gfrd_admin_script.js',
				'version' => $this->_version,
				'deps'    => array('jquery'),
				'enqueue'  => array(
					array('admin_page' => array('form_editor', 'plugin_settings', 'form_settings')),
				)
			)
		);

		return array_merge(parent::scripts(), $scripts);
	}

	public function gcafe_fields_settings_tab($tabs, $form) {
		$tabs[] = array(
			// Define the unique ID for your tab.
			'id'             => 'restrict_date_tab',
			// Define the title to be displayed on the toggle button your tab.
			'title'          => 'Restrict Date',
			// Define an array of classes to be added to the toggle button for your tab.
			'toggle_classes' => array('gcafe_toggle_1', 'gcafe_toggle_2'),
			// Define an array of classes to be added to the body of your tab.
			'body_classes'   => array('gcafe_toggle_class'),
		);

		return $tabs;
	}

	public function gcafe_fields_settings_tab_content($form) {
?>

		<li class="restrict_date_setting field_setting">
			<?php if (!GFIC_GF_MIN_2_5): ?>
				<h3>Restrict Dates</h3>
			<?php endif; ?>
			<ul>
				<li>
					<input type="checkbox" id="gfrda_enable_restrict_value" onclick="SetFieldProperty('restrictDateGField', this.checked);" />
					<label for="gfrda_enable_restrict_value" class="inline">
						<?php esc_html_e("Enable Restrict date options", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("enable_rd"); ?>
					</label>
				</li>
			</ul>
			<ul id="rda_enable">
				<li class="rda_minimum_date field_setting">
					<label for="field_admin_label" class="section_label">
						<?php esc_html_e("Minimum Date", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("rda_min_date"); ?>
					</label>
					<select name="mini_date" id="mini_date_value" onChange="SetFieldProperty('rdaMinimumDateGField', this.value);">
						<option value="">Select One</option>
						<option value="today">Current Date</option>
						<option value="custom_date">Set Specific Date</option>
					</select>
				</li>
				<li class="rda_minimum_date_picker field_setting">
					<label for="field_minimum_date_picker" class="section_label">
						<?php esc_html_e("Choose Date", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("rda_min_date"); ?>
					</label>
					<input type="date" id="field_minimum_date_picker" onChange="SetFieldProperty('rdaMinDatePickGField', this.value);">
				</li>
				<li class="rda_maximum_date field_setting">
					<label for="field_admin_label" class="section_label">
						<?php esc_html_e("Maximum Date", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("rda_min_date"); ?>
					</label>
					<select name="maxi_date" id="max_date_value" onChange="SetFieldProperty('rdaMaximumDateGField', this.value);">
						<option value="">Select One</option>
						<option value="today">Current Date</option>
						<option value="custom_date">Set Specific Date</option>
					</select>
				</li>
				<li class="rda_maximum_date_picker field_setting">
					<label for="field_maximum_date_picker" class="section_label">
						<?php esc_html_e("Choose Date", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("rda_max_date"); ?>
					</label>
					<input type="date" id="field_maximum_date_picker" onChange="SetFieldProperty('rdaMaxDatePickGField', this.value);">
				</li>
				<li class="rda_weekly_off field_setting">
					<label for="field_admin_label" class="section_label">
						<?php esc_html_e("Disable Week/Off Day", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("rda_weekly_date"); ?>
					</label>
					<select name="weekly_date" id="weekly_date_value" onChange="SetFieldProperty('rdaWeeklyDateGField', this.value);">
						<option value="">Choose Day</option>
						<option value="0">Sunday</option>
						<option value="1">Monday</option>
						<option value="2">Tuesday</option>
						<option value="3">Wednesday</option>
						<option value="4">Thursday</option>
						<option value="5">Friday</option>
						<option value="6">Saturday</option>
					</select>
				</li>
				<li class="rda_start_day field_setting">
					<label for="field_admin_label" class="section_label">
						<?php esc_html_e("Week Start Day", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("rda_week_start_day"); ?>
					</label>
					<select name="week_start_day" id="week_start_day" onChange="SetFieldProperty('rdaWeekStartDayGField', this.value);">
						<option value="">Choose Day</option>
						<option value="0">Sunday</option>
						<option value="1">Monday</option>
						<option value="2">Tuesday</option>
						<option value="3">Wednesday</option>
						<option value="4">Thursday</option>
						<option value="5">Friday</option>
						<option value="6">Saturday</option>
					</select>
				</li>
				<li class="rda_disable_specific_dates field_setting">
					<label for="rda_disable_specific_dates" class="section_label">
						<?php esc_html_e("Type Specific Dates", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("rda_dis_sp_dates"); ?>
					</label>
					<input type="text" id="rda_disable_specific_dates" placeholder="06/25/2020,06/29/2020" onChange="SetFieldProperty('rdaDisableSDatesGField', this.value);">
				</li>
				<li class="readonly_date_setting field_setting">
					<input type="checkbox" id="gfrda_enable_readonly" onclick="SetFieldProperty('readOnlyDateGField', this.checked);" />
					<label for="gfrda_enable_readonly" class="inline">
						<?php esc_html_e("Enable readonly", "restrict-dates-add-on-for-gravity-forms"); ?>
						<?php gform_tooltip("gfrda_readonly"); ?>
					</label>
				</li>
			</ul>
		</li>

	<?php
	}

	public function gfrda_advanced_settings($position, $form_id) {
		if ($position == 550) {
			$this->gcafe_fields_settings_tab_content(GFAPI::get_form($form_id));
		}
	}


	function gfrda_editor_script() {
	?>

		<script type='text/javascript'>
			//adding setting to fields of type "date"

			fieldSettings.date += ", .restrict_date_setting";
			fieldSettings.date += ", .rda_minimum_date";
			fieldSettings.date += ", .rda_minimum_date_picker";
			fieldSettings.date += ", .rda_maximum_date";
			fieldSettings.date += ", .rda_maximum_date_picker";
			fieldSettings.date += ", .readonly_date_setting";
			fieldSettings.date += ", .rda_disable_specific_dates";
			fieldSettings.date += ", .rda_weekly_off";
			fieldSettings.date += ", .rda_start_day";

			//binding to the load field settings event to initialize the checkbox

			jQuery(document).bind("gform_load_field_settings", function(event, field, form) {
				jQuery("#gfrda_enable_restrict_value").prop('checked', Boolean(rgar(field, 'restrictDateGField')));
				jQuery("#gfrda_enable_readonly").prop('checked', Boolean(rgar(field, 'readOnlyDateGField')));
				jQuery("#mini_date_value").val(field["rdaMinimumDateGField"]);
				jQuery("#max_date_value").val(field["rdaMaximumDateGField"]);
				jQuery("#field_minimum_date_picker").val(field["rdaMinDatePickGField"]);
				jQuery("#field_maximum_date_picker").val(field["rdaMaxDatePickGField"]);
				jQuery("#rda_disable_specific_dates").val(field["rdaDisableSDatesGField"]);
				jQuery("#weekly_date_value").val(field["rdaWeeklyDateGField"]);
				jQuery("#week_start_day").val(field["rdaWeekStartDayGField"]);
			});
		</script>

<?php
	}


	function gfrda_frontend_enqueue_scripts($form, $is_ajax) {
		$form_id = $form['id'];
		$fields_data = [];

		foreach ($form['fields'] as $field) {
			if (property_exists($field, 'restrictDateGField') && $field->restrictDateGField) {
				$form = (array) GFFormsModel::get_form_meta($field->formId);
				$fields_data[] = json_encode(GFFormsModel::get_field($form, $field->id));
			}
		}

		if (count($fields_data) === 0) {
			return;
		}

		wp_enqueue_script('gfrd_data', $this->get_base_url() . '/assets/js/gf_rd_data.js', array('jquery'), $this->_version, true);
		wp_localize_script(
			'gfrd_data',
			'gfrdMainJsVars_' . $form_id,
			array(
				'elements' =>  $fields_data
			)
		);
	}

	function gfrda_add_tooltips() {
		$tooltips['enable_rd'] = "<h6>" . esc_html__("Enable restrict options", "restrict-dates-add-on-for-gravity-forms") . "</h6>" . esc_html__("Check this box to show date restrict options.", "restrict-dates-add-on-for-gravity-forms") . "";
		$tooltips['gfrda_readonly'] = "<h6>" . esc_html__("Enable Readonly", "restrict-dates-add-on-for-gravity-forms") . "</h6>" . esc_html__("Check this box to disable manual date input change.", "restrict-dates-add-on-for-gravity-forms") . "";
		$tooltips['rda_weekly_date'] = "<h6>" . esc_html__("Week/Off day", "restrict-dates-add-on-for-gravity-forms") . "</h6>" . esc_html__("Choose your day to disable in weekly.", "restrict-dates-add-on-for-gravity-forms") . "";
		$tooltips['rda_max_date'] = "<h6>" . esc_html__("Maximum Date", "restrict-dates-add-on-for-gravity-forms") . "</h6>" . esc_html__("Choose maximum date for restricting in date-picker", "restrict-dates-add-on-for-gravity-forms") . "";
		$tooltips['rda_min_date'] = "<h6>" . esc_html__("Minimum Date", "restrict-dates-add-on-for-gravity-forms") . "</h6>" . esc_html__("Choose minimum date for restricting in date-picker", "restrict-dates-add-on-for-gravity-forms") . "";
		$tooltips['rda_dis_sp_dates'] = "<h6>" . esc_html__("Disable Specific Dates", "restrict-dates-add-on-for-gravity-forms") . "</h6>" . esc_html__("Type your dates to disable with comma spearate. Ex: mm/dd/yyyy", "restrict-dates-add-on-for-gravity-forms") . "";
		$tooltips['rda_week_start_day'] = "<h6>" . esc_html__("Week Start Day", "restrict-dates-add-on-for-gravity-forms") . "</h6>" . esc_html__("Choose week start day for your calendar.", "restrict-dates-add-on-for-gravity-forms") . "";
		return $tooltips;
	}
}
