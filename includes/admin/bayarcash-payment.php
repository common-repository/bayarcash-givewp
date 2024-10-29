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
 * This file served as a wrapper to solve the issue with the X-Frame-Options header.
 * This file will receive input from BayarCash\GiveWP\Givewp::process_payment() and send the payment data to the Bayarcash end-point.
 * The input should send as $_GET query and not as HTML form.
 *
 * References:
 *  https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-Frame-Options
 *  includes/src/Givewp.php
*/
\defined('ABSPATH') && exit;

if (empty($_GET['u']) || empty($_GET['p']) || !\is_array($_GET['p'])) {
	exit('Invalid request!');
}

if (false === strpos($_GET['u'], 'console.bayar.cash') && false === strpos($_GET['u'], 'console.bayarcash-sandbox.com')) {
	exit('Invalid endpoint!');
}

$wpload_file = realpath('../../../../../wp-load.php');
if (false === $wpload_file) {
	exit('wp-load file not found!');
}

try {
	\define('SHORTINIT', true);
	require $wpload_file;
	require ABSPATH.WPINC.'/kses.php';
	require ABSPATH.WPINC.'/class-wp-block-parser.php';
	require ABSPATH.WPINC.'/blocks.php';
} catch (\Throwable $e) {
	exit('Failed to load wp-load file');
}

$args = [];

foreach ([
	'order_no',
	'buyer_name',
	'buyer_email',
	'buyer_tel_no',
	'order_amount',
	'return_url',
	'portal_key',
	'payment_gateway',
	'buyer_id',
	'buyer_id_type',
	'order_description',
	'mandate_frequency_mode',
	'mandate_max_frequency',
	'mandate_application_type',
	'mandate_effective_date',
	'mandate_expiry_date',
	'return_url',
	'success_url',
	'failed_url',
] as $key) {
	if (isset($_GET['p'][$key])) {
		$args[$key] = $_GET['p'][$key];
	}
}

$output = '<html><head><title>Bayarcash</title>';
$output .= '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, max-age=0, s-maxage=0, proxy-revalidate">';
$output .= '<meta http-equiv="Expires" content="0">';
$output .= '</head><body>';
$output .= '<form name="payment" id="bayarcash_payment" method="post" action="'.esc_url_raw($_GET['u']).'">';
foreach ($args as $f => $v) {
	$output .= '<input type="hidden" name="'.esc_attr($f).'" value="'.esc_attr($v).'">';
}

$output .= '</form>';
$output .= '<script>document.getElementById( "bayarcash_payment" ).submit();</script>';
$output .= '</body></html>';

echo wp_kses(
	$output,
	[
		'html'  => [],
		'head'  => [],
		'title' => [],
		'body'  => [],
		'meta'  => [
			'http-equiv' => [],
			'content'    => [],
		],
		'form' => [
			'name'   => [],
			'id'     => [],
			'method' => [],
			'action' => [],
		],
		'input' => [
			'type'  => [],
			'name'  => [],
			'value' => [],
		],
		'script' => [],
	]
);
exit;