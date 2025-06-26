/* global hc_wcma_checkout_params, jQuery, wc_checkout_params */
jQuery(function ($) {
    'use strict';

    const checkoutForm = $('form.checkout');

    /**
     * Helper function to format address object into HTML (similar to PHP version)
     * @param {object} address Address data object
     * @returns {string} HTML string
     */
    function formatAddressForDisplayJS(address) {
        if (!address || typeof address !== 'object') {
            return '';
        }

        let parts = [];
        const name = (address.first_name || '') + ' ' + (address.last_name || '');
        const company = address.company || '';        
        const address1 = address.address_1 || '';
        const address2 = address.address_2 || '';
        const city = address.city || '';
        const state = address.state || '';
        const postcode = address.postcode || '';
        const country = address.country || '';
        const phone = address.phone || '';
        const email = address.email || '';

        // Basic formatting - Adapt based on needs or passed locale info
        if (name.trim()) parts.push(name.trim());
        if (company) parts.push(company.trim()); 
        if (address1) parts.push(address1.trim());
        if (address2) parts.push(address2.trim());

        let city_state_postcode = city.trim();
        if (state) city_state_postcode += (city_state_postcode ? ', ' : '') + state;
        if (postcode) city_state_postcode += (city_state_postcode ? ' ' : '') + postcode;
        if (city_state_postcode) parts.push(city_state_postcode);

        if (country) parts.push(country.trim());
        if (phone) parts.push(phone.trim());
        if (email) parts.push(email.trim());

        return parts.join('<br>');
    }


    // Function to populate address fields
    function populateAddressFields(type, addressData) {
        const $wrapper = $('.woocommerce-' + type + '-fields__field-wrapper'); // Find the main address wrapper div

        if (!$wrapper.length) {
            return;
        }

        let $fieldsContainer = $wrapper.find('.woocommerce-address-fields__field-wrapper');
        if (!$fieldsContainer.length) {
            // Fallback: Assume fields are direct children wrapped in <p> inside $wrapper
            $fieldsContainer = $wrapper.find('p.form-row').parent(); // Get the parent containing the rows
            if (!$fieldsContainer.length) $fieldsContainer = $wrapper; // Ultimate fallback
        }

        const $addressBlock = $('#hc_wcma_' + type + '_address_block');

        // Clear existing values first (important if switching from 'new')
        $addressBlock.hide().html('');
        $wrapper.find('input[type="text"], input[type="tel"], input[type="email"], select, textarea').each(function() {
            const $field = $(this);
            const fieldName = $field.attr('name');
            if (fieldName) { // Only clear fields with names
                $field.val('');
            }
        });

        if (!addressData) {
            // --- NO ADDRESS DATA ("Enter New" or initial load with nothing selected) ---
            $addressBlock.hide(); // Ensure block is hidden
            $fieldsContainer.slideDown(); // Show the container with form fields
            $wrapper.find('input, select, textarea').prop('disabled', false); // Ensure fields are enabled
            // Trigger WC JS handlers
            $(document.body).trigger('wc_address_i18n_ready');
            $(document.body).trigger('wc_country_select_ready');
            return; // Exit
        }

        // Populate fields
        $.each(addressData, function (key, value) {
            const fieldName = type + '_' + key;
            const $field = $('#' + fieldName);

            if ($field.length) {
                if ($field.is('select')) {
                    $field.val(value).trigger('change');
                    if (key === 'country') {
                         setTimeout(function() {
                            if (addressData.state) {
                                $('#' + type + '_state').val(addressData.state).trigger('change');
                            }
                         }, 100);
                    }
                } else {
                    $field.val(value);
                }
            }
        });

        const savedDisplayMode = hc_wcma_checkout_params.saved_display || 'block';

        if (savedDisplayMode === 'block') {
            const formattedHtml = formatAddressForDisplayJS(addressData);
            if (formattedHtml && $addressBlock.length) {
                 $addressBlock.html(formattedHtml).show();
            }
            $fieldsContainer.slideUp();

        } else {
            $addressBlock.hide();
            $fieldsContainer.slideDown();
            $wrapper.find('input, select, textarea').prop('disabled', false);
        }

        $(document.body).trigger('wc_address_i18n_ready');
        $(document.body).trigger('wc_country_select_ready');
        $wrapper.find('.woocommerce-address-fields__field-wrapper').slideUp();
    }

    // Function to handle selector change
    function handleAddressSelectionChange() {
        const $select = $(this);
        const type = $select.data('address-type'); // 'billing' or 'shipping'
        const selectedKey = $select.val();

        if (!type) return;

        const addresses = hc_wcma_checkout_params.addresses[type] || {};

        if (selectedKey === 'new' || selectedKey === '') {
            populateAddressFields(type, null);
        } else if (addresses && addresses[selectedKey]) {
            const selectedAddress = addresses[selectedKey];
            populateAddressFields(type, selectedAddress);
        } else {
            populateAddressFields(type, null);
        }
    }

    // Initial setup on page load (check if default is pre-selected)
    function initializeSelectors() {
        $('.hc-wcma-address-select').each(function() {
            const $selectOrRadio = $(this);
            const type = $selectOrRadio.data('address-type');
            let initialValue = '';

            if ($selectOrRadio.is('select')) {
                initialValue = $selectOrRadio.val();
            } else if ($selectOrRadio.is(':radio')) {
                const radioName = $selectOrRadio.attr('name');
                initialValue = $('input[name="' + radioName + '"]:checked').val();
            } else {
                return;
            }

            if (initialValue && initialValue !== 'new') {
                const addresses = hc_wcma_checkout_params.addresses[type] || {};
                if (addresses && addresses[initialValue]) {
                     populateAddressFields(type, addresses[initialValue]);
                } else {
                    populateAddressFields(type, null);
                }

            } else {
                if (initialValue !== 'new' && hc_wcma_checkout_params.allow_new === 'yes') {
                     populateAddressFields(type, null);
                } else if (initialValue !== 'new' && hc_wcma_checkout_params.allow_new !== 'yes') {
                    populateAddressFields(type, null);
                }
            }
        });

        if (hc_wcma_checkout_params.selector_style === 'dropdown') {
            $('.hc-wcma-address-select.select').filter(':not(.select2-hidden-accessible)').selectWoo().addClass('select2-hidden-accessible');
        }
    }

    checkoutForm.on('change', '.hc-wcma-address-select', handleAddressSelectionChange);

    initializeSelectors();
});