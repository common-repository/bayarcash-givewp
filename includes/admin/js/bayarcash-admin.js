jQuery(document).ready(function($) {
    const tabPrefixes = {
        'bayarcash': ['bayarcash_'],
        'bc-duitnow': ['bc-duitnow_', 'duitnow_'],
        'bc-linecredit': ['bc-linecredit_', 'linecredit_'],
        'bc-duitnowqr': ['bc-duitnowqr_', 'duitnowqr_'],
        'bc-duitnowshopee': ['bc-duitnowshopee_', 'duitnowshopee_'],
        'bc-duitnowboost': ['bc-duitnowboost_', 'duitnowboost_'],
        'bc-duitnowqris': ['bc-duitnowqris_', 'duitnowqris_'],
        'bc-duitnowqriswallet': ['bc-duitnowqriswallet_', 'duitnowqriswallet_']
    };

    function organizeFieldsIntoTabs() {
        $('.bayarcash-tab-pane').each(function() {
            const tabId = $(this).attr('id');
            const tabName = tabId.replace('bayarcash-', '');
            const prefixes = tabPrefixes[tabName];

            if (prefixes) {
                $('tr').each(function() {
                    const $field = $(this).find('input, select, textarea');
                    const fieldId = $field.attr('id') || $field.attr('name');

                    if (fieldId && prefixes.some(prefix => fieldId.startsWith(prefix))) {
                        $(this).appendTo('#' + tabId);
                    }
                });
            }
        });

        // Move remaining Bayarcash fields to the first tab
        const firstTabId = $('.bayarcash-tab-pane:first').attr('id');
        $('tr').each(function() {
            const $field = $(this).find('input, select, textarea');
            const fieldId = $field.attr('id') || $field.attr('name');
            if (fieldId && /^(bayarcash_|duitnow_|linecredit_|duitnowqr_|duitnowshopee_|duitnowboost_|duitnowqris_|duitnowqriswallet_)/.test(fieldId) && !$(this).closest('.bayarcash-tab-pane').length) {
                $(this).appendTo('#' + firstTabId);
            }
        });
    }

    function initializeTabs() {
        $('.bayarcash-tabs a').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            $('.bayarcash-tabs a').removeClass('active');
            $(this).addClass('active');

            $('.bayarcash-tab-pane').removeClass('active').hide();
            $('#bayarcash-' + tab).addClass('active').show();
        });

        $('.bayarcash-tabs a:first').click();
        $('.bayarcash-tabs-wrapper').insertBefore('.submit');
        organizeFieldsIntoTabs();
    }

    function toggleBayarcashFields(channel) {
        const customizeField = $(`input[name="${channel}_givewp_customize_donations"]:checked`);
        const allFields = $(`#${channel}_options .give-field-wrap`).not(customizeField.closest('.give-field-wrap'));

        allFields.toggle(customizeField.val() !== 'global' && customizeField.val() !== 'disabled');
    }

    function initializeFieldToggling() {
        if ($('body').hasClass('post-type-give_forms') && $('body').hasClass('post-php')) {
            Object.keys(tabPrefixes).forEach(function(channel) {
                var customizeField = $(`input[name="${channel}_givewp_customize_donations"]`);
                customizeField.on('change', () => toggleBayarcashFields(channel));
                toggleBayarcashFields(channel);
            });
        } else {
            $('.bayarcash-tab-pane tr').show();
        }
    }

    initializeTabs();
    initializeFieldToggling();
});