<?php
/**
 * Bayarcash GiveWP.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */

/*
 * @wordpress-plugin
 * Plugin Name:         Bayarcash GiveWP
 * Plugin URI:          https://bayarcash.com/
 * Version:             4.1.0
 * Description:         Accept online donation & QR from Malaysia. Currently, Bayarcash support FPX, Direct Debit and DuitNow payment channels.
 * Author:              Web Impian
 * Author URI:          https://bayarcash.com/
 * Requires at least:   5.6
 * Requires PHP:        8.0
 * License:             GPLv3
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:         bayarcash-givewp
 * Domain Path:         /languages
 * Requires Plugins:     give
 */

namespace BayarCash\GiveWP;

\defined('ABSPATH') && !\defined('BAYARCASH_GIVEWP_FILE') || exit;

\define('BAYARCASH_GIVEWP_FILE', __FILE__);
require __DIR__.'/includes/load.php';
(new Bayarcash())->register();
