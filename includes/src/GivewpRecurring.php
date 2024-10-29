<?php
/**
 * Bayarcash GiveWP.
 *
 * @author  Bayarcash
 * @license GPLv3
 *
 * @see    https://bayarcash.com
 */

/**
 * This class will call by the give-recurring plugin.
 * Separated from the BayarCash\GiveWP\Givewp class to avoid error-prone when it is not available.
 */

namespace BayarCash\GiveWP;

\defined('ABSPATH') && class_exists('Give_Recurring_Gateway', false) || exit;

use Exception;
use Give_Recurring_Gateway;
use Give_Subscription;

final class GivewpRecurring extends Give_Recurring_Gateway
{
    /*
     * @see Bayarcash::register_addon_hooks()
     * @see Givewp::subscription_setups()
     * @see Give_Recurring_Gateway_Factory::get_gateway()
     * @see CancelSubscriptionRoute::handleRequest()
     */

    private ?Bayarcash $pt     = null;
    private ?Givewp $givewp = null;

    public function __construct()
    {
        if (null === $this->pt) {
            $this->pt     = new Bayarcash();
            $this->givewp = new Givewp($this->pt);
        }

        add_action('give_cancel_subscription', [$this, 'process_cancellation']);
        add_action('give_recurring_cancel_bayarcash_subscription', [$this, 'cancel'], 10, 2);
    }

    public function process_cancellation($data)
    {
        if (empty($data['sub_id'])) {
            return;
        }

        if (
	        !is_user_logged_in()
	        && ! Give_Recurring()->subscriber_has_email_access()
            && !give_get_purchase_session()
        ) {
            return;
        }

        $data['sub_id'] = absint($data['sub_id']);

        if (!wp_verify_nonce($data['_wpnonce'], "give-recurring-cancel-{$data['sub_id']}")) {
            wp_die(__('Nonce verification failed.', 'bayarcash-givewp'), __('Error', 'bayarcash-givewp'), ['response' => 403]);
        }

        $subscription_pt = new Give_Subscription($data['sub_id']);

        if (!$subscription_pt->can_cancel()) {
            wp_die(__('This subscription cannot be cancelled.', 'bayarcash-givewp'), __('Error', 'bayarcash-givewp'), ['response' => 403]);
        }

        try {
            do_action('give_recurring_cancel_bayarcash_subscription', $subscription_pt, true);

            $subscription_pt->cancel();

            if (is_admin()) {
                wp_redirect(admin_url('edit.php?post_type=give_forms&page=give-subscriptions&give-message=cancelled&id='.$subscription_pt->id));
                exit;
            }
            $args = !give_get_errors() ? ['give-message' => 'cancelled'] : [];
            wp_redirect(
                remove_query_arg(
                    [
                        '_wpnonce',
                        'give_action',
                        'sub_id',
                    ],
                    add_query_arg($args)
                )
            );

            exit;
        } catch (Exception $e) {
            wp_die($e->getMessage(), __('Error', 'bayarcash-givewp'), ['response' => 403]);
        }
    }

    public function cancel($subscription_pt, $valid): bool {
        if (empty($valid)) {
            return false;
        }

        $payment_id   = $subscription_pt->parent_payment_id;
        $form_id      = give_get_payment_form_id($payment_id);
        $tokens       = $this->givewp->endpoint_tokens($form_id);
        $endpoint_url = $tokens->endpoint.'/emandate/termination/'.$payment_id.'/confirmation';

        $args = [
            'portal_key'                => $tokens->portal_key,
            'mandate_application_reson' => 'Terima kasih',
        ];

        $response  = $this->pt->data_request()->request_terminate($args, $endpoint_url);
        $error_msg = '';
        if (empty($response)) {
            $error_msg = $this->pt->data_request()->get_last_error_message();
            if (empty($error_msg)) {
                $error_msg = 'Invalid response';
            }
            debug_log(
                [
                    'caller'  => __METHOD__,
                    'url'     => $endpoint_url,
                    'content' => $error_msg,
                ]
            );
        }

        $get_headers = $this->pt->data_request()->get_last_headers();
        debug_log(
            [
                'caller'  => __METHOD__,
                'headers' => $get_headers,
                'url'     => $endpoint_url,
                'content' => $response,
            ]
        );

        if (false !== strpos($response, '<title>404 Not Found')) {
            $error_msg = '404 Not Found';
        }

        if (!empty($error_msg)) {
            /* translators: %s: error message */
            throw new \RuntimeException(sprintf(__('There was a problem cancelling the subscription, please contact customer support. Bayarcash endpoint returns: %s', 'bayarcash-givewp'), $error_msg));
        }

        return true;
    }
}
