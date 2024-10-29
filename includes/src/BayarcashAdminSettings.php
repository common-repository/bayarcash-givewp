<?php
namespace BayarCash\GiveWP;

class BayarcashAdminSettings {
	private static array $channels = [
		'bayarcash' => 'Online Banking',
		'bc-duitnow' => 'Online Banking (DuitNow)',
		'bc-linecredit' => 'Credit Card',
		'bc-duitnowqr' => 'DuitNow QR',
		'bc-duitnowshopee' => 'SPayLater',
		'bc-duitnowboost' => 'Boost PayFlex',
		'bc-duitnowqris' => 'Indonesia Online Banking',
		'bc-duitnowqriswallet' => 'Indonesia e-Wallet'
	];

	public static function init() {
		add_filter('give_get_settings_gateways', [self::class, 'register_settings']);
		add_action('give_admin_field_bayarcash_tabs', [self::class, 'render_bayarcash_tabs'], 10, 2);
		add_filter('give_metabox_form_data_settings', [self::class, 'register_metabox_settings']);
	}

	public static function register_settings($settings) {
		if ('bayarcash-settings' !== give_get_current_setting_section()) {
			return $settings;
		}

		$bayarcash_settings = [];

		foreach (self::$channels as $channel => $name) {
			$bayarcash_settings = array_merge(
				$bayarcash_settings,
				[
					["id" => "give_title_bayarcash_{$channel}", "type" => "title"],
					...self::get_channel_fields($channel, true),
					["id" => "give_title_bayarcash_{$channel}", "type" => "sectionend"]
				]
			);
		}

		$bayarcash_settings[] = ['id' => 'bayarcash_settings', 'type' => 'bayarcash_tabs'];

		return array_merge($settings, $bayarcash_settings);
	}

	public static function render_bayarcash_tabs() {
		echo '<div class="bayarcash-tabs-wrapper"><div class="bayarcash-tabs-container"><ul class="bayarcash-tabs">';
		foreach (self::$channels as $tab => $name) {
			echo "<li><a href='#' data-tab='" . esc_attr($tab) . "'>" . esc_html__($name, 'bayarcash-givewp') . "</a></li>";
		}
		echo '</ul><div class="bayarcash-tab-content">';
		foreach (self::$channels as $tab => $name) {
			echo "<div id='bayarcash-" . esc_attr($tab) . "' class='bayarcash-tab-pane'><h2>" . esc_html__($name, 'bayarcash-givewp') . "</h2></div>";
		}
		echo '</div></div></div>';
	}

	public static function register_metabox_settings($settings) {
		$channels = [
			'bayarcash' => 'bayarcash',
			'bc-duitnow' => 'bayarcash_duitnow',
			'bc-linecredit' => 'bayarcash_linecredit',
			'bc-duitnowqr' => 'bayarcash_duitnowqr',
			'bc-duitnowshopee' => 'bayarcash_duitnowshopee',
			'bc-duitnowboost' => 'bayarcash_duitnowboost',
			'bc-duitnowqris' => 'bayarcash_duitnowqris',
			'bc-duitnowqriswallet' => 'bayarcash_duitnowqriswallet'
		];

		foreach ($channels as $channel => $gateway) {
			if (give_is_gateway_active($gateway)) {
				$fields = self::get_channel_fields($channel);
				foreach ($fields as &$field) {
					$field['wrapper_class'] = ($field['wrapper_class'] ?? '') . " bayarcash-{$channel}-field";
				}

				$settings["{$channel}_options"] = [
					'id' => "{$channel}_options",
					'title' => self::get_channel_title($channel),
					'icon-html' => self::get_channel_icon($channel),
					'fields' => $fields,
				];
			}
		}
		return $settings;
	}

