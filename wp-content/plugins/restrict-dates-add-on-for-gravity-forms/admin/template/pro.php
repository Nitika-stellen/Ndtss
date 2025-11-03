<?php

$features = [
    [
        'feature'   => __('Minimum & Maximum Date Range', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => 0
    ],
    [
        'feature'   => __('Disable Week/Off Day', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => 0
    ],
    [
        'feature'   => __('Week Start Day', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => 0
    ],
    [
        'feature'   => __('Disable Specific Dates', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => 0
    ],
    [
        'feature'   => __('Readonly', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => 0
    ],
    [
        'feature'   => __('Date modifiers', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => true
    ],
    [
        'feature'   => __('Frontend Validation ', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => 1
    ],
    [
        'feature'   => __('Backend Validation', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => 1
    ],
    [
        'feature'   => __('Inline Date Picker', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => true
    ],
    [
        'feature'   => __('Add exceptions', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => true
    ],
    [
        'feature'   => __('Timezone support', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => true
    ],
    [
        'feature'   => __('30+ language support', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => true
    ],
    [
        'feature'   => __('Date calculation', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => true
    ],
    [
        'feature'   => __('Custom validation text', 'restrict-dates-add-on-for-gravity-forms'),
        'pro'       => true
    ]
];

?>
<div id="pro" class="pro_introduction tab_item">

    <div class="content_heading">
        <h2><?php esc_html_e('Unlock the full power of Restrict Dates For Gravity Forms', 'restrict-dates-add-on-for-gravity-forms'); ?></h2>
        <p><?php esc_html_e('The amazing PRO features will make your restrict dates even more efficient.', 'restrict-dates-add-on-for-gravity-forms'); ?></p>
        <?php if (! rdfgf_fs()->is_plan('pro', true)) : ?>
            <a href="<?php echo esc_url(rdfgf_fs()->get_upgrade_url()); ?>" class="pcafe_btn">
                <?php esc_html_e('Get PRO Now', 'restrict-dates-add-on-for-gravity-forms'); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="content_heading free_vs_pro">
        <h2>
            <span><?php esc_html_e('Free', 'restrict-dates-add-on-for-gravity-forms'); ?></span>
            <?php esc_html_e('vs', 'restrict-dates-add-on-for-gravity-forms'); ?>
            <span><?php esc_html_e('Pro', 'restrict-dates-add-on-for-gravity-forms'); ?></span>
        </h2>
    </div>

    <div class="features_list">
        <div class="list_header">
            <div class="feature_title"><?php esc_html_e('Feature List', 'restrict-dates-add-on-for-gravity-forms'); ?></div>
            <div class="feature_free"><?php esc_html_e('Free', 'restrict-dates-add-on-for-gravity-forms'); ?></div>
            <div class="feature_pro"><?php esc_html_e('Pro', 'restrict-dates-add-on-for-gravity-forms'); ?></div>
        </div>
        <?php foreach ($features as $feature) : ?>
            <div class="feature">
                <div class="feature_title"><?php echo esc_html($feature['feature']); ?></div>
                <div class="feature_free">
                    <?php if ($feature['pro']) : ?>
                        <i class="dashicons dashicons-no-alt"></i>
                    <?php else : ?>
                        <i class="dashicons dashicons-saved"></i>
                    <?php endif; ?>
                </div>
                <div class="feature_pro">
                    <i class="dashicons dashicons-saved"></i>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (! rdfgf_fs()->is_plan('pro', true)) : ?>
        <div class="pro-cta background_pro">
            <div class="cta-content">
                <h2><?php esc_html_e('Don\'t waste time, get the PRO version now!', 'restrict-dates-add-on-for-gravity-forms'); ?></h2>
                <p><?php esc_html_e('Upgrade to the PRO version of the plugin and unlock all the amazing Restrict Dates features for
                your website.', 'restrict-dates-add-on-for-gravity-forms'); ?></p>
            </div>
            <div class="cta-btn">
                <a href="<?php echo esc_url(rdfgf_fs()->get_upgrade_url()); ?>" class="pcafe_btn"><?php esc_html_e('Upgrade Now', 'restrict-dates-add-on-for-gravity-forms'); ?></a>
            </div>
        </div>
    <?php endif; ?>

    <div class="pro-cta background_free">
        <div class="cta-content">
            <h2><?php esc_html_e('Want to try live demo, before purchase?', 'restrict-dates-add-on-for-gravity-forms'); ?></h2>
            <p><?php esc_html_e('Try our instant ready-made demo with form submission! If you use an active email address, you\'ll also receive a notification.', 'restrict-dates-add-on-for-gravity-forms'); ?></p>
        </div>
        <div class="cta-btn">
            <a href="https://demo.pluginscafe.com/restrict-dates-for-gravity-forms-pro/" target="_blank" class="pcafe_btn"><?php esc_html_e('Try Live Demo', 'restrict-dates-add-on-for-gravity-forms'); ?></a>
        </div>
    </div>
</div>