<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'ndtss' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'R_2<8*5$g7+Sd&K1(>jj,_QR.6AE$wR:aIS PbSiGl *OPQrEk_R6r&<Y@|k=;FZ' );
define( 'SECURE_AUTH_KEY',  'Sj61Ri?Qf$*iy(Cf7o4q<Q LD;tZ:mumf(k<IQJscX??3j.Ntr:g=G!{F/hCTYJ5' );
define( 'LOGGED_IN_KEY',    'kGekq%pO:CMGh_$3[u7C.m-@e.#bSGAW8Ozxuhy uyl6c*y/<MoM]}br+MRau|Vl' );
define( 'NONCE_KEY',        'QJMow+swz+>QY@99;x2VzhX.ESPRFkLmF(W0z/kxsq,HnAT#~DEawRsw.g,K=+V<' );
define( 'AUTH_SALT',        '!m0kgBw_EaSp7v*q D3Hn6=LL=&581oE5H6Ckjf>xR3])G$jsG#C:DWiT]Y;^@(z' );
define( 'SECURE_AUTH_SALT', '6Ka%2};1&$58zSh42D<rX[ZF&o?rJl(:MN3H.e3S&c]i(mX+aUtWY]p^ptWyi!9i' );
define( 'LOGGED_IN_SALT',   'K) {(9WCBs_-=A&Hotno]o~!J^OoideXkYQip4F4h)ed>VQ;x.=+.2O9z/juNJ[i' );
define( 'NONCE_SALT',       '+oraB`]CuTDV7MkrFgG]|@<~(b&zKE![.95b6!A82!oOLgi`}`ZRUBz+Yop/?g_y' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
