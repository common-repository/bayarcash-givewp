<?php
namespace BayarCash\GiveWP;

use BayarCash\GiveWP\FormFields\BayarcashPaymentFieldManager;
use Exception;
use Give\Donors\Models\Donor;
use Give\Helpers\Form\Utils as FormUtils;
use Give_Subscription;

class FormSetups
{
	private Bayarcash $pt;
	private const GATEWAYS = [
		'bayarcash',
		'bayarcash_duitnow',
		'bayarcash_linecredit',
		'bayarcash_duitnowqr',
		'bayarcash_duitnowshopee',
		'bayarcash_duitnowboost',
		'bayarcash_duitnowqris',
		'bayarcash_duitnowqriswallet'
	];

	public function __construct(Bayarcash $pt)
	{
		$this->pt = $pt;
	}

	public function init()
	{
		$this->addActions();
		$this->addFilters();
	}

	private function addActions()
	{
		foreach (self::GATEWAYS as $gateway) {
			add_action("give_{$gateway}_cc_form", [$this, 'removeCcForm']);
		}
		add_action('give_donation_form_before_email', [$this, 'renderCustomFormFields'], 10, 1);
		add_action('give_insert_payment', [$this, 'saveCustomFields'], 10);
		add_action('give_payment_receipt_after', [$this, 'displaySubscriptionInfo'], 10, 2);
	}

	private function addFilters()
	{
		add_filter('give_donation_form_required_fields', [$this, 'modifyRequiredFields'], 10, 2);
		add_filter('give_export_donors_get_default_columns', [$this, 'addExportColumns']);
		add_filter('give_export_set_donor_data', [$this, 'setExportDonorData'], 10, 2);
	}

	public function removeCcForm($formId)
	{
		$gateway = current_filter();
		$gateway = str_replace('give_', '', str_replace('_cc_form', '', $gateway));
		$settingPrefix = $this->getSettingPrefix($gateway);

		$formCustomize = give_get_meta($formId, "{$settingPrefix}_givewp_customize_donations", true) === 'enabled';
		$collectBilling = $this->shouldCollectBilling($formId, $settingPrefix, $formCustomize);

		if ($collectBilling) {
			give_default_cc_address_fields($formId);
		}

		if (FormUtils::isLegacyForm($formId)) {
			return;
		}

		$this->renderGatewayInfo($gateway);
	}

	private function getSettingPrefix($gateway): string
	{
		return str_replace('bayarcash_', 'bc-', $gateway);
	}

	private function shouldCollectBilling($formId, $settingPrefix, $formCustomize): bool {
		if ($formCustomize) {
			return give_get_meta($formId, "{$settingPrefix}_collect_billing", true) !== 'disabled';
		}
		return give_get_option("{$settingPrefix}_collect_billing") === 'enabled';
	}

	private function renderGatewayInfo($gateway)
	{
		$imageUrl = $this->getGatewayImageUrl($gateway);

		printf(
			'
        <fieldset class="no-fields">
            <div style="display: flex; justify-content: center;">
                <img style="width:70%%;" src="%s" alt="">
            </div>
            <p style="text-align: center;">
                <b>%s</b> %s
            </p>
        </fieldset>
        ',
			esc_url($imageUrl),
			esc_html__('How it works:', 'bayarcash-givewp'),
			esc_html__('You will be redirected to Bayarcash to pay using your selected method. You will then be brought back to this page to view your receipt.', 'bayarcash-givewp')
		);
	}

	private function getGatewayImageUrl($gateway): string {
		$baseUrl = $this->pt->url . 'includes/admin/img/';
		$imageMap = [
			'bayarcash' => 'fpx-online-banking.png',
			'bayarcash_duitnow' => 'dobw.png',
			'bayarcash_linecredit' => 'visa-mastercard.png',
			'bayarcash_duitnowqr' => 'duitnow-qr.png',
			'bayarcash_duitnowshopee' => 'spaylater.png',
			'bayarcash_duitnowboost' => 'boost-payflex.png',
			'bayarcash_duitnowqris' => 'qris-online-banking.png',
			'bayarcash_duitnowqriswallet' => 'qris-ewallet.png'
		];

		return $baseUrl . ($imageMap[$gateway] ?? 'default.png');
	}

	public function renderCustomFormFields($formId): void {
		$isLegacyForm = FormUtils::isLegacyForm($formId);
		$donorData = $this->getDonorData();
		$this->renderNameFields($donorData);
		$this->renderPhoneField($formId, $isLegacyForm, $donorData);
	}

	private function getDonorData(): array {
		$donorData = [
			'first_name' => '',
			'last_name' => '',
			'phone' => ''
		];

		if (is_user_logged_in()) {
			$userId = get_current_user_id();
			$oldDonor = new \Give_Donor($userId, true);
			$donorId = $oldDonor->id;

			if ($donorId) {
				$newDonor = Donor::find($donorId);
				$donorData['first_name'] = $oldDonor->get_first_name();
				$donorData['last_name'] = $oldDonor->get_last_name();

				if ($newDonor) {
					$donorData['phone'] = $newDonor->phone ?: '';
				}
			}
		}

		return $donorData;
	}

