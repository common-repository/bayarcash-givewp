<?php

namespace BayarCash\GiveWP;

use Exception;
use Give_Donor;
use Give_Subscription;
use Give_Recurring_Subscriber;
use stdClass;

class BayarcashCallbacks
{
	private Bayarcash $pt;
	private stdClass $endpoint_tokens;

	private Givewp $givewp;

	public function __construct(Bayarcash $pt, $endpoint_tokens, Givewp $givewp)
	{
		$this->pt = $pt;
		$this->endpoint_tokens = $endpoint_tokens;
		$this->givewp = $givewp;
	}

	/**
	 * @throws Exception
	 */
	public function process_callback(): void {

		//$response_data = $_POST;
		//error_log('Response data: ' . print_r($response_data, true));
		$this->callback_fpx();
		$this->callback_directdebit();
	}

	/**
	 * @throws Exception
	 */
	private function callback_fpx(): void {

		$response_data = $_POST;

		if (!$this->is_response_hit_plugin_callback_url()) {
			return;
		}

		if (!empty($_POST['mandate_application_type']) || empty($_GET['bc-givewp-return'])) {
			error_log('Invalid mandate_application_type or bc-givewp-return');
			return;
		}
		error_log('Response data: ' . print_r($response_data, true));

		// Determine the payment mode
		if (empty($response_data['order_number'])) {
			error_log('Invalid order number');
			wp_die('Invalid order number', 'Error', ['response' => 403]);
		}

		$payment_id = sanitize_text_field($response_data['order_number']);
		$payment_gateway = give_get_meta($payment_id, '_give_payment_gateway', true);

		error_log("Payment ID: $payment_id, Payment Gateway: $payment_gateway");

		if (empty($payment_gateway)) {
			error_log('Invalid payment gateway');
			wp_die('Invalid payment gateway', 'Error', ['response' => 403]);
		}

		// Get the appropriate tokens based on the payment gateway
		$tokens = $this->endpoint_tokens->$payment_gateway;

		if (empty($tokens)) {
			error_log('Invalid payment gateway configuration');
			wp_die('Invalid payment gateway configuration', 'Error', ['response' => 403]);
		}

		// Verify the callback data
		$bayarcashSdk = new \Webimpian\BayarcashSdk\Bayarcash($tokens['portal_token']);
		if (give_is_test_mode()) {
			$bayarcashSdk->useSandbox();
			error_log('Using sandbox mode');
		}

		$validResponse = $bayarcashSdk->verifyTransactionCallbackData($response_data, $tokens['secret_key']);

		if (!$validResponse) {
			error_log('Invalid callback data');
			wp_die('Invalid callback data', 'Error', ['response' => 403]);
		}

		error_log('Callback data verified successfully');

		// Captures FPX pre transaction data.
		if (isset($response_data['record_type']) || $response_data['record_type'] == 'pre_transaction') {
			error_log('Processing pre-transaction data');
			$transaction_data = $response_data;
			if (empty($transaction_data['order_number']) || empty($transaction_data['transaction_id'])) {
				error_log('Missing order_number or transaction_id in pre-transaction data');
				return;
			}

			$payment_pt = give_get_payment_by('id', $payment_id);
			if (empty($payment_pt)) {
				error_log('Payment not found');
				return;
			}

			if ('complete' == $payment_pt->status) {
				error_log('Payment already complete');
				return;
			}

			give_update_payment_status($payment_id, 'pending');
			give_update_meta($payment_id, 'bayarcash_transaction_id', $transaction_data['transaction_id']);
			error_log('Pre-transaction data processed successfully');
		}

		// Captures FPX primary transaction data.
		if ($response_data['record_type'] == 'transaction_receipt') {
			error_log('Processing transaction receipt data');
			if (!isset($response_data['transaction_id']) || !isset($response_data['status'])) {
				error_log('Missing transaction id or status in transaction receipt data');
				wp_die('Invalid request', 'Error', ['response' => 403]);
			}

			$form_id = give_get_payment_form_id($payment_id);
			if (empty($form_id)) {
				error_log('Form ID does not exist');
				wp_die('Form ID does not exist', 'Error', ['response' => 403]);
			}

			$receipt_url = give_get_failed_transaction_uri('?payment-id='.$payment_id);

			if (is_fpx_transaction_status(sanitize_text_field($response_data['status']), 'successful')) {
				$receipt_url = give_get_success_page_url('?payment-id=' . $payment_id . '&payment-confirmation=' . $payment_gateway);
				error_log('Transaction successful');
			} else {
				error_log('Transaction failed');
			}

			$transaction_data = $this->pt->data_request()->requery(
				sanitize_text_field($response_data['transaction_id']),
				$tokens['portal_token']
			);
			//error_log('Requery response: ' . print_r($transaction_data, true));

			$this->pt->data_store()->update_payment_fpx($transaction_data);
			error_log('Payment updated');

			error_log('Redirecting to receipt URL: ' . $receipt_url);
			exit($this->redirect($receipt_url));
		}

		error_log('Finished callback_fpx method');
	}

