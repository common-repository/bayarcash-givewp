<?php
namespace BayarCash\GiveWP;

class DataStore
{
	public function update_payment_fpx($transaction_data)
	{
		if (!isset($transaction_data['order_number']) || !isset($transaction_data['status'])) {
			error_log('Missing order_number or status in transaction data');
			return;
		}

		$payment_id = $transaction_data['order_number'];
		$status_number = $transaction_data['status'];

		$status_list = ['new', 'pending', 'unsuccessful', 'successful', 'cancelled'];
		$status_name = $status_list[ $status_number ] ?? 'unknown';

		$form_url = Give()->payment_meta->get_meta($payment_id, '_give_current_url', true);
		$request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';

		$payment_note_arr = static function ($records, $status = '') {
			return [
				'Donation ID: '.$records['order_number'],
				'Exchange Reference Number: '.$records['exchange_reference_number'],
				'ID Number: '.$records['id'],
				'Transaction Status: '.$status,
				'Transaction Status Description: '.$records['status_description'],
				'Donor Bank Name: '.$records['payer_bank_name'],
				'Transaction Amount: '.$records['currency'].' '.$records['amount'],
				'Donor Name: '.$records['payer_name'],
				'Donor Email: '.$records['payer_email'],
			];
		};

		if ($status_name === 'successful') {
			$payment_status = 'complete';
			give_update_payment_status($payment_id, $payment_status);
			//delete_post_meta($payment_id, 'bayarcash_fpx_transaction_exchange_no');

			give_insert_payment_note($payment_id, implode(' | ', $payment_note_arr($transaction_data, $payment_status)));

			debug_log([
				'caller'      => __METHOD__,
				'form-url'    => $form_url,
				'request-uri' => $request_uri,
				'content'     => implode(' | ', $payment_note_arr($transaction_data, $payment_status)),
			]);

			error_log('Payment ' . $payment_id . ' updated to complete status');
		} elseif ($status_name === 'unsuccessful' || $status_name === 'cancelled') {
			$payment_status = 'failed';
			give_update_payment_status($payment_id, $payment_status);
			//delete_post_meta($payment_id, 'bayarcash_fpx_transaction_exchange_no');

			give_insert_payment_note($payment_id, implode(' | ', $payment_note_arr($transaction_data, $payment_status)));

			debug_log([
				'caller'      => __METHOD__,
				'form-url'    => $form_url,
				'request-uri' => $request_uri,
				'content'     => implode(' | ', $payment_note_arr($transaction_data, $payment_status)),
			]);

			error_log('Payment ' . $payment_id . ' updated to failed status');
		} else {
			$payment_status = 'pending';
			give_update_payment_status($payment_id, $payment_status);
			give_insert_payment_note($payment_id, implode(' | ', $payment_note_arr($transaction_data, $payment_status)));
			debug_log([
				'caller'      => __METHOD__,
				'form-url'    => $form_url,
				'request-uri' => $request_uri,
				'content'     => 'The payment status of #' . $payment_id . ' is still not resolved yet.',
			]);

			error_log('The payment status of #' . $payment_id . ' is still not resolved yet.');
		}
	}
}