	private function renderNameFields($donorData): void {
		?>
        <script>
            jQuery(document).ready(function($) {
                $('#give-first').val('<?php echo esc_js($donorData['first_name']); ?>');x
                $('#give-last').val('<?php echo esc_js($donorData['last_name']); ?>');
            });
        </script>
		<?php
	}

	private function renderPhoneField($formId, $isLegacyForm, $donorData): void {
		$phoneFieldManager = new BayarcashPaymentFieldManager($formId, $isLegacyForm);
		$phoneFieldManager->manage($donorData['phone']);
	}

	public function modifyRequiredFields($requiredFields, $formId)
	{
		// Add any additional required fields here if needed
		return $requiredFields;
	}

	/**
	 * @throws Exception
	 */
	public function saveCustomFields($donationId): void {
		if (!$this->shouldSaveCustomFields()) {
			return;
		}

		$donorId = give_get_payment_donor_id($donationId);
		$formId = give_get_payment_form_id($donationId);
		$paymentMode = !empty($_POST['payment-mode']) ? $_POST['payment-mode'] : '';
		$isRecurring = !empty($_POST['give-recurring-period']) || !empty($_POST['_give_is_donation_recurring']);

		$enablePhone = BayarcashPaymentFieldManager::shouldEnablePhone($formId, $isRecurring);

		if (isset($enablePhone[$paymentMode]) && $enablePhone[$paymentMode]) {
			$this->savePhoneNumber($donationId, $donorId);

			if ($isRecurring) {
				$this->saveIdentificationFields($donationId, $donorId);
				$this->saveBayarcashDirectDebitCycle($donationId);
			}
		}
	}

	private function shouldSaveCustomFields(): bool {
		return give_is_gateway_active('bayarcash') &&
		       !empty($_POST['bayarcash_givewp_form_fields']) &&
		       wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bayarcash_givewp_form_fields'])), 'bayarcash_givewp_nonce_action');
	}

	/**
	 * @throws Exception
	 */
	private function savePhoneNumber($donationId, $donorId): void {
		if (!empty($_POST['bayarcash-phone'])) {
			$phone = sanitize_text_field($_POST['bayarcash-phone']);
			$donor = Donor::find($donorId);
			if ($donor) {
				$donor->phone = $phone;
				$donor->save();
			}
			Give()->payment_meta->update_meta($donationId, '_give_payment_donor_phone', $phone);
		}
	}

	private function saveIdentificationFields($donationId, $donorId): void {
		$fieldsToSave = ['bayarcash-identification-id', 'bayarcash-identification-type'];
		foreach ($fieldsToSave as $field) {
			if (!empty($_POST[$field])) {
				$value = sanitize_text_field($_POST[$field]);
				$existingValues = Give()->donor_meta->get_meta($donorId, $field);
				if (!\in_array($value, $existingValues, true)) {
					Give()->donor_meta->add_meta($donorId, $field, $value);
				}
				Give()->payment_meta->update_meta($donationId, $field, $value);
			}
		}
	}

	private function saveBayarcashDirectDebitCycle($donationId): void {
		if (!empty($_POST['bayarcash-identification-type'])) {
			Give()->payment_meta->add_meta($donationId, 'bayarcash_directdebit_cycle', 1);
		}
	}

	public function addExportColumns($defaultColumns)
	{
		if (give_is_gateway_active('bayarcash')) {
			$defaultColumns['identification_number'] = esc_html__('Identification Number', 'bayarcash-givewp');
		}
		return $defaultColumns;
	}

	public function setExportDonorData($data, $donor)
	{
		if (give_is_gateway_active('bayarcash')) {
			$identificationNumber = Give()->donor_meta->get_meta($donor->id, 'bayarcash-identification-id', true);
			$data['identification_number'] = !empty($identificationNumber) ? $identificationNumber : '- N/A - ';
		}
		return $data;
	}

	public function displaySubscriptionInfo($donation, $giveReceiptArgs): void {
		if (empty($giveReceiptArgs['id'])) {
			return;
		}

		$paymentId = $giveReceiptArgs['id'];
		$formId = give_get_payment_form_id($paymentId);
		$subscriptionId = give_get_meta($paymentId, 'subscription_id', true);

		if (!$subscriptionId) {
			return;
		}

		$subscriptionPt = new Give_Subscription($subscriptionId);
		if ('bayarcash' !== $subscriptionPt->gateway) {
			return;
		}

		$this->renderSubscriptionInfo();
	}

	private function renderSubscriptionInfo(): void {
		$html = '<div class="details" style="line-height: 1.5; font-weight:500;font-size:16px; border:1px solid #ffeeba;padding:25px;margin:25px 0;border-radius:5px;color:#856404;background-color:#fff3cd;">';
		$html .= '<p class="instruction" style="margin-bottom:18px;">';
		$html .= esc_html__('Please note that RM1.00 is deducted from your bank account for bank verification fee. This fee is non-refundable.', 'bayarcash-givewp');
		$html .= '</p><p>';
		$html .= esc_html__('For monthly recurring, deduction will happen between 3rd-5th every month. In case of failure, second attempt will be made between 25th-28th. For weekly recurring, deduction will happen every Friday.', 'bayarcash-givewp');
		$html .= '</p></div>';

		echo wp_kses(
			$html,
			[
				'script'   => [],
				'noscript' => [],
				'p'        => [],
				'div'      => [
					'class' => [],
					'style' => [],
				],
			]
		);
	}
}