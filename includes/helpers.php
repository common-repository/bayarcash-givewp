<?php
/**
 * Bayarcash GiveWP.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */

namespace BayarCash\GiveWP;

\defined('ABSPATH') || exit;

function debug_log($log)
{
    $logfile = WP_CONTENT_DIR.'/bayarcash-givewp-debug.log';
    if (\defined('BAYARCASH_GIVEWP_DEBUG_LOG') && \is_string(BAYARCASH_GIVEWP_DEBUG_LOG)) {
        $logfile = BAYARCASH_GIVEWP_DEBUG_LOG;
    }

    if (\defined('BAYARCASH_GIVEWP_DEBUG') && true === (bool) BAYARCASH_GIVEWP_DEBUG) {
        $timestamp = '['.date('d-M-Y H:i:s T').'] ';
        if (\is_array($log) || \is_object($log)) {
            error_log(str_replace($timestamp.'Array', $timestamp, $timestamp.print_r($log, true)).\PHP_EOL, 3, $logfile);
        } else {
            error_log($timestamp.$log.\PHP_EOL, 3, $logfile);
        }
    }
}

function get_response_data($type = '')
{
	if (empty($_POST)) {
		return [];
	}

	$keys_checksum_fpx = [
		'order_ref_no',
		'order_no',
		'transaction_currency',
		'order_amount',
		'buyer_name',
		'buyer_email',
		'buyer_bank_name',
		'transaction_status',
		'transaction_status_description',
		'transaction_datetime',
		'transaction_gateway_id',
	];

	$keys_checksum_directdebit = [
		'created_at',
		'order_number',
		'order_description',
		'order_currency',
		'order_amount',
		'merchant_name',
		'buyer_name',
		'buyer_email',
		'buyer_bank_name',
		'transaction_exchange_ref_no',
		'transaction_status',
		'transaction_status_description',
		'transaction_id',
		'transaction_date_time',
		'mandate_application_type',
		'mandate_payment_reference_number',
		'mandate_amount',
		'mandate_max_frequency',
		'mandate_frequency_mode_name',
		'mandate_frequency_mode_label',
		'mandate_payment_purpose',
		'payment_channel',
		'payment_gateway_id',
	];

	$keys_general = [
		'amount',
		'buyer_bank_account_no',
		'buyer_bank_code',
		'buyer_bank_code_hashed',
		'buyer_bank_name',
		'buyer_email',
		'buyer_id',
		'buyer_id_type',
		'buyer_name',
		'buyer_tel_no',
		'currency',
		'datetime',
		'failed_url',
		'fpx_data',
		'maintenance_url',
		'mandate_application_reason',
		'mandate_application_type',
		'mandate_e ective_date',
		'mandate_expiry_date',
		'mandate_frequency_mode',
		'mandate_max_frequency',
		'mandate_no',
		'mandate_order_no',
		'mandate_status',
		'order_amount',
		'order_currency',
		'order_description',
		'order_no',
		'order_ref_no',
		'payment_gateway_name',
		'payment_model',
		'raw',
		'raw_data',
		'record_type',
		'return_url',
		'status',
		'status_description',
		'success_url',
		'termination_url',
		'transaction_currency',
		'transaction_datetime',
		'transaction_gateway_id',
		'transaction_status',
		'transaction_status_description',
	];

	switch ($type) {
		case 'fpx':
			$keys = $keys_checksum_fpx;
			break;
		case 'directdebit':
			$keys = $keys_checksum_directdebit;
			break;
		default:
			$keys = array_merge($keys_general, $keys_checksum_fpx, $keys_checksum_directdebit);
	}

	$post_data = [];

	foreach ($keys as $key) {
		if (isset($_POST[$key])) {
			$post_data[$key] = sanitize_text_field(wp_unslash($_POST[$key]));
		}
	}

	return $post_data;
}

function is_fpx_transaction_status($status, $match)
{
    $lists = [
        'new',
        'pending',
        'unsuccessful',
        'successful',
        'cancelled',
    ];

    $match = strtolower($match);
    $index = (int) array_search($match, $lists);
    if ($index === (int) $status) {
        return true;
    }

    return false;
}

function note_text($data)
{
    $note = '';
    foreach ($data as $k => $v) {
        $k = str_replace('_', ' ', str_replace('fpx_data', 'FPX_data', $k));
        $k = ucwords($k);
        $note .= $k.': '.$v.' | ';
    }

    return esc_html(rtrim(trim($note), '|'));
}

function directdebit_register_note($data)
{
    $data = (object) $data;

    if (!isset($data->order_number)) {
        return '';
    }

    return [
        'Donation ID: '.$data->order_number,
        'Exchange Reference Number: '.$data->transaction_exchange_ref_no,
        'ID Number: '.$data->transaction_id,
        'Transaction Status: '.$data->transaction_status,
        'Transaction Status Description: '.$data->transaction_status_description,
        'Donor Bank Name: '.$data->buyer_bank_name,
        'Donor Name: '.$data->buyer_name,
        'Donor Email: '.$data->buyer_email,
        'Mandate Amount: '.'RM'.' '.$data->mandate_amount,
        'Mandate Frequency: '.$data->mandate_frequency_mode_name,
    ];
}
