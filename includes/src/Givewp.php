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

use Webimpian\BayarcashSdk\Bayarcash as BayarcashSdk;
use Give\Helpers\Form\Utils as FormUtils;

\defined('ABSPATH') || exit;

final class Givewp
{
    private Bayarcash $pt;

	private BayarcashSdk $bayarcashSdk;
	private BayarcashCallbacks $callbacks;
	private FormSetups $form_setups;

	public function __construct(Bayarcash $pt)
    {
        $this->pt = $pt;
	    $this->form_setups = new FormSetups($pt);
	    $endpoint_tokens = $this->endpoint_tokens(0);

	    $this->callbacks = new BayarcashCallbacks($pt, $endpoint_tokens, $this);

	    // Initialize BayarcashSdk with a default token
	    $this->bayarcashSdk = new BayarcashSdk($endpoint_tokens->bayarcash['portal_token']);
	    if (give_is_test_mode()) {
		    $this->bayarcashSdk->useSandbox();
	    }
    }

    public function init(): void {
	    BayarcashGatewaySetup::init();
	    BayarcashAdminSettings::init();
	    $this->form_setups->init();
        $this->payment_setups();
        $this->subscription_setups();
        $this->pt->register_cronjob();

    }

	private function payment_setups(): void {
	    // Set up hooks for all Bayarcash gateways
		$bayarcash_gateways = [
			'bayarcash',
			'bayarcash_duitnow',
			'bayarcash_linecredit',
			'bayarcash_duitnowqr',
			'bayarcash_duitnowshopee',
			'bayarcash_duitnowboost',
			'bayarcash_duitnowqris',
			'bayarcash_duitnowqriswallet'
		];

	    foreach ($bayarcash_gateways as $gateway) {
		    add_action("give_gateway_{$gateway}", [$this, 'process_payment']);
		    add_filter("give_payment_confirm_{$gateway}", [$this, 'give_bayarcash_success_page_content']);
	    }
	    add_action('init', [$this->callbacks, 'process_callback'], \PHP_INT_MAX);

        // GiveWP uses iframe and js to display [give receipt].
        // Didn't find any suitable hook, for time being this is a little hack to achieve
        // the objective of putting a note on the receipt.
        add_filter('wp', function () {
            if (!empty($_GET['payment-id']) && !empty($_GET['bc-givewp-initial']) && !empty($_GET['giveDonationFormInIframe'])) {
                ob_start(function ($content) {
                    $code = 'jQuery(document).ready(function(){setTimeout(function() { ';
                    $code .= "jQuery('div.receipt-sections, div.details.donor-section').css('padding-top', '20px').prepend('";
                    $code .= '<div class="details" style="line-height: 1.5; font-weight:500;font-size:17px; border:1px solid #ffeeba;padding:25px;margin-top:15px;border-radius:5px;color:#856404;background-color:#fff3cd;"><p class="instruction" style="margin-bottom:18px;">';
                    $code .= esc_html__('Please note that RM1.00 is deducted from your bank account for bank verification fee. This fee is non-refundable.', 'bayarcash-givewp');
                    $code .= '</p><p>';
                    $code .= esc_html__('For monthly recurring, deduction will happen between 3rd-5th every month. In case of failure, second attempt will be made between 25th-28th. For weekly recurring, deduction will happen every Friday.', 'bayarcash-givewp');
                    $code .= '</p></div>';
                    $code .= "'); }, 1000);});";

	                return str_replace('</body>', '<script>' . $code . '</script></body>', $content);
                });
            }
        }, \PHP_INT_MAX);
    }

