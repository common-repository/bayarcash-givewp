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

class CronEvent {
	private Bayarcash $pt;

	public function __construct(Bayarcash $pt) {
		$this->pt = $pt;
	}

	public function register() {
		add_filter(
			'cron_schedules',
			function ($schedules) {
				$schedules['bayarcash_givewp_schedule'] = [
					'interval' => 5 * MINUTE_IN_SECONDS,
					'display'  => esc_html__('Every 5 Minutes', 'bayarcash-givewp'),
				];
				return $schedules;
			},
			PHP_INT_MAX
		);

		// Remove old schedule
		if (false !== wp_get_scheduled_event('bayarcash_update_payment')) {
			wp_clear_scheduled_hook('bayarcash_update_payment');
		}

		add_action('bayarcash_givewp_checkpayment', [$this, 'check_payment']);
		if (!wp_next_scheduled('bayarcash_givewp_checkpayment')) {
			wp_schedule_event(time(), 'bayarcash_givewp_schedule', 'bayarcash_givewp_checkpayment');
		}
	}

	public function unregister() {
		foreach (['bayarcash_update_payment', 'bayarcash_givewp_checkpayment'] as $hook) {
			wp_clear_scheduled_hook($hook);
		}
	}

	/**
	 * @throws \Exception
	 */
	public function check_payment() {
		error_log('Bayarcash GiveWP: check_payment method started at ' . current_time('mysql'));

		if (!$this->pt->is_givewp_activated()) {
			error_log('Bayarcash GiveWP: GiveWP is not activated');
			return;
		}

		$payments_query = new PaymentsQuery();
		$unpaid_payments = $payments_query->get_payments();

		error_log('Bayarcash GiveWP: Number of unpaid payments: ' . count($unpaid_payments));

		if (empty($unpaid_payments)) {
			error_log('Bayarcash GiveWP: No unpaid payments detected');
			return;
		}

		foreach ($unpaid_payments as $payment) {
			$transaction_id = give_get_payment_meta($payment->ID, 'bayarcash_transaction_id');
			$payment_gateway = give_get_payment_meta($payment->ID, '_give_payment_gateway');

			error_log("Bayarcash GiveWP: Processing transaction: {$transaction_id} with gateway: {$payment_gateway}");

			$donation_form_id = give_get_payment_form_id($payment->ID);

			// Get the appropriate token based on the payment gateway
			$bayarcash_portal_token = $this->get_portal_token($payment_gateway, $donation_form_id);

			if (empty($bayarcash_portal_token)) {
				error_log("Bayarcash GiveWP: No portal token found for gateway: {$payment_gateway}");
				continue;
			}

			$transaction_data = $this->pt->data_request()->requery(
				sanitize_text_field($transaction_id),
				$bayarcash_portal_token
			);

			//error_log('Requery response: ' . print_r($transaction_data, true));

			$this->pt->data_store()->update_payment_fpx($transaction_data);
			error_log('Payment updated');

		}

		error_log('Bayarcash GiveWP: check_payment method completed at ' . current_time('mysql'));
	}

	private function get_portal_token($payment_gateway, $form_id) {
		$setting_key = $this->get_setting_key($payment_gateway);

		$customize_donations = give_get_meta($form_id, "{$setting_key}_givewp_customize_donations", true) === 'enabled';

		if ($customize_donations) {
			return give_get_meta($form_id, "{$setting_key}_portal_token", true);
		} else {
			return give_get_option("{$setting_key}_portal_token");
		}
	}

	private function get_setting_key($payment_gateway): string {
		$keys = [
			'bayarcash' => 'bayarcash',
			'bayarcash_duitnow' => 'bc-duitnow',
			'bayarcash_linecredit' => 'bc-linecredit',
			'bayarcash_duitnowqr' => 'bc-duitnowqr',
			'bayarcash_duitnowshopee' => 'bc-duitnowshopee',
			'bayarcash_duitnowboost' => 'bc-duitnowboost',
			'bayarcash_duitnowqris' => 'bc-duitnowqris',
			'bayarcash_duitnowqriswallet' => 'bc-duitnowqriswallet'
		];

		return $keys[$payment_gateway] ?? 'bayarcash';
	}
}