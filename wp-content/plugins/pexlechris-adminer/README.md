# Database Manager - WP Adminer

This is the public GitHub repository of WP Adminer.
Contributors: pexlechris
Plugin Name: Database Manager - WP Adminer
Author: Pexle Chris
Author URI: https://www.pexlechris.dev
Tags: Adminer, Database, sql, mysql, mariadb
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage the database from your WordPress Dashboard using Adminer.

## Description

The best database management tool for the best CMS.

This plugin uses the tool [Adminer](https://www.adminer.org/), in order to give database access to administrators directly from the Dashboard.
As simple as the previous sentence!

I am not the author of Adminer. I am only the author who does the WordPress integration with Adminer.
Author of Adminer is Jakub Vrana and you can donate him from [there](https://www.paypal.com/donate/?item_name=Donation+to+Adminer&cmd=_donations&business=jakub%40vrana.cz).

Compatible also with WordPress Multisite installations





## Frequently Asked Questions

[Find the answer to your question here](https://wordpress.org/plugins/pexlechris-adminer/#faq)

## Hooks

### `pexlechris_adminer_access_capabilities`

**Type:** `add_filter`

**Description:**
Allows changing the capabilities required to access the WP Adminer page. By default, only users with the `manage_options` capability (administrators) can access it.

**Example:**
```php
add_filter('pexlechris_adminer_access_capabilities', function($capabilities) {
    return ['manage_options', 'my_custom_capability'];
});
```

### `pexlechris_adminer_head`

**Type:** `add_action`

**Description:**
Allows adding custom CSS and JavaScript to the head of the Adminer page.

**Example:**
```php
add_action('pexlechris_adminer_head', function() {
    ?>
    <style>
        body {
            background-color: #f0f0f0;
        }
    </style>
    <script>
        console.log('Adminer page loaded');
    </script>
    <?php
});
```

### `pexlechris_adminer_adminbar_dropdown_items`

**Type:** `add_filter`

**Description:**
Allows customizing the items in the WP Adminer dropdown menu in the admin bar.

**Example:**
```php
add_filter('pexlechris_adminer_adminbar_dropdown_items', function($items) {
    $items[] = [
        'name'  => 'my_custom_table',
        'label' => 'My Custom Table',
    ];
    return $items;
});
```

### `pexlechris_adminer_before_adminer_loads`

**Type:** `add_action`

**Description:**
Allows running custom code before the Adminer interface is loaded.

**Example:**
```php
add_action('pexlechris_adminer_before_adminer_loads', function() {
    // Custom code here
});
```

### `pexlechris_adminer_url`

**Type:** `add_filter`

**Description:**
Allows changing the URL of the WP Adminer page.

**Example:**
```php
add_filter('pexlechris_adminer_url', function($url) {
    return home_url('/my-custom-adminer-url');
});
```

### `pexlechris_adminer_locale`

**Type:** `add_filter`

**Description:**
Allows changing the locale of the Adminer interface.

**Example:**
```php
add_filter('pexlechris_adminer_locale', function($locale) {
    return 'fr';
});
```

### `pexlechris_adminer_sticky_links`

**Type:** `add_filter`

**Description:**
Allows customizing the sticky links at the top of the Adminer interface.

**Example:**
```php
add_filter('pexlechris_adminer_sticky_links', function($links) {
    $links[] = [
        'label' => 'My Custom Link',
        'url'   => 'https://example.com',
    ];
    return $links;
});
```
