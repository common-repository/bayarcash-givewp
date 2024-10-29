<?php
namespace BayarCash\GiveWP;

defined('ABSPATH') || exit;

class PaymentsQuery {
	private $args = [];

	public function __construct($args = []) {
		$defaults = [
			'output'    => 'payments',
			'number'    => -1,
			'status'    => ['pending', 'abandoned'],
			'orderby'   => 'ID',
			'order'     => 'DESC',
			'gateway'   => [
				'bayarcash',
				'bayarcash_duitnow',
				'bayarcash_linecredit',
				'bayarcash_duitnowqr',
				'bayarcash_duitnowshopee',
				'bayarcash_duitnowboost',
				'bayarcash_duitnowqris',
				'bayarcash_duitnowqriswallet'
			],
			'meta_query' => [
				[
					'key'     => 'bayarcash_transaction_id',
					'compare' => 'EXISTS',
				]
			]
		];

		$this->args = wp_parse_args($args, $defaults);
	}

	public function get_payments(): array {
		//error_log('BayarCash GiveWP: Query args: ' . print_r($this->args, true));

		$payments = new \Give_Payments_Query($this->args);
		$results = $payments->get_payments();

		error_log('Bayarcash GiveWP: Number of payments found: ' . count($results));

		return $results;
	}

	public function get_payments_count(): array {
		$this->args['count'] = true;
		$payments = new \Give_Payments_Query($this->args);
		return $payments->get_payments();
	}
}