    private function subscription_setups(): void {
        add_action('give_checkout_error_checks', function ($valid_data) {
            if (!empty($_POST['give-gateway']) && 'bayarcash' !== $_POST['give-gateway']) {
                return;
            }

            if (\function_exists('give_is_form_recurring') && !is_user_logged_in() && !empty($_POST['give-form-id'])) {
                $form_id = absint($_POST['give-form-id']);
                if (give_is_form_recurring($form_id) && !give_is_setting_enabled(give_get_option('email_access'))) {
                    if (!empty($_POST['_give_is_donation_recurring']) && empty($_POST['give_create_account'])) {
                        give_set_error('recurring_create_account', __('Please tick the create account button if you want to create a subscription donation', 'bayarcash-givewp'));
                    }
                }
            }
        }, 0, 1);

        add_filter('give_subscription_can_cancel', [$this, 'can_cancel'], 10, 2);
        add_filter('give_subscription_can_cancel_bayarcash_subscription', [$this, 'can_cancel'], 10, 2);

        add_filter('give_subscription_can_update', [$this, 'can_update'], 10, 2);
        add_filter('give_subscription_can_update_subscription', [$this, 'can_update_subscription'], 10, 2);
    }

	public function endpoint_tokens($form_id): object {
		$channels = [
			'bayarcash' => ['key' => 'bayarcash', 'payment_channel' => 1],
			'bayarcash_duitnow' => ['key' => 'bc-duitnow', 'payment_channel' => 5],
			'bayarcash_linecredit' => ['key' => 'bc-linecredit', 'payment_channel' => 4],
			'bayarcash_duitnowqr' => ['key' => 'bc-duitnowqr', 'payment_channel' => 6],
			'bayarcash_duitnowshopee' => ['key' => 'bc-duitnowshopee', 'payment_channel' => 7],
			'bayarcash_duitnowboost' => ['key' => 'bc-duitnowboost', 'payment_channel' => 8],
			'bayarcash_duitnowqris' => ['key' => 'bc-duitnowqris', 'payment_channel' => 9],
			'bayarcash_duitnowqriswallet' => ['key' => 'bc-duitnowqriswallet', 'payment_channel' => 10],
		];

		$tokens = [];
		foreach ($channels as $payment_mode => $channel) {
			$setting_key = $channel['key'];
			$customize_donations = give_get_meta($form_id, "{$setting_key}_givewp_customize_donations", true) === 'enabled';

			if ($customize_donations) {
				$portal_token = give_get_meta($form_id, "{$setting_key}_portal_token", true);
				$portal_key = give_get_meta($form_id, "{$setting_key}_portal_key", true);
				$secret_key = give_get_meta($form_id, "{$setting_key}_secret_key", true);
			} else {
				$portal_token = give_get_option("{$setting_key}_portal_token");
				$portal_key = give_get_option("{$setting_key}_portal_key");
				$secret_key = give_get_option("{$setting_key}_secret_key");
			}

			$tokens[$payment_mode] = [
				'portal_token' => $portal_token,
				'portal_key' => $portal_key,
				'secret_key' => $secret_key,
				'payment_channel' => $channel['payment_channel'],
			];
		}

		$endpoint = give_is_test_mode() ? $this->pt->endpoint_sandbox : $this->pt->endpoint_public;

		return (object) array_merge(
			$tokens,
			[
				'directdebit_request_url' => $endpoint.'/mandates/enrollment/confirmation',
				'directdebit_cancel_url'  => $endpoint.'/mandates/termination',
			]
		);
	}

	private function create_payment( $purchase_data )
    {
	    $insert_payment_data = [
            'price'           => $purchase_data['price'],
            'give_form_title' => $purchase_data['form_title'],
            'give_form_id'    => $purchase_data['form_id'],
            'give_price_id'   => $purchase_data['price_id'],
            'date'            => $purchase_data['date'],
            'user_email'      => $purchase_data['user_email'],
            'purchase_key'    => $purchase_data['purchase_key'],
            'currency'        => give_get_currency($purchase_data['form_id'], $purchase_data),
            'user_info'       => $purchase_data['user_info'],
            'status'          => 'pending',
            'gateway'         => $purchase_data['gateway'],
        ];

        $insert_payment_data = apply_filters('give_create_payment', $insert_payment_data);

	    $payment_id = give_insert_payment($insert_payment_data);

	    if ($payment_id) {
		    error_log('Payment created successfully. ID: ' . $payment_id);
	    } else {
		    error_log('Failed to create payment');
	    }

	    return $payment_id;
    }