	private static function get_channel_fields($channel, $is_global = false): array {
		$prefix = "{$channel}_";
		$common_fields = [
			[
				'name' => esc_html__('Personal Access Token (PAT)', 'bayarcash-givewp'),
				'desc' => sprintf(
					esc_html__('Enter your Personal Access Token (PAT). You can retrieve it from Bayarcash console at %sDashboard > Profile%s.', 'bayarcash-givewp'),
					'<a href="https://console.bayar.cash/profile" target="_blank">',
					'</a>'
				),
				'id'   => "{$prefix}portal_token",
				'type' => 'textarea',
				'after' => "<div class='bayarcash-verify-token' data-channel='" . esc_attr($channel) . "'>
                    <button class='button-primary verify-button'>Verify Token</button>
                    <span class='verify-status'></span>
                </div>",
			],
			[
				'name' => esc_html__('Portal Key', 'bayarcash-givewp'),
				'desc' => esc_html__('Choose the Portal Key for your Bayarcash integration. This key defines which portal will process your transactions.', 'bayarcash-givewp'),
				'id'   => "{$prefix}portal_key",
				'type' => 'select',
				'option' => [],
			],
			[
				'name' => esc_html__('API Secret Key', 'bayarcash-givewp'),
				'desc' => sprintf(
					esc_html__('Enter your secret key. You can retrieve it from Bayarcash console at %sDashboard > Profile%s.', 'bayarcash-givewp'),
					'<a href="https://console.bayar.cash/profile" target="_blank">',
					'</a>'
				),
				'id'   => "{$prefix}secret_key",
				'type' => 'text',
			],
			[
				'name'    => esc_html__('Billing Fields', 'bayarcash-givewp'),
				'desc'    => esc_html__('This option will enable the billing details section at the donation form.', 'bayarcash-givewp'),
				'id'      => "{$prefix}collect_billing",
				'type'    => 'radio_inline',
				'default' => 'disabled',
				'options' => [
					'enabled'  => esc_html__('Enabled', 'bayarcash-givewp'),
					'disabled' => esc_html__('Disabled', 'bayarcash-givewp'),
				],
			],
			[
				'name'    => esc_html__('Enable Phone field', 'bayarcash-givewp'),
				'desc'    => esc_html__('This option will enable the phone field in the non-recurring donation form.', 'bayarcash-givewp'),
				'id'      => "{$prefix}enable_phone_number",
				'type'    => 'radio_inline',
				'default' => 'disabled',
				'options' => [
					'enabled'  => esc_html__('Enabled', 'bayarcash-givewp'),
					'disabled' => esc_html__('Disabled', 'bayarcash-givewp'),
				],
			],
		];

		if (!$is_global) {
			array_unshift($common_fields, [
				'name'    => 'Global Option',
				'desc'    => esc_html__('Do you want to customize the donation instructions for this form?', 'bayarcash-givewp'),
				'id'      => "{$prefix}givewp_customize_donations",
				'type'    => 'radio_inline',
				'default' => 'global',
				'options' => apply_filters('give_forms_content_options_select', [
					'global'   => esc_html__('Global Option', 'bayarcash-givewp'),
					'enabled'  => esc_html__('Customize', 'bayarcash-givewp'),
					'disabled' => esc_html__('Disable', 'bayarcash-givewp'),
				]),
				'wrapper_class' => 'bayarcash_givewp_customize_donations_field',
			]);
		}

		return $common_fields;
	}

	private static function get_channel_icon($channel): string {
		$baseUrl = plugin_dir_url( dirname( __FILE__, 2 ) ) . 'includes/admin/img/';
		$iconFile = 'logo.svg';
		$iconUrl = $baseUrl . $iconFile;
		return "<img src='{$iconUrl}' alt='Payment Icon' style='width: 14%;'>";
	}

	private static function get_channel_title($channel): string {
		$titles = [
			'bayarcash' => 'Online Banking (FPX)',
			'bc-duitnow' => 'Online Banking (DuitNow)',
			'bc-linecredit' => 'Credit Card',
			'bc-duitnowqr' => 'DuitNow QR',
			'bc-duitnowshopee' => 'SPayLater',
			'bc-duitnowboost' => 'Boost PayFlex',
			'bc-duitnowqris' => 'Indonesia Online Banking',
			'bc-duitnowqriswallet' => 'Indonesia e-Wallet'
		];
		return esc_html__($titles[$channel] ?? 'Bayarcash Payment', 'bayarcash-givewp');
	}
}