<?php
namespace BayarCash\GiveWP\FormFields;

class BayarcashPaymentFieldManager
{
	private $form_id;
	private $is_legacy_form;

	public function __construct($form_id, $is_legacy_form)
	{
		$this->form_id = $form_id;
		$this->is_legacy_form = $is_legacy_form;
	}

	public static function shouldEnablePhone($form_id, $is_recurring = false): array {
		$channels = [
			'bayarcash' => 'bayarcash',
			'bayarcash_duitnow' => 'bc-duitnow',
			'bayarcash_linecredit' => 'bc-linecredit',
			'bayarcash_duitnowqr' => 'bc-duitnowqr',
			'bayarcash_duitnowshopee' => 'bc-duitnowshopee',
			'bayarcash_duitnowboost' => 'bc-duitnowboost',
			'bayarcash_duitnowqris' => 'bc-duitnowqris',
			'bayarcash_duitnowqriswallet' => 'bc-duitnowqriswallet'
		];

		$global_enable_phone = [];
		$form_customize = [];
		$enable_phone = [];

		foreach ($channels as $payment_mode => $setting_key) {
			$global_enable_phone[$payment_mode] = give_get_option("{$setting_key}_enable_phone_number") === 'enabled';
			$form_customize[$payment_mode] = give_get_meta($form_id, "{$setting_key}_givewp_customize_donations", true) === 'enabled';

			if ($form_customize[$payment_mode]) {
				$enable_phone[$payment_mode] = give_get_meta($form_id, "{$setting_key}_enable_phone_number", true) !== 'disabled';
			} else {
				$enable_phone[$payment_mode] = $global_enable_phone[$payment_mode];
			}

			if ($is_recurring) {
				$enable_phone[$payment_mode] = true;
			}
		}

		return $enable_phone;
	}

	public function manage($pre_filled_phone = '')
	{
		$enable_phone = self::shouldEnablePhone($this->form_id);

		if (array_filter($enable_phone)) {
			$this->renderFields($enable_phone, $pre_filled_phone);
		}
	}

	private function isRecurringForm(): bool {
		// Check if Give Recurring add-on is active
		if (!class_exists('Give_Recurring')) {
			return false;
		}

		// Check if the current form is set up for recurring donations
		$recurring_option = give_get_meta($this->form_id, '_give_recurring', true);
		return $recurring_option === 'yes_donor' || $recurring_option === 'yes_admin';
	}

	private function renderFields($enable_phone, $pre_filled_phone)
	{
		$phone = isset($_REQUEST['bayarcash-phone']) ? sanitize_text_field($_REQUEST['bayarcash-phone']) : $pre_filled_phone;
		$identification_type = isset($_REQUEST['bayarcash-identification-type']) ? sanitize_text_field($_REQUEST['bayarcash-identification-type']) : '1';
		$identification_id = isset($_REQUEST['bayarcash-identification-id']) ? sanitize_text_field($_REQUEST['bayarcash-identification-id']) : '';
		?>
        <style id="bayarcash-form-fields">
            .bayarcash-hidden {
                display: none;
            }

            <?php if (!$this->is_legacy_form) : ?>
            .give-personal-info-section #bayarcash-phone-wrap label,
            .give-personal-info-section #bayarcash-identification-type-wrap label,
            .give-personal-info-section #bayarcash-identification-id-wrap label {
                clip: rect(0, 0, 0, 0);
                border-width: 0;
                height: 1px;
                margin: -1px;
                overflow: hidden;
                padding: 0;
                position: absolute;
                white-space: nowrap;
                width: 1px
            }