    private function is_recurring($form_id): bool {
        return \function_exists('Give_Recurring') && Give_Recurring()->is_recurring($form_id);
    }

	public function process_payment($data)
	{
		error_log('Starting process_payment');
		error_log('Payment mode: ' . ( $_POST['payment-mode'] ?? 'not set' ));

		give_validate_nonce($data['gateway_nonce'], 'give-gateway');

		$payment_mode = $_POST['payment-mode'] ?? 'bayarcash';

		give_clear_errors();
		if (give_get_errors()) {
			give_send_back_to_checkout('?payment-mode=' . $payment_mode);
			return;
		}

		unset($data['card_info']);

		$post_data = $data['post_data'];
		$payment_mode = $_POST['payment-mode'] ?? 'bayarcash';
		$is_recurring = !empty($post_data['_give_is_donation_recurring']);

		// Validation checks
		if ($is_recurring) {
			foreach ([
				'bayarcash-phone' => [
					'error_id' => 'invalid_bayarcash-phone',
					'error_message' => esc_html__('Please enter mobile number.', 'bayarcash-givewp'),
				],
				'bayarcash-identification-id' => [
					'error_id' => 'invalid_bayarcash_identification_id',
					'error_message' => esc_html__('Please enter identification number.', 'bayarcash-givewp'),
				],
				'bayarcash-identification-type' => [
					'error_id' => 'invalid_bayarcash_identification_type',
					'error_message' => esc_html__('Please select identification type.', 'bayarcash-givewp'),
				],
			] as $field_name => $value) {
				if (empty($post_data[$field_name])) {
					$post_data[$field_name] = '';
					give_set_error($value['error_id'], $value['error_message']);
				}
			}

			if (!empty($post_data['give_email']) && \strlen($post_data['give_email']) > 27) {
				give_set_error('invalid_bayarcash_email_maxlength', esc_html__('Email length reaches a limit of 27 characters.', 'bayarcash-givewp'));
			}

			if (give_get_errors()) {
				$return_query = [
					'payment-mode'                  => 'bayarcash',
					'bc-select-recurring'           => 1,
					'bayarcash-phone'               => $post_data['bayarcash-phone'],
					'bayarcash-identification-type' => $post_data['bayarcash-identification-type'],
					'bayarcash-identification-id'   => $post_data['bayarcash-identification-id'],
				];

				give_send_back_to_checkout('?' . http_build_query($return_query, '', '&'));
				return;
			}
		} else {
			if (!empty($post_data['give_email']) && \strlen($post_data['give_email']) > 50) {
				give_set_error('invalid_bayarcash_email_maxlength', esc_html__('Email length reaches a limit of 50 characters.', 'bayarcash-givewp'));
				$return_query = [
					'payment-mode' => $payment_mode,
				];

				if (!empty($post_data['bayarcash-phone'])) {
					$return_query['bayarcash-phone'] = $post_data['bayarcash-phone'];
				}

				give_send_back_to_checkout('?' . http_build_query($return_query, '', '&'));
				return;
			}
		}

		$form_id = $post_data['give-form-id'];
		$form_title = $post_data['give-form-title'];
		$price_id = !empty($post_data['give-price-id']) ? $post_data['give-price-id'] : '';

		$buyer_id = !empty($data['user_info']['id']) ? $data['user_info']['id'] : null;
		$buyer_name = '';
		$buyer_email = !empty($data['user_email']) ? $data['user_email'] : '';
		$buyer_phone = !empty($post_data['bayarcash-phone']) ? $post_data['bayarcash-phone'] : '';

		if (!empty($data['user_info'])) {
			if (empty($buyer_email)) {
				$buyer_email = $data['user_info']['email'];
			}
			$buyer_name = trim($data['user_info']['title'] . ' ' . $data['user_info']['first_name'] . ' ' . $data['user_info']['last_name']);
		}

		if (empty($buyer_name)) {
			$buyer_name = trim($post_data['give_title'] . ' ' . $post_data['give_first'] . ' ' . $post_data['give_last']);
		}

		if (empty($buyer_email)) {
			$buyer_email = $post_data['give_email'];
		}

		$data['user_email'] = $buyer_email;
		$data['price_id'] = $price_id;
		$data['form_id'] = $form_id;
		$data['form_title'] = $form_title;
		$payment_id = $this->create_payment( $data );

		if (empty($payment_id)) {
			$error = sprintf(esc_html__('Payment creation failed before sending donor to Bayarcash. Payment data: %s', 'bayarcash-givewp'), json_encode($data));
			error_log(__('Payment Error', 'bayarcash-givewp'), $error, $payment_id);
			give_send_back_to_checkout();
			return;
		}

		$is_anonymous = isset($post_data['give_anonymous_donation']) && absint($post_data['give_anonymous_donation']);
		update_post_meta($payment_id, '_give_anonymous_donation', (int) $is_anonymous);

		$return_url = site_url('/give-api/bayarcash_gateway/');

		$tokens = $this->endpoint_tokens($form_id);

		if (!isset($tokens->$payment_mode)) {
			give_set_error('invalid_payment_mode', __('Invalid payment mode selected.', 'bayarcash-givewp'));
			give_send_back_to_checkout();
			return;
		}

		$channel_tokens = $tokens->$payment_mode;

		// Update BayarcashSdk with the correct token for the selected payment mode
		$this->bayarcashSdk->setToken($channel_tokens['portal_token']);

		$amount = $data['price'];
		$args = [
			'portal_key' => $channel_tokens['portal_key'],
			'order_number' => $payment_id,
			'amount' => $amount,
			'payer_name' => $buyer_name,
			'payer_email' => $buyer_email,
			'payer_telephone_number' => $buyer_phone,
			'return_url' => $return_url . '?bc-givewp-return=' . $this->pt->set_return_token($payment_id, $payment_id, $payment_mode),
			'payment_channel' => $channel_tokens['payment_channel'],
		];

		// Create checksum
		$args['checksum'] = $this->bayarcashSdk->createPaymentIntenChecksumValue($channel_tokens['secret_key'], $args);

		if ($is_recurring) {
//			$args = [
//				"portal_key" => $channel_tokens['portal_key'],
//				"order_number" => $payment_id,
//				"amount" => number_format($amount, 2, '.', ''),
//				"payer_name" => $buyer_name,
//				"payer_email" => $buyer_email,
//				"payer_telephone_number" => $buyer_phone,
//				"payer_id_type" => !empty($post_data['bayarcash-identification-type']) ? $post_data['bayarcash-identification-type'] : '',
//				"payer_id" => !empty($post_data['bayarcash-identification-id']) ? $post_data['bayarcash-identification-id'] : '',
//				"frequency_mode" => 'week' === $data['period'] ? 'WK' : 'MT',
//				"application_reason" => 'Enrollment of '.$payment_id,
//				"effective_date" => '',
//				"expiry_date" => '',
//				"metadata" => json_encode(["form_id" => $form_id, "price_id" => $price_id]),
//				"return_url" => $return_url.'?bc-givewp-return='.$this->pt->set_return_token($payment_id, $payment_id, 'directdebit'),
//				"success_url" => $return_url.'?bc-givewp-success='.$this->pt->set_return_token($payment_id, $payment_id, 'directdebit'),
//				"failed_url" => $return_url.'?bc-givewp-failed='.$this->pt->set_return_token($payment_id, 'bc-givewp-failed', 'directdebit'),
////			];
			$args = [
				'order_no'        => $payment_id,
				'buyer_name'      => $buyer_name,
				'buyer_email'     => $buyer_email,
				'buyer_tel_no'    => $buyer_phone,
				"portal_key"      => $channel_tokens['portal_key'],
			];
			$args['buyer_id']                 = !empty($post_data['bayarcash-identification-id']) ? $post_data['bayarcash-identification-id'] : '';
			$args['buyer_id_type']            = !empty($post_data['bayarcash-identification-type']) ? $post_data['bayarcash-identification-type'] : '';
			$args['order_amount']             = number_format($amount, 2, '.', '');
			$args['order_description']        = 'Enrollment of '.$payment_id;
			$args['mandate_frequency_mode']   = 'week' === $data['period'] ? 'WK' : 'MT';
			$args['mandate_max_frequency']    = '999';
			$args['mandate_application_type'] = '01';
			$args['mandate_effective_date']   = '';
			$args['mandate_expiry_date']      = '';
			$args['return_url']               = $return_url.'?bc-givewp-return='.$this->pt->set_return_token($payment_id, $payment_id, 'directdebit');
			$args['success_url']              = $return_url.'?bc-givewp-success='.$this->pt->set_return_token($payment_id, $payment_id, 'directdebit');
			$args['failed_url']               = $return_url.'?bc-givewp-failed='.$this->pt->set_return_token($payment_id, 'bc-givewp-failed', 'directdebit');

			$endpoint_url = $tokens->directdebit_request_url;

			//$args['checksum'] = $this->bayarcashSdk->createFpxDIrectDebitEnrolmentChecksumValue($channel_tokens['secret_key'], $args);

			$signup_data = [
				'name'             => $post_data['give-form-title'],
				'id'               => $form_id,
				'form_id'          => $form_id,
				'price_id'         => $price_id,
				'initial_amount'   => $amount,
				'recurring_amount' => $amount,
				'period'           => $data['period'], // Make sure this is set
				'frequency'        => !empty($data['frequency']) ? (int) $data['frequency'] : 1,
				'times'            => !empty($data['times']) ? (int) $data['times'] : 0,
				'profile_id'       => $payment_id,
				'transaction_id'   => '',
				'payment_id'       => '',
				'subscriber_id'    => '',
				'user_id'          => $buyer_id,
			];

			set_transient('bayarcash_givewp_directdebit_'.$payment_id, $signup_data, HOUR_IN_SECONDS);
			error_log('Transient data set for payment ID ' . $payment_id . ': ' . var_export($signup_data, true));
			//$response = $this->bayarcashSdk->createFpxDirectDebitEnrollment($args);

        $payment_page = $this->pt->url.'includes/admin/bayarcash-payment.php';
        $query_args   = ['p' => $args, 'u' => $endpoint_url];
        $payment_url  = add_query_arg(filter_var($query_args, \FILTER_DEFAULT, \FILTER_FORCE_ARRAY), filter_var($payment_page, \FILTER_VALIDATE_URL));
        $output       = wp_get_inline_script_tag('window.onload = function(){window.parent.location = "'.$payment_url.'";}');
        exit(filter_var($output, \FILTER_DEFAULT, \FILTER_REQUIRE_SCALAR));

		} else {
			$response = $this->bayarcashSdk->createPaymentIntent($args);
		}

		if (empty($response->url)) {
			error_log(__('Payment Error', 'bayarcash-givewp'), 'Failed to create payment intent with Bayarcash SDK', $payment_id);
			give_send_back_to_checkout();
			return;
		}

		// Check if it's a legacy form using the provided method
		$is_legacy_form = FormUtils::isLegacyForm($form_id);

		if ($is_legacy_form) {
			// Display loader for legacy forms
			?>
			<!DOCTYPE html>
			<html>
			<head>
				<title><?php echo esc_html__('Processing Payment', 'bayarcash-givewp'); ?></title>
				<style>
                    body {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                        font-family: Arial, sans-serif;
                        background-color: #f0f0f0;
                    }
                    .loader-container {
                        text-align: center;
                    }
                    .spinner {
                        width: 50px;
                        height: 50px;
                        animation: spin 1s linear infinite;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    .message {
                        margin-top: 20px;
                        font-size: 18px;
                        color: #333;
                    }
                    .fallback-message {
                        display: none;
                        margin-top: 20px;
                        font-size: 14px;
                        color: #666;
                    }
				</style>
			</head>
			<body>
			<div class="loader-container">
				<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAMAAABEpIrGAAAAXVBMVEVHcEwjOIQjOYQjOYQjOYQjOYQjOYQjSIcklNYklNYklNYjeb4kkdMklNYklNYklNYjN4Mkl9kjWZ8jOYQjKHkklNYkk9Ukk9UjOYQjOIMjY6gkfcAklNYklNYjOYRzxrQZAAAAH3RSTlMA3mj/hR0yBRk9KQiW/+xn+v//wUHc97Kh8P//ylJTk+NkDgAAANxJREFUeAHV0QWOxDAQBMAJmCHM8P9nmm6iZeFRiQLtNsGfkHk5fFB45T8JEEoZIC6kug+URZC2y7UJbIWBWxmByiArMXCj1ubSGPEcaBtzi1+Brickr4vCBIPgvBqNN2FghkAta5O+BkOsSIEFkn7zgRESxT11f1DMePrFQeU4aHwRWG4bXgW6cD63a7BwL79ZRBZ2gRW7tXbkvraO+yRK9dnXOUxSKRF3uWOFV8egrwjG0UQSgrm4MZkbeKFlgWoK2lwOQLQrgiWuVaaSUSu4weh5kutFHIeEH+MAZaoPYZ1M9b0AAAAASUVORK5CYII=" alt="Loading" class="spinner">
				<noscript>
					<p><?php echo esc_html__('Processing payment...', 'bayarcash-givewp'); ?></p>
				</noscript>
				<p class="message"><?php echo esc_html__('Please wait, processing your payment...', 'bayarcash-givewp'); ?></p>
				<p class="fallback-message">
					<?php echo esc_html__('If you are not redirected automatically, please click ', 'bayarcash-givewp'); ?>
					<a href="<?php echo esc_url($response->url); ?>"><?php echo esc_html__('here', 'bayarcash-givewp'); ?></a>.
				</p>
			</div>
			<script>
                document.querySelector('.fallback-message').style.display = 'block';
                setTimeout(function() {
                    window.location.href = <?php echo json_encode($response->url); ?>;
                }, 2000); // Redirect after 2 seconds
			</script>
			</body>
			</html>
			<?php
			exit;
		} else {
			// For non-legacy forms, redirect immediately
			wp_redirect($response->url);
			exit;}
	}


    public function give_bayarcash_success_page_content($content)
    {
        $payment_id = isset($_GET['payment-id']) ? sanitize_text_field($_GET['payment-id']) : false;
        if (!$payment_id && !give_get_purchase_session()) {
            return $content;
        }

        $payment_id = absint($payment_id);

        if (!$payment_id) {
            $session    = give_get_purchase_session();
            $payment_id = give_get_donation_id_by_key($session['purchase_key']);
        }

        $payment = get_post($payment_id);
        if ($payment && 'pending' === $payment->post_status) {
            ob_start();
            give_get_template_part('payment', 'processing');
            $content = ob_get_clean();
        }

        return $content;
    }

    public function can_cancel($ret, $subscription_pt)
    {
        if ('bayarcash' === $subscription_pt->gateway && 'active' === $subscription_pt->status) {
            $ret = true;
        }

        return $ret;
    }

    public function can_sync($ret, $subscription): bool {
        return false;
    }

    public function can_update($ret, $subscription): bool {
        return false;
    }

    public function can_update_subscription($ret, $subscription): bool {
        return false;
    }

    public function get_interval($period, $frequency)
    {
        $interval = $period;

	    if ( $period == 'quarter' ) {
		    $interval = 'month';
	    }

        return $interval;
    }

    public function get_interval_count($period, $frequency)
    {
        $interval_count = $frequency;

	    if ( $period == 'quarter' ) {
		    $interval_count = 3 * $frequency;
	    }

        return $interval_count;
    }
}
