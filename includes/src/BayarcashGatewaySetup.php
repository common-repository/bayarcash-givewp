<?php
namespace BayarCash\GiveWP;

class BayarcashGatewaySetup {
	private const GATEWAYS = [
		'bayarcash' => 'Online Banking',
		'bayarcash_duitnow' => 'Online Banking (DuitNow)',
		'bayarcash_linecredit' => 'Credit Card',
		'bayarcash_duitnowqr' => 'DuitNow QR',
		'bayarcash_duitnowshopee' => 'SPayLater',
		'bayarcash_duitnowboost' => 'Boost PayFlex',
		'bayarcash_duitnowqris' => 'Indonesia Online Banking',
		'bayarcash_duitnowqriswallet' => 'Indonesia e-Wallet'
	];

	private const CHANNELS = [
		'bayarcash',
		'bc-duitnow',
		'bc-linecredit',
		'bc-duitnowqr',
		'bc-duitnowshopee',
		'bc-duitnowboost',
		'bc-duitnowqris',
		'bc-duitnowqriswallet'
	];

	public static function init(): void {
		add_filter('give_payment_gateways', [self::class, 'register_gateway']);
		add_filter('give_get_sections_gateways', [self::class, 'register_gateway_section']);
		add_action('give_init', [self::class, 'remove_cc_form']);
		add_filter('give_enabled_payment_gateways', [self::class, 'filter_gateways'], 10, 2);
	}

	public static function register_gateway($gateways) {
		foreach (self::GATEWAYS as $key => $label) {
			$gateways[$key] = [
				'admin_label'    => esc_html__($label, 'bayarcash-givewp'),
				'checkout_label' => esc_html__($label, 'bayarcash-givewp'),
			];
		}
		return $gateways;
	}

	public static function register_gateway_section($sections) {
		$sections['bayarcash-settings'] = esc_html__('Bayarcash', 'bayarcash-givewp');
		return $sections;
	}

	public static function remove_cc_form(): void {
		remove_action('give_cc_form', 'give_get_cc_form');
	}

	public static function filter_gateways($gateway_list, $form_id) {
		// Check if the form is recurring
		$is_recurring = self::is_recurring_form($form_id);

		foreach (self::CHANNELS as $channel) {
			$customize_option = give_get_meta($form_id, "{$channel}_givewp_customize_donations", true);
			$gateway_key = $channel === 'bayarcash' ? 'bayarcash' : "bayarcash_" . str_replace('bc-', '', $channel);

			if ($customize_option === 'disabled' || ($is_recurring && $gateway_key !== 'bayarcash')) {
				unset($gateway_list[$gateway_key]);
			}
		}

		return $gateway_list;
	}

	private static function is_recurring_form($form_id): bool {
		// Check if Give Recurring add-on is active
		if (!class_exists('Give_Recurring')) {
			return false;
		}

		// Check if the current form is set up for recurring donations
		$recurring_option = give_get_meta($form_id, '_give_recurring', true);
		return $recurring_option === 'yes_donor' || $recurring_option === 'yes_admin';
	}
}