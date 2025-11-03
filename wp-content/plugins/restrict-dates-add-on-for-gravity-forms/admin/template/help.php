<?php

$faqs = [
    [
        'question' => __('Can I disable specific days of the week, like weekends?', 'restrict-dates-add-on-for-gravity-forms'),
        'answer' => __('Yes. You can disable any weekday (for example, Saturday and Sunday) to prevent users from choosing those dates.', 'restrict-dates-add-on-for-gravity-forms'),
    ],
    [
        'question' => __('Can I set a minimum and maximum date range?', 'restrict-dates-add-on-for-gravity-forms'),
        'answer' => __('Absolutely. You can define both a minimum and maximum selectable date. For example, allow only dates from “tomorrow” to “30 days from today.”', 'restrict-dates-add-on-for-gravity-forms'),
    ],
    [
        'question' => __('Does it support multiple date fields in the same form?', 'restrict-dates-add-on-for-gravity-forms'),
        'answer' => __('Yes, it does support.', 'restrict-dates-add-on-for-gravity-forms'),
    ],
    [
        'question' => __('Can I restrict dates globally for all forms?', 'restrict-dates-add-on-for-gravity-forms'),
        'answer' => __('Currently, restrictions are applied per field of gravity forms.', 'restrict-dates-add-on-for-gravity-forms'),
    ],
    [
        'question' => __('Does it support inline date picker?', 'restrict-dates-add-on-for-gravity-forms'),
        'answer' => __('Yes, Its supproted but only in the pro version.', 'restrict-dates-add-on-for-gravity-forms'),
    ]
];

?>


<div id="help" class="help_introduction tab_item">
    <div class="content_heading">
        <h2><?php esc_html_e('Frequently Asked Questions', 'restrict-dates-add-on-for-gravity-forms'); ?></h2>
    </div>

    <section class="section_faq">
        <?php foreach ($faqs as $key => $faq) : ?>
            <div class="faq_item">
                <input type="checkbox" name="accordion-1" id="faq<?php echo esc_attr($key); ?>">
                <label for="faq<?php echo esc_attr($key); ?>" class="faq__header">
                    <?php echo esc_html($faq['question']); ?>
                    <i class="dashicons dashicons-arrow-down-alt2"></i>
                </label>
                <div class="faq__body">
                    <p><?php echo esc_html($faq['answer']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <div class="content_heading">
        <h2><?php esc_html_e('Need Help?', 'restrict-dates-add-on-for-gravity-forms'); ?></h2>
        <p><?php esc_html_e('If you have any questions or need help, please feel free to contact us.', 'restrict-dates-add-on-for-gravity-forms'); ?></p>
    </div>

    <div class="help_docs">
        <section class="help_box section_half">
            <div class="help_box__img">
                <img src="<?php echo esc_url(GF_RESTRICT_DATES_ADDON_URL . 'admin/images/docs.svg'); ?>">
            </div>
            <div class="help_box__content">
                <h3><?php esc_html_e('Documentation', 'restrict-dates-add-on-for-gravity-forms'); ?></h3>
                <p><?php esc_html_e('Check out our detailed online documentation and video tutorials to find out more about what you can do.', 'restrict-dates-add-on-for-gravity-forms'); ?></p>
                <a target="_blank" href="https://pluginscafe.com/docs/restrict-dates-for-gravity-forms/" class="pcafe_btn"><?php esc_html_e('Documentation', 'restrict-dates-add-on-for-gravity-forms'); ?></a>
            </div>
        </section>
        <section class="help_box section_half">
            <div class="help_box__img">
                <img src="<?php echo esc_url(GF_RESTRICT_DATES_ADDON_URL . 'admin/images/service247.svg'); ?>">
            </div>
            <div class="help_box__content">
                <h3><?php esc_html_e('Support', 'restrict-dates-add-on-for-gravity-forms'); ?></h3>
                <p><?php esc_html_e('We have dedicated support team to provide you fast, friendly & top-notch customer support.', 'restrict-dates-add-on-for-gravity-forms'); ?></p>
                <a target="_blank" href="https://wordpress.org/support/plugin/restrict-dates-add-on-for-gravity-forms/" class="pcafe_btn"><?php esc_html_e('Get Support', 'restrict-dates-add-on-for-gravity-forms'); ?></a>
            </div>
        </section>
    </div>
</div>