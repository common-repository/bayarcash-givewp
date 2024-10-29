(function($) {
    const BayarCashAdmin = {
        channels: ['bayarcash', 'bc-duitnow', 'bc-linecredit', 'bc-duitnowqr', 'bc-duitnowshopee', 'bc-duitnowboost', 'bc-duitnowqris', 'bc-duitnowqriswallet'],
        cache: {},
        init: function() {
            $(document).ready(() => {
                Promise.all(this.channels.map(channel => this.setupVerifyToken(channel)))
                    .then(() => console.log('All channels initialized'));
            })
        },
        getEndpoint: () => bayarcashAdminData.is_test_mode ? bayarcashAdminData.endpoint_sandbox : bayarcashAdminData.endpoint_public,
        setupVerifyToken: async function(channel) {
            const portalTokenField = $(`textarea#${channel}_portal_token`);
            if (portalTokenField.length === 0) return;

            const verifyDiv = this.createVerifyDiv(channel);
            portalTokenField.after(verifyDiv);

            const app = this.createVueApp(channel);
            app.mount(`#${channel}-vue-app`);
        },
        createVerifyDiv: function(channel) {
            return $('<div>', {
                id: `${channel}-verify-token`,
                html: `
                    <div id="${channel}-vue-app">
                        <button type="button" class="button-primary" id="${channel}-verify-button" @click="debouncedVerifyToken">Verify Token</button>
                        <span :id="'${channel}-verify-status'" :class="{ valid: isTokenValid, invalid: !isTokenValid }" v-html="statusText"></span>
                    </div>
                `
            });
        },
        createVueApp: function(channel) {
            return Vue.createApp({
                data() {
                    return {
                        channel,
                        statusText: '',
                        status: null,
                        portalInfo: null,
                        savedSettings: {},
                        additionalInfoText: '',
                        merchantName: '',
                        isCustomized: false,
                        formId: null
                    };
                },
                computed: {
                    isTokenValid() { return this.status === 1; },
                    filteredPortals() { return this.portalInfo ? this.portalInfo.portals : []; }
                },
                methods: {
                    async loadSavedSettings() {
                        this.setFormIdAndCustomization();
                        if (BayarCashAdmin.cache[`settings_${this.channel}`]) {
                            this.savedSettings = BayarCashAdmin.cache[`settings_${this.channel}`];
                        } else {
                            await this.fetchSavedSettings();
                        }
                        this.populateFields();
                    },
                    setFormIdAndCustomization() {
                        const urlParams = new URLSearchParams(window.location.search);
                        this.formId = urlParams.get('post');
                        this.isCustomized = $(`input[name="${this.channel}_givewp_customize_donations"]:checked`).val() === 'enabled';
                    },
                    async fetchSavedSettings() {
                        try {
                            const response = await $.ajax({
                                url: bayarcashAdminData.ajaxurl,
                                method: 'POST',
                                data: {
                                    action: 'get_bayarcash_settings',
                                    channel: this.channel,
                                    nonce: bayarcashAdminData.nonce,
                                    form_id: this.formId,
                                    is_customized: this.isCustomized
                                }
                            });
                            if (response.success) {
                                this.savedSettings = response.data;
                                BayarCashAdmin.cache[`settings_${this.channel}`] = this.savedSettings;
                            } else {
                                console.error('Error loading saved settings:', response.data);
                            }
                        } catch (error) {
                            console.error('Error loading saved settings:', error);
                        }
                    },
                    populateFields() {
                        const { portal_token, portal_key, secret_key } = this.savedSettings;
                        this.setFieldValue(`textarea#${this.channel}_portal_token`, portal_token);
                        this.setFieldValue(`select#${this.channel}_portal_key`, portal_key);
                        this.setFieldValue(`input#${this.channel}_secret_key`, secret_key);
                        this.updateFieldsVisibility();
                    },
                    setFieldValue(selector, value, placeholder = '') {
                        const $field = $(selector);
                        if (value) {
                            $field.val(value);
                        } else if (placeholder) {
                            $field.attr('placeholder', placeholder);
                        }
                    },
                    updateFieldsVisibility() {
                        ['portal_token', 'portal_key', 'secret_key'].forEach(field => {
                            $(`#${this.channel}_${field}`).closest('tr').toggle(this.isCustomized);
                        });
                    },
                    async verifyToken(event) {
                        if (event) event.preventDefault();
                        const token = $.trim($(`textarea#${this.channel}_portal_token`).val());
                        if (!token) {
                            this.handleInvalidToken('Please insert Token.');
                            return false;
                        }
                        this.setStatus('Validating PAT token..', null);
                        try {
                            const response = await this.makeApiCall(token);
                            this.handleApiResponse(response);
                        } catch (error) {
                            console.error('Error:', error);
                            this.handleInvalidToken();
                        }
                    },
                    async makeApiCall(token) {
                        const apiUrl = `${BayarCashAdmin.getEndpoint()}/api/transactions/`;
                        const cacheKey = `api_${apiUrl}_${token}`;
                        if (BayarCashAdmin.cache[cacheKey]) return BayarCashAdmin.cache[cacheKey];
                        const response = await axios.post(apiUrl, {}, {
                            headers: {
                                'Accept': 'application/json',
                                'Authorization': `Bearer ${token}`
                            }
                        });
                        BayarCashAdmin.cache[cacheKey] = response;
                        return response;
                    },
                    handleApiResponse(response) {
                        if (response.status === 200) {
                            this.setStatus('PAT Token is valid', 1);
                            const portalsList = response.data.output.portalsList;
                            if (portalsList.recordsListData.length > 0) {
                                this.updatePortalInfo(portalsList.recordsListData);
                            } else {
                                this.clearPortalInfo();
                            }
                            this.updateAdditionalInfo();
                            this.populatePortalKeyOptions(portalsList.recordsListData);
                        } else {
                            this.handleInvalidToken();
                        }
                    },
                    debouncedVerifyToken: _.debounce(function() { this.verifyToken(); }, 300),
                    setStatus(text, status) {
                        this.statusText = text + (status === 1 ? ' <span class="dashicons dashicons-yes-alt"></span>' :
                            status === 0 ? ' <span class="dashicons dashicons-dismiss"></span>' : '');
                        this.status = status;
                    },
                    updatePortalInfo(portals) {
                        this.portalInfo = {
                            merchantName: portals[0].merchant.name,
                            portals: portals
                        };
                        this.merchantName = this.portalInfo.merchantName;
                        this.displayMerchantName();
                    },
                    clearPortalInfo() {
                        this.portalInfo = null;
                        this.merchantName = '';
                        this.removeMerchantNameDisplay();
                        $('#portal-info').remove();
                    },
                    populatePortalKeyOptions(portals) {
                        const selectElement = $(`select#${this.channel}_portal_key`);
                        const currentValue = selectElement.val() || this.savedSettings.portal_key;
                        selectElement.empty().append(portals.map(portal =>
                            $('<option>', {
                                value: portal.api_key,
                                text: `${portal.name} (${portal.api_key})`
                            })
                        ));
                        if (currentValue && selectElement.find(`option[value="${currentValue}"]`).length > 0) {
                            selectElement.val(currentValue);
                        }
                        selectElement.trigger('change');
                    },
                    handleInvalidToken(message = 'Invalid PAT Token') {
                        this.setStatus(message, 0);
                        this.clearPortalInfo();
                        this.clearFields();
                        this.updateAdditionalInfo();
                    },
                    clearFields() {
                        $(`select#${this.channel}_portal_key`).val('').empty().append($('<option>', {
                            value: '',
                            text: 'Please Enter Valid PAT Key'
                        }));
                    },
                    updateAdditionalInfo() {
                        const infoElementId = `${this.channel}-additional-info`;
                        $(`#${infoElementId}`).remove();

                        let infoText = this.getAdditionalInfoText();
                        const infoElement = $('<p>', {
                            id: infoElementId,
                            class: 'description',
                            html: infoText
                        });
                        $(`select#${this.channel}_portal_key`).after(infoElement);
                    },
                    getAdditionalInfoText() {
                        if (this.status === 0) {
                            return `Invalid token. Please enter a valid token and verify again.`;
                        }
                        if (this.status === 1) {
                            return `Valid token.`;
                        }
                        return ``;
                    },
                    displayMerchantName() {
                        const merchantNameElementId = `${this.channel}-merchant-name`;
                        $(`#${merchantNameElementId}`).remove();
                        if (this.merchantName) {
                            const merchantNameElement = $('<div>', {
                                id: merchantNameElementId,
                                class: 'description',
                                html: `<strong>Merchant Name:</strong> ${this.merchantName}`,
                                css: { 'margin-top': '10px', 'margin-bottom': '10px' }
                            });
                            $(`#${this.channel}-verify-status`).after(merchantNameElement);
                        }
                    },
                    removeMerchantNameDisplay() {
                        $(`#${this.channel}-merchant-name`).remove();
                    },
                },
                mounted() {
                    this.loadSavedSettings().then(() => {
                        const tokenField = $(`textarea#${this.channel}_portal_token`);
                        if (tokenField.val().trim() !== '') {
                            this.verifyToken();
                        }
                    });

                    $(`select#${this.channel}_portal_key`).on('change', (e) => {
                        const selectedPortalKey = $(e.target).val();
                        if (this.portalInfo && this.portalInfo.portals) {
                            const selectedPortal = this.portalInfo.portals.find(p => p.api_key === selectedPortalKey);
                            if (selectedPortal) {
                                this.updateAdditionalInfo();
                            }
                        }
                    });

                    $(`input[name="${this.channel}_givewp_customize_donations"]`).on('change', (e) => {
                        this.isCustomized = $(e.target).val() === 'enabled';
                        this.updateFieldsVisibility();
                    });
                }
            });
        }
    };

    BayarCashAdmin.init();
})(jQuery);