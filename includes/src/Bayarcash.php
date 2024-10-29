<?php
/**
 * Bayarcash GiveWP.
 *
 * @author  Bayarcash
 * @license GPLv3
 * @see     https://bayarcash.com
 */

namespace BayarCash\GiveWP;

use Nawawi\Utils\Base64Encryption;

defined('ABSPATH') || exit;

final class Bayarcash
{
	public string $file;
	public string $slug;
	public string $hook;
	public string $path;
	public string $page;
	public string $url;
	public string $endpoint_public;
	public string $endpoint_sandbox;

	private DataRequest $data_request;
	private DataStore $data_store;
	private Base64Encryption $data_enc;

	public function __construct()
	{
		$this->initialize_properties();
		$this->register_init();
	}

	private function initialize_properties(): void {
		$this->slug = 'bayarcash-givewp';
		$this->file = BAYARCASH_GIVEWP_FILE;
		$this->hook = plugin_basename($this->file);
		$this->path = realpath(plugin_dir_path($this->file));
		$this->url  = trailingslashit(plugin_dir_url($this->file));
	}

	public function register(): void {
		$this->register_locale();
		$this->register_admin_hooks();
		$this->register_addon_hooks();
		$this->register_cronjob();
		$this->register_plugin_hooks();
	}

	public function data_request(): DataRequest {
		return $this->data_request ?? $this->data_request = new DataRequest($this);
	}

	public function data_store(): DataStore {
		return $this->data_store ?? $this->data_store = new DataStore();
	}

	public function data_enc(): Base64Encryption {
		return $this->data_enc ?? $this->data_enc = new Base64Encryption();
	}

	public function set_return_token($data, $key, $type = 'fpx'): float|bool|int|string {
		$str = $data . '|' . substr(md5($data), 0, 12) . '|' . $type;
		return $this->data_enc()->encrypt($str, $key);
	}

	public function get_return_token($data, $key, $type): object|bool {
		$str = $this->data_enc()->decrypt($data, $key);
		if ($str === $data || ! str_contains( $str, '|' . $type ) ) {
			return false;
		}

		list($data, $data_id, $token_type) = explode('|', $str);

		return ($token_type === $type) ? (object) [
			'data'    => $data,
			'data_id' => $data_id,
			'type'    => $token_type,
		] : false;
	}

	private function register_locale(): void {
		add_action('plugins_loaded', [$this, 'load_plugin_textdomain'], 0);
	}

	public function load_plugin_textdomain(): void {
		load_plugin_textdomain('bayarcash-givewp', false, $this->path . '/languages/');
	}