	private function callback_directdebit(): void {

		//$response_data = $_POST;
		//error_log('Response data: ' . print_r($response_data, true));

		if (!\function_exists('Give_Recurring') || !isset($_GET['bc-givewp-success']) && !isset($_GET['bc-givewp-failed']) && !isset($_GET['bc-givewp-return'])) {
			return;
		}


		if (isset($_GET['bc-givewp-success'])) {
			if (empty($_POST['mandate_application_type']) || empty($_POST['order_number']) || empty($_POST['fpx_data'])) {
				wp_die('Invalid request', 'Error', ['response' => 403]);
			}

			$fpx_data = sanitize_text_field($_POST['fpx_data']);
			unset($_POST['fpx_data']);

			$data_key   = sanitize_text_field($_GET['bc-givewp-success']);
			$payment_id = sanitize_text_field($_POST['order_number']);

			if (!$this->pt->get_return_token($data_key, $payment_id, 'directdebit')) {
				wp_die('Invalid token key: '.$data_key, 'Error', ['response' => 403]);
			}

			$payment = give_get_payment_by('id', $payment_id);
			if ('complete' == $payment->status) {
				debug_log([
					'caller'      => __METHOD__,
					'form-url'    => Give()->payment_meta->get_meta($payment_id, '_give_current_url', true),
					'request-uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
					'content'     => 'No further action needed as payment #'.$payment->ID.' is already in complete',
				]);

				return;
			}

			$payment_id = sanitize_text_field($_POST['order_number']);
			$payment_gateway = give_get_meta($payment_id, '_give_payment_gateway', true);
			$tokens = $this->endpoint_tokens->$payment_gateway;
			$key_data = md5($tokens['portal_key'].json_encode(get_response_data('directdebit')));
			if ($fpx_data !== $key_data) {
				wp_die('Data does not match', 'Error', ['response' => 403]);
			}

			if ('01' === $_POST['mandate_application_type']) {
				$signup_data = get_transient('bayarcash_givewp_directdebit_'.$payment_id);
				delete_transient('bayarcash_givewp_directdebit_'.$payment_id);

				give_update_payment_status($payment_id, 'pending');
				give_insert_payment_note($payment_id, implode(' | ', directdebit_register_note(get_response_data())));

				debug_log([
					'caller'      => __METHOD__,
					'form-url'    => Give()->payment_meta->get_meta($payment_id, '_give_current_url', true),
					'request-uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
					'content'     => get_response_data(),
				]);

				if (!empty($signup_data)) {
					if (empty($signup_data['user_id'])) {
						$subscriber = new Give_Donor(sanitize_text_field($_POST['buyer_email']));
					} else {
						$subscriber = new Give_Donor($signup_data['user_id'], true);
					}

					if (empty($subscriber->id)) {
						$subscriber->create([
							'name'    => sanitize_text_field($_POST['buyer_name']),
							'email'   => sanitize_text_field($_POST['buyer_email']),
							'user_id' => $signup_data['user_id'],
						]);
					}

					$signup_data['status']         = 'pending';
					$signup_data['payment_id']     = $payment_id;
					$signup_data['transaction_id'] = sanitize_text_field($_POST['transaction_id']);
					$signup_data['subscriber_id']  = $subscriber->id;
					$this->subscription_signup($signup_data);
				}

				$receipt_url = give_get_success_page_url('?payment-id='.$payment_id.'&bc-givewp-initial='.$payment_id);
				exit($this->redirect($receipt_url));
			}

			wp_die('Invalid application type: '.sanitize_text_field($_POST['mandate_application_type']), 'Error', ['response' => 403]);
		}

		if (isset($_GET['bc-givewp-failed'])) {
			$data_key = sanitize_text_field($_GET['bc-givewp-failed']);

			if (!($data_dec = $this->pt->get_return_token($data_key, 'bc-givewp-failed', 'directdebit'))) {
				wp_die('Invalid token key: '.$data_key, 'Error', ['response' => 403]);
			}

			$payment_id = $data_dec->data;
			delete_transient('bayarcash_givewp_directdebit_'.$payment_id);

			give_update_payment_status($payment_id, 'failed');
			$receipt_url = give_get_failed_transaction_uri('?payment-id='.$payment_id);

			if (!empty($_POST)) {
				if ('01' === $_POST['mandate_application_type']) {
					$response_data = get_response_data();
					give_insert_payment_note($payment_id, implode(' | ', directdebit_register_note($response_data)));

					debug_log([
						'caller'      => __METHOD__,
						'form-url'    => Give()->payment_meta->get_meta($payment_id, '_give_current_url', true),
						'request-uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
						'content'     => $response_data,
					]);
				}
			}

			exit($this->redirect($receipt_url));
		}

		// Request from Bayarcash
		if (!empty($_POST['record_type']) && !empty($_POST['order_no'])) {
			$response_data = get_response_data();

			// record_type: MandateApplications.
			if ('mandate_applications' === $_POST['record_type']) {
				wp_send_json_success('OK', 200);
			}

			// Mandate status
			if ('mandates' === $_POST['record_type']) {

				$payment_id = sanitize_text_field($_POST['order_no']);

				$data_key = sanitize_text_field($_GET['bc-givewp-return']);
				if (!$this->pt->get_return_token($data_key, $payment_id, 'directdebit')) {
					wp_send_json_error('Invalid token key: '.$data_key, 403);
				}

				$subscription_id = give_get_meta($payment_id, 'subscription_id', true);
				$subscription_pt = new Give_Subscription($subscription_id);
				if ('bayarcash' !== $subscription_pt->gateway) {
					wp_send_json_error('Invalid payment ID', 403);
				}

				if ('pending' !== $subscription_pt->status) {
					wp_send_json_error('Status is not pending', 200);
				}

				give_insert_payment_note($payment_id, note_text($response_data));

				$mandate_status = (int) sanitize_text_field($_POST['mandate_status']);
				// 0 = New
				// 1 = Waiting Approval
				// 2 = Verification Failed
				// 3 = Active
				// 4 = Terminated
				// 5 = Approved -> Only for record_type mandate_applications
				// 6 = Rejected
				// 7 = Cancelled
				// 8 = Error

				if (3 === $mandate_status) {
					give_update_payment_status($payment_id, 'pending');
					$subscription_pt->update(['status' => 'active']);
				}

				if (\in_array($mandate_status, [4, 6, 7, 8])) {
					give_update_payment_status($payment_id, 'failed');
					$subscription_pt->failing();
				}

				give_insert_subscription_note($subscription_pt->id, note_text($response_data));

				wp_send_json_success('Request accepted', 200);
			}

			// Mandate transaction
			if ('mandate_transactions' === $_POST['record_type']) {

				// Must use mandate order no or else getting invalid token error
				// because the URL token was generated using the mandate order_no.
				$payment_id = sanitize_text_field($_POST['mandate_order_no']);

				$data_key = sanitize_text_field($_GET['bc-givewp-return']);
				if (!($data_dec = $this->pt->get_return_token($data_key, $payment_id, 'directdebit'))) {
					wp_send_json_error('Invalid token key: '.$data_key, 403);
				}

				// Use order_no. It's already unique in Bayarcash.
				$transaction_id = isset($_POST['order_no']) ? sanitize_text_field($_POST['order_no']) : $data_dec->data_id;

				$subscription_id = give_get_meta($payment_id, 'subscription_id', true);
				$subscription_pt = new Give_Subscription($subscription_id);
				if ('bayarcash' !== $subscription_pt->gateway) {
					wp_send_json_error('Invalid payment ID: '.$payment_id, 403);
				}

				if (!$subscription_pt->is_active()) {
					wp_send_json_error('Subscription is not active yet', 403);
				}

				// Deduction
				if (empty($_POST['mandate_application_type']) || 'null' === $_POST['mandate_application_type']) {
					if (give_get_purchase_id_by_transaction_id($transaction_id)) {
						wp_send_json_success('Payment already recorded: '.$transaction_id, 200);
					}

					if ('3' !== $_POST['status']) {
						give_insert_subscription_note($subscription_pt->id, note_text($response_data));
						wp_send_json_success('Request accepted. Status ID: '.sanitize_text_field($_POST['status']), 200);
					}

					// Check for 1st cycle deduction
					if ('1' === $_POST['cycle']) {
						$parent_payment_id = sanitize_text_field($_POST['mandate_order_no']);
						give_update_payment_status($parent_payment_id, 'complete');

						give_insert_subscription_note($subscription_pt->id, note_text($response_data));

						wp_send_json_success('Request accepted. This is 1st cycle deduction: '.sanitize_text_field($_POST['cycle']), 200);
					}

					// 2nd deduction & onward
					if (empty($_POST['datetime'])) {
						$_POST['datetime'] = date('Y-m-d H:i:s');
					}

					$args = [
						'amount'         => sanitize_text_field($_POST['amount']),
						'transaction_id' => $transaction_id,
						'post_date'      => date('Y-m-d', strtotime(sanitize_text_field($_POST['datetime']))),
					];

					$subscription_pt->add_payment($args);
					$subscription_pt->renew();

					give_insert_subscription_note($subscription_pt->id, note_text($response_data));

					wp_send_json_success('Request accepted', 200);
				}

				// Terminate
				if ('03' === $_POST['mandate_application_type']) {
					$subscription_pt->cancel();

					give_insert_subscription_note($subscription_pt->id, note_text($response_data));
					wp_send_json_success('Request accepted', 200);
				}

				return;
			}
		}
	}