            #bayarcash-phone-wrap,
            #bayarcash-identification-type-wrap,
            #bayarcash-identification-id-wrap {
                position: relative
            }

            #bayarcash-phone-wrap:before,
            #bayarcash-identification-id-wrap:before {
                block-size: 1em;
                color: #8d8e8e;
                font-family: Font Awesome\ 5 Free, serif;
                font-weight: 900;
                inset-block-end: .0em;
                inset-block-start: 0;
                inset-inline-start: 0.7rem;
                margin-block: auto;
                pointer-events: none;
                position: absolute;
                font-size: 14px;
            }

            #bayarcash-phone-wrap input,
            #bayarcash-identification-id-wrap input {
                -webkit-padding-start: 2.6875rem;
                padding-inline-start: 2.6875rem
            }

            #bayarcash-phone-wrap:before {
                transform: rotate(90deg);
                content: "\f095";
            }

            #bayarcash-identification-id-wrap:before {
                content: "\f2c2";
            }

            #bayarcash-phone-wrap input#bayarcash-phone,
            #bayarcash-identification-id-wrap input#bayarcash-identification-id {
                padding-left: 33px !important;
            }
            <?php endif; ?>
        </style>

        <div id="bayarcash-form-fields">
            <p id="bayarcash-phone-wrap" class="form-row form-row-wide bayarcash-hidden">
                <label class="give-label" for="bayarcash-phone">
					<?php esc_html_e('Phone Number', 'bayarcash-givewp'); ?>
                    <span class="give-required-indicator">*</span>
					<?php echo Give()->tooltips->render_help(esc_html__('We require a phone number for verification.', 'bayarcash-givewp')); ?>
                </label>
                <input
                        class="give-input"
                        type="text"
                        name="bayarcash-phone"
                        autocomplete="tel"
                        placeholder="<?php esc_html_e('Phone Number', 'bayarcash-givewp'); ?>"
                        id="bayarcash-phone"
                        value="<?php echo esc_attr($phone); ?>"
                        aria-required="true"
                >
            </p>

            <p id="bayarcash-identification-type-wrap" class="form-row form-row-first form-row-responsive bayarcash-hidden">
                <label class="give-label" for="bayarcash-identification-type">
					<?php esc_html_e('Identification Type', 'bayarcash-givewp'); ?>
                    <span class="give-required-indicator">*</span>
					<?php echo Give()->tooltips->render_help(esc_html__('We require an identification type for verification.', 'bayarcash-givewp')); ?>
                </label>
                <select class="give-select" id='bayarcash-identification-type' name='bayarcash-identification-type' v-model="identificationType">
                <?php
					foreach ([
						'1' => 'New IC Number',
						'2' => 'Old IC Number',
						'3' => 'Passport Number',
						'4' => 'Business Registration',
					] as $id => $vl) :
						?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($identification_type, $id); ?>><?php echo esc_html($vl); ?></option>
					<?php endforeach; ?>
                </select>
            </p>

            <p id="bayarcash-identification-id-wrap" class="form-row form-row-last form-row-responsive bayarcash-hidden">
                <label class="give-label" for="bayarcash-identification-id">
					<?php esc_html_e('Identification Number', 'bayarcash-givewp'); ?>
                    <span class="give-required-indicator">*</span>
					<?php echo Give()->tooltips->render_help(esc_html__('We require an identification number for verification.', 'bayarcash-givewp')); ?>
                </label>
                <input
                        class="give-input"
                        type="text"
                        name="bayarcash-identification-id"
                        autocomplete="off"
                        placeholder="<?php esc_html_e('Identification Number', 'bayarcash-givewp'); ?>"
                        id="bayarcash-identification-id"
                        v-model="identificationId"
                        @input="handleInput"
                        @keypress="handleKeyPress"
                        aria-required="true"
                >
                <span id="identification-error" class="give-error" style="display: none;"></span>
            </p>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Vue.createApp({
                    data() {
                        return {
                            phone: '<?php echo esc_js($phone); ?>',
                            identificationType: '<?php echo esc_js($identification_type); ?>',
                            identificationId: '<?php echo esc_js($identification_id); ?>',
                            identificationError: ''
                        };
                    },
                    computed: {
                        getMaxLength() {
                            switch(this.identificationType) {
                                case '1': return 12;
                                case '2': return 10;
                                case '3': return 99;
                                case '4': return 99;
                                default: return 27;
                            }
                        }
                    },
                    methods: {
                        validateIdentificationId() {
                            if (this.identificationId.length > 0) {
                                let isValid = true;
                                let errorMessage = '';

                                switch(this.identificationType) {
                                    case '1':
                                        const newICPattern = /^\d{2}(?:(?:0[1-9]|1[0-2])(?:0[1-9]|1[0-9]|2[0-9])|(?:01|03|05|07|08|10|12)(?:3[01])|(?:04|06|09|11)(?:30))\d{6}$/;
                                        isValid = newICPattern.test(this.identificationId);
                                        errorMessage = isValid ? '' : 'Please enter a valid IC Number in the format YYMMDDXXXXXX (12 digits)';
                                        break;
                                    case '2':
                                        isValid = /^\d{7}$/.test(this.identificationId);
                                        errorMessage = isValid ? '' : 'Please enter a valid Old IC Number. It should be exactly 7 digits long.';
                                        break;
                                    case '3':
                                        isValid = /^[A-Z0-9]{9}$/.test(this.identificationId);
                                        errorMessage = isValid ? '' : 'Please enter a valid Passport Number.';
                                        break;
                                    case '4':
                                        isValid = /^[A-Z0-9]{10}$/.test(this.identificationId);
                                        errorMessage = isValid ? '' : 'Please enter a valid Business Registration.';
                                        break;
                                }

                                this.identificationError = isValid ? '' : '<?php echo esc_js(__('', 'bayarcash-givewp')); ?>' + errorMessage;
                            } else {
                                this.identificationError = '';
                            }
                            document.getElementById('identification-error').textContent = this.identificationError;
                            document.getElementById('identification-error').style.display = this.identificationError ? 'block' : 'none';
                        },
                        handleInput(event) {
                            let value = event.target.value;

                            switch(this.identificationType) {
                                case '1': // New IC Number
                                    value = value.replace(/\D/g, '').slice(0, 12);
                                    break;
                                case '2': // Old IC Number
                                    value = value.replace(/\D/g, '').slice(0, 7);
                                    break;
                                case '3': // Passport Number
                                    value = value.replace(/[^A-Z0-9]/gi, '').toUpperCase().slice(0, 9);
                                    break;
                                case '4': // Business Registration
                                    value = value.replace(/[^A-Z0-9]/gi, '').toUpperCase().slice(0, 10);
                                    break;
                            }

                            this.identificationId = value;
                            this.validateIdentificationId();

                            event.target.value = this.identificationId;
                        },
                        handleKeyPress(event) {
                            switch(this.identificationType) {
                                case '1': // New IC Number
                                case '2': // Old IC Number
                                    if (!/\d/.test(event.key)) {
                                        event.preventDefault();
                                    }
                                    break;
                                case '3': // Passport Number
                                case '4': // Business Registration
                                    if (!/[A-Za-z0-9]/.test(event.key)) {
                                        event.preventDefault();
                                    }
                                    break;
                            }

                            // Prevent input if already at max length
                            if (this.identificationId.length >= this.getMaxLength) {
                                event.preventDefault();
                            }
                        },
                        clearIdentificationId() {
                            console.log('Clearing identification ID');
                            this.identificationId = '';
                            this.$nextTick(() => {
                                const idField = document.getElementById('bayarcash-identification-id');
                                if (idField) {
                                    idField.value = '';
                                    idField.dispatchEvent(new Event('input'));
                                }
                            });
                            this.validateIdentificationId();
                        }
                    },
                    watch: {
                        identificationType(newValue, oldValue) {
                            console.log('Identification type changed from', oldValue, 'to', newValue);
                            this.clearIdentificationId();
                        }
                    },
                    mounted() {
                        this.validateIdentificationId();
                        const identificationTypeElement = document.getElementById('bayarcash-identification-type');
                        const identificationIdElement = document.getElementById('bayarcash-identification-id');

                        if (identificationTypeElement) {
                            identificationTypeElement.addEventListener('change', (e) => {
                                this.identificationType = e.target.value;
                                console.log('Identification type changed to:', this.identificationType);
                            });
                        }

                        if (identificationIdElement) {
                            identificationIdElement.addEventListener('input', this.handleInput);
                            identificationIdElement.addEventListener('keypress', this.handleKeyPress);
                        }
                    }
                }).mount('#bayarcash-form-fields');
            });
        </script>
        <script id="bayarcash-form-fields-toggle">
            ( function( $ ) {
                $( document ).ready( function() {
                    function toggleBayarcashFields() {
                        let $paymentMode = $('input[name=payment-mode]:checked, select[name=payment-mode]').val();
                        let $isRecurring = $('input[name=give-recurring-period]').is(':checked') || $('input[name=_give_is_donation_recurring]').val() === '1';
                        let enabledChannels = <?php echo json_encode(array_keys(array_filter($enable_phone))); ?>;
                        let $bayarcashFields = $('p[id^=bayarcash-]');

                        if ($paymentMode === 'bayarcash' && $isRecurring) {
                            $bayarcashFields.removeClass('bayarcash-hidden');
                            $('#bayarcash-phone, #bayarcash-identification-id').prop('required', true);
                        } else {
                            $bayarcashFields.addClass('bayarcash-hidden');
                            $('#bayarcash-phone, #bayarcash-identification-id').prop('required', false);
                        }

                        if (enabledChannels.includes($paymentMode)) {
                            $('#bayarcash-phone-wrap').removeClass('bayarcash-hidden');
                            $('#bayarcash-phone').prop('required', true);
                        }
                    }

                    $('input[name=payment-mode], select[name=payment-mode], input[name=give-recurring-period], input[name=_give_is_donation_recurring]').on('change', toggleBayarcashFields);

                    toggleBayarcashFields();

                    $(document).on('give_gateway_loaded', toggleBayarcashFields);
                });
            })( jQuery );
        </script>

		<?php
		wp_nonce_field('bayarcash_givewp_nonce_action', 'bayarcash_givewp_form_fields');
	}
}