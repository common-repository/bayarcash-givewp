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

class DataRequest
{
    private $pt;
    private $last_error_message = null;
    private $last_headers       = null;

    public function __construct(Bayarcash $pt)
    {
        $this->pt = $pt;
    }

    public function get_plugin_meta()
    {
        static $meta = null;

        if (empty($meta)) {
            $meta = get_plugin_data($this->pt->file, false);
        }

        return $meta;
    }

    public function get_last_error_message()
    {
        return $this->last_error_message;
    }

    public function get_last_headers()
    {
        return $this->last_headers;
    }

	/**
	 * @throws \Exception
	 */
	public function requery($transaction_id, $bearer_token)
	{
		$sandbox_mode = give_is_test_mode();
		$base_url = $sandbox_mode ? 'https://console.bayarcash-sandbox.com' : 'https://console.bayar.cash';
		$url = $base_url . '/api/v2/transactions/' . $transaction_id;

		$this->log_debug('Requerying transaction: ' . $url);

		$args = [
			'method'  => 'GET',
			'headers' => [
				'Accept' => 'application/json',
				'Authorization' => 'Bearer ' . $bearer_token
			]
		];

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) {
			$this->last_error_message = $response->get_error_message();
			$this->log_debug('WordPress HTTP Error: ' . $this->last_error_message);
			throw new \Exception('WordPress HTTP Error: ' . $this->last_error_message);
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			$this->last_error_message = 'HTTP Error: Received response code ' . $response_code;
			$this->log_debug($this->last_error_message);
			throw new \Exception($this->last_error_message);
		}

		$body = wp_remote_retrieve_body($response);
		$decoded_response = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->last_error_message = 'JSON Decoding Error: ' . json_last_error_msg();
			$this->log_debug($this->last_error_message);
			throw new \Exception($this->last_error_message);
		}

		$this->last_headers = wp_remote_retrieve_headers($response);

		return $decoded_response;
	}

	private function log_debug($message)
	{
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('BayarCash Debug: ' . $message);
		}
	}

    public function request_terminate($data, $endpoint_url)
    {
        $args = [
            'body' => $data,
        ];

        return $this->send($args, $endpoint_url);
    }

}