	public function register_admin_hooks(): void {
		add_action('plugins_loaded', [$this, 'admin_notices']);
		add_filter("plugin_action_links_{$this->hook}", [$this, 'add_settings_link']);
		add_action('wp_ajax_get_bayarcash_settings', [$this, 'ajax_get_bayarcash_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
	}

	public function admin_notices(): void {
		if (current_user_can(apply_filters('capability', 'manage_options'))) {
			add_action('all_admin_notices', [$this, 'callback_compatibility'], PHP_INT_MAX);
		}
	}

	public function add_settings_link($links)
	{
		$settings_link = sprintf('<a href="%s">%s</a>', admin_url($this->page), esc_html__('Settings', 'bayarcash-givewp'));
		array_unshift($links, $settings_link);
		return $links;
	}

	public function enqueue_admin_scripts($hook): void {
		if (!$this->is_givewp_activated()) {
			return;
		}

		$this->enqueue_third_party_scripts();
		$this->enqueue_custom_scripts_and_styles();
		$this->localize_admin_script();
	}

	public function enqueue_frontend_scripts(): void {
		if (!$this->is_givewp_activated()) {
			return;
		}

		$this->enqueue_third_party_scripts();
	}

	private function enqueue_third_party_scripts(): void {
		wp_enqueue_script('vuejs', $this->url . 'includes/admin/js/vuejs.js', [], '3.4.33', false);
		wp_enqueue_script('axios', $this->url . 'includes/admin/js/axios.min.js', [], '0.21.1', true);
		wp_enqueue_script('lodash', $this->url . 'includes/admin/js/lodash.min.js', [], '0.21.1', true);
	}

	private function enqueue_custom_scripts_and_styles(): void {
		$version = $this->get_asset_version();

		// Scripts
		wp_enqueue_script("{$this->slug}-script", $this->url . 'includes/admin/js/bayarcash-script.js', ['jquery'], $version, false);
		wp_enqueue_script('bayarcash-admin-js', $this->url . 'includes/admin/js/bayarcash-admin.js', ['jquery'], '1.0.0', true);

		// Styles
		wp_enqueue_style("{$this->slug}-css", $this->url . 'includes/admin/css/bayarcash-style.css', null, $version);
		wp_enqueue_style('bayarcash-admin-css', $this->url . 'includes/admin/css/bayarcash-admin.css', [], '1.0.0');
	}

	private function localize_admin_script(): void {
		wp_localize_script('bayarcash-admin-js', 'bayarcashAdminData', [
			'nonce' => wp_create_nonce('bayarcash_admin_nonce'),
			'ajaxurl' => admin_url('admin-ajax.php'),
			'endpoint_public' => $this->endpoint_public,
			'endpoint_sandbox' => $this->endpoint_sandbox,
			'is_test_mode' => give_is_test_mode(),
		]);
	}

	private function get_asset_version(): string {
		$is_debug = defined('WP_DEBUG') && WP_DEBUG;
		$version = $this->data_request()->get_plugin_meta()['Version'];
		return str_replace('.', '', $version) . 'ac' . ($is_debug ? date('his') : date('yd'));
	}

	public function ajax_get_bayarcash_settings(): void {
		check_ajax_referer('bayarcash_admin_nonce', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('You do not have permission to perform this action');
			return;
		}

		$channel = isset($_POST['channel']) ? sanitize_text_field($_POST['channel']) : '';
		$form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
		$is_customized = isset($_POST['is_customized']) ? filter_var($_POST['is_customized'], FILTER_VALIDATE_BOOLEAN) : false;

		if (empty($channel)) {
			wp_send_json_error('Channel not specified');
			return;
		}

		$settings = $this->get_bayarcash_settings($channel, $form_id, $is_customized);
		wp_send_json_success($settings);
	}

	private function get_bayarcash_settings($channel, $form_id, $is_customized): array {
		$settings = [];

		if ($form_id && $is_customized) {
			$settings = [
				'portal_token' => give_get_meta($form_id, "{$channel}_portal_token", true),
				'portal_key' => give_get_meta($form_id, "{$channel}_portal_key", true),
				'secret_key' => give_get_meta($form_id, "{$channel}_secret_key", true),
			];
		}

		$settings['portal_token'] = $settings['portal_token'] ?: give_get_option("{$channel}_portal_token");
		$settings['portal_key'] = $settings['portal_key'] ?: give_get_option("{$channel}_portal_key");
		$settings['secret_key'] = $settings['secret_key'] ?: give_get_option("{$channel}_secret_key");

		$settings['endpoint'] = $this->get_current_endpoint();
		$settings['is_test_mode'] = give_is_test_mode();

		return $settings;
	}

	public function register_addon_hooks(): void {
		add_action('init', [$this, 'initialize_givewp']);
		add_filter('give_recurring_available_gateways', [$this, 'add_recurring_gateway']);
		add_filter('plugin_row_meta', [$this, 'add_plugin_row_meta'], 10, 2);
	}

	public function initialize_givewp(): void {
		if ($this->is_givewp_activated()) {
			(new Givewp($this))->init();
		}
	}

	public function add_recurring_gateway($gateways)
	{
		$gateways['bayarcash'] = 'BayarCash\\GiveWP\\GivewpRecurring';
		return $gateways;
	}

	public function add_plugin_row_meta($links, $file)
	{
		if ($file == $this->hook) {
			$row_meta = [
				'docs' => '<a href="' . esc_url('https://docs.bayarcash.com/') . '" target="_blank">Docs</a>',
				'api_docs' => '<a href="' . esc_url('https://api.webimpian.support/bayarcash') . '" target="_blank">API docs</a>',
				'register_account' => '<a href="' . esc_url('https://bayarcash.com/register/') . '" target="_blank">Register Account</a>',
			];
			return array_merge($links, $row_meta);
		}
		return $links;
	}

	public function is_givewp_activated(): bool {
		return class_exists('Give', false) && function_exists('give');
	}

	public function callback_compatibility(): void {
		if (!$this->is_givewp_activated()) {
			$html = '<div id="bayarcash-notice" class="notice notice-error is-dismissible">';
			$html .= '<p>' . esc_html__('Bayarcash requires GiveWP plugin. Please install and activate.', 'bayarcash-givewp') . '</p>';
			$html .= '</div>';
			echo wp_kses_post($html);
		}
	}

	private function register_init(): void {
		$this->endpoint_public  = 'https://console.bayar.cash';
		$this->endpoint_sandbox = 'https://console.bayarcash-sandbox.com';
		$this->page = 'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=bayarcash-settings';
	}

	public function get_current_endpoint()
	{
		return give_is_test_mode() ? $this->endpoint_sandbox : $this->endpoint_public;
	}

	public function register_plugin_hooks(): void {
		register_activation_hook($this->file, [$this, 'activate']);
		register_deactivation_hook($this->file, [$this, 'deactivate']);
		register_uninstall_hook($this->file, [__CLASS__, 'uninstall']);
	}

	public function register_cronjob(): void {
		(new CronEvent($this))->register();
	}

	public function unregister_cronjob(): void {
		(new CronEvent($this))->unregister();
	}

	public function activate(): void {
		$this->unregister_cronjob();
	}

	public function deactivate(): void {
		$this->unregister_cronjob();
	}

	public static function uninstall(): void {
		(new self())->unregister_cronjob();
	}
}