	private function is_response_hit_plugin_callback_url(): bool {

		$request_uri = esc_url_raw($_SERVER['REQUEST_URI']);
		$script_name = sanitize_file_name($_SERVER['SCRIPT_NAME']);

		$request = wp_parse_url($request_uri);
		$path    = $request['path'];

		$result = trim(str_replace(basename($script_name), '', $path), '/');

		$result    = explode('/', $result);
		$max_level = 2;

		if (\count($result) < $max_level) {
			return false;
		}

		$result = \array_slice($result, -2);

		$result = '/'.implode('/', $result);

		if (0 === strcmp($result, '/give-api/bayarcash_gateway')) {
			return true;
		}

		return false;
	}
	 private function redirect($redirect)
	{
		if (!headers_sent()) {
			wp_redirect($redirect);
			exit;
		}

		$html = "<script>window.location.replace('".$redirect."');</script>";
		$html .= '<noscript><meta http-equiv="refresh" content="1; url='.$redirect.'">Redirecting..</noscript>';

		echo wp_kses(
			$html,
			[
				'script'   => [],
				'noscript' => [],
				'meta'     => [
					'http-equiv' => [],
					'content'    => [],
				],
			]
		);
		exit;
	}

	private function subscription_signup($data): void {
		global $period;
		error_log('Data received in subscription_signup: ' . var_export($data, true));
		give_update_meta($data['payment_id'], '_give_subscription_payment', true);
		$subscriber = new Give_Recurring_Subscriber($data['subscriber_id']);
		$status     = !empty($data['status']) ? $data['status'] : 'pending';

		$times     = !empty($data['times']) ? (int) $data['times'] : 0;
		$frequency = !empty($data['frequency']) ? (int) ($data['frequency']) : 1;
		$mode      = give_get_meta($data['payment_id'], '_give_payment_mode', true) ?? (give_is_test_mode() ? 'test' : 'live');

		// Ensure period is not null
		$subscription_period = $this->givewp->get_interval($data['period'], $data['frequency']);
		if (empty($subscription_period)) {
			error_log('Subscription period is empty. Using default value.');
			return;
		}

		$args = [
			'form_id'              => $data['id'],
			'parent_payment_id'    => $data['payment_id'],
			'payment_mode'         => $mode,
			'status'               => $status,
			'period'               => $subscription_period,
			'frequency'            => $this->givewp->get_interval_count($period, $frequency),
			'initial_amount'       => give_sanitize_amount_for_db($data['initial_amount']),
			'recurring_amount'     => give_sanitize_amount_for_db($data['recurring_amount']),
			'recurring_fee_amount' => $data['recurring_fee_amount'] ?? 0,
			'bill_times'           => give_recurring_calculate_times($times, $frequency),
			'expiration'           => $subscriber->get_new_expiration($data['id'], $data['price_id'], $frequency, $data['period']),
			'profile_id'           => $data['subscriber_id'],
			'transaction_id'       => $data['transaction_id'],
			'user_id'              => $data['user_id'],
		];

		// Add error checking
		foreach (['period', 'frequency', 'initial_amount', 'recurring_amount'] as $required_field) {
			if (empty($args[$required_field])) {
				error_log("Required field '{$required_field}' is empty or null.");
				return; // Exit the function if any required field is missing
			}
		}

		$subscription_pt = $subscriber->add_subscription($args);

		if ($subscription_pt) {
			give_update_meta($data['payment_id'], 'subscription_id', $subscription_pt->id);
			give_insert_subscription_note($subscription_pt->id, note_text($args));
		} else {
			error_log('Failed to create subscription.');
		}
	}
}