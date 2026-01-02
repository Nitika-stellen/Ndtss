<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'sistagnewstellen_wp_p8plo');

/** Database username */
define('DB_USER', 'sistagnewstellen_wp_u4gor');

/** Database password */
define( 'DB_PASSWORD', '7E7r^%FdEo1cr$oL' );

/** Database hostname */
define( 'DB_HOST', 'localhost:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', 'J8;K+G7i1|3]byX6B0:7(rh8563(yZQx)sm__6W6G3oA:!/X0wL/OJ*)2fuR@rD6');
define('SECURE_AUTH_KEY', 'Q4Q8N@A57Fg8B7-xVmB&[&#!-N7c232KF29(MTo788nY%)VQ5TtJ@b*+8k8/%ow0');
define('LOGGED_IN_KEY', '(M_9nmdt56!rNhf_09X*wEh1Pks/H&I&)QkYq8-0Ae-i~%e_3yOMdv_I/9Emp_]|');
define('NONCE_KEY', '0iJ396*5~B_)%6/r|183ylb0qhJh#@33(]1v0+te19w0*4m05/I(K!3:-In8nK~O');
define('AUTH_SALT', ':Pc-7Dd5-0[ymTc:Jl9-5SRo(f24d4@%Y3D021i5d#uvE#+9s-#*_92(OfL-:(uy');
define('SECURE_AUTH_SALT', 'YT!g*P:MRu-q93r)4MCqq|!3R0*:3:wPwNg4)ZR9fqr89-;JaJ@d#3Y+~mw57b&g');
define('LOGGED_IN_SALT', 'ZV0E*~N5~27Nqk%42(Fh0za5;5!y3tnS8]3w9271(Ci!UvG40Lh2!@[kv8*F48B1');
define('NONCE_SALT', '+9U4ecTVbwRQR9JJ@9IfUF0_9Ke(s79ZYgGX/w3n5W7jKHicU8z/]3Y:U5C&&XXn');

set_time_limit(900);
/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'mTPYv6oG_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_ALLOW_MULTISITE', false);
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	
}

define('WP_DEBUG', true);
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );

// ✅ Only log fatal errors
error_reporting( E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR );

// 👇 Custom handler to filter out notices/warnings from debug.log
set_error_handler( function( $errno, $errstr, $errfile, $errline ) {
    // Allow only fatal-like errors to be logged
    if ( in_array( $errno, [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ] ) ) {
        error_log( "PHP Fatal Error: $errstr in $errfile on line $errline" );
    }
    return true; // Suppress default PHP logging
} );


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
