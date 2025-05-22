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

        return parts.join('<br>'); // Remove trailing <br>
    }


    // Function to populate address fields
    function populateAddressFields(type, addressData) {
        const $wrapper = $('.woocommerce-' + type + '-fields__field-wrapper'); // Find the main address wrapper div

        if (!$wrapper.length) {
            console.error(`Could not find address wrapper for type: ${type}`);
            return;
        }

        // --- Get the element that actually wraps the fields ---
        // Replace '.your-actual-fields-wrapper-class' if needed
        let $fieldsContainer = $wrapper.find('.woocommerce-address-fields__field-wrapper');
        if (!$fieldsContainer.length) {
            // Fallback: Assume fields are direct children wrapped in <p> inside $wrapper
            $fieldsContainer = $wrapper.find('p.form-row').parent(); // Get the parent containing the rows
            if (!$fieldsContainer.length) $fieldsContainer = $wrapper; // Ultimate fallback
        }

        // --- Get the formatted address block element ---
        const $addressBlock = $('#hc_wcma_' + type + '_address_block');

        // Clear existing values first (important if switching from 'new')
        $addressBlock.hide().html('');
        $wrapper.find('input[type="text"], input[type="tel"], input[type="email"], select, textarea').each(function() {
            const $field = $(this);
            const fieldName = $field.attr('name');
            if (fieldName) { // Only clear fields with names
                $field.val('');
               // $field.val('').trigger('change'); // Trigger change for compatibility (e.g., state dropdown)
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
                // Handle different field types
                if ($field.is('select')) {
                    // For select dropdowns (like country or state)
                    $field.val(value).trigger('change'); // Set value and trigger change
                    // Ensure state field updates if country changes
                    if (key === 'country') {
                         // WooCommerce should handle state updates on 'change' trigger
                         // May need small delay if async updates are slow
                         setTimeout(function() {
                            if (addressData.state) {
                                $('#' + type + '_state').val(addressData.state).trigger('change');
                            }
                         }, 100);
                    }
                } else {
                    // For text inputs, textarea, etc.
                    $field.val(value);
                }
            }
        });

        // 2. Decide whether to show fields or the block based on admin setting
        const savedDisplayMode = hc_wcma_checkout_params.saved_display || 'block'; // Default to block

        if (savedDisplayMode === 'block') {
            // --- Show Formatted Block ---
            const formattedHtml = formatAddressForDisplayJS(addressData);
            if (formattedHtml && $addressBlock.length) {
                 $addressBlock.html(formattedHtml).show(); // Add formatted address and show block
            }
            $fieldsContainer.slideUp(); // Hide the fields container
            // $wrapper.find('input, select, textarea').prop('disabled', true); // Optionally disable hidden fields

        } else {
            // --- Show Form Fields (Populated) ---
            $addressBlock.hide(); // Hide the formatted block div
            $fieldsContainer.slideDown(); // Show the fields container
            $wrapper.find('input, select, textarea').prop('disabled', false); // Ensure fields are enabled
        }

        // Trigger WC JS after populating/showing
        $(document.body).trigger('wc_address_i18n_ready');
        $(document.body).trigger('wc_country_select_ready');

        // Optionally hide/disable the fields after populating
        $wrapper.find('.woocommerce-address-fields__field-wrapper').slideUp(); // Hide the standard fields
        // $wrapper.find('input, select, textarea').prop('disabled', true); // Optionally disable
    }

    // Function to handle selector change
    function handleAddressSelectionChange() {
        const $select = $(this);
        const type = $select.data('address-type'); // 'billing' or 'shipping'
        const selectedKey = $select.val();
        const $addressWrapper = $('.' + type + '_address').find('.woocommerce-address-fields__field-wrapper');

        if (!type) return; // Exit if type is not defined

        // Find the relevant addresses from localized data
        const addresses = hc_wcma_checkout_params.addresses[type] || {};

        if (selectedKey === 'new' || selectedKey === '') {
            // User selected "Enter New" or "-- Select --"
            populateAddressFields(type, null); // Clear fields / show form
            // $addressWrapper.slideDown();
        } else if (addresses && addresses[selectedKey]) {
            // User selected a saved address
            const selectedAddress = addresses[selectedKey];
            populateAddressFields(type, selectedAddress);
            // $addressWrapper.slideUp(); // Hide the form fields
        } else {
            // Fallback / Error - shouldn't happen if data is correct
            console.error('HC WCMA: Selected address key not found:', selectedKey);
            populateAddressFields(type, null);
            // $addressWrapper.slideDown();
        }
    }

    // Initial setup on page load (check if default is pre-selected)
    function initializeSelectors() {
        $('.hc-wcma-address-select').each(function() {
            const $selectOrRadio = $(this);
            const type = $selectOrRadio.data('address-type');
            const $addressWrapper = $('.' + type + '_address').find('.woocommerce-address-fields__field-wrapper');
            let initialValue = '';

            if ($selectOrRadio.is('select')) {
                initialValue = $selectOrRadio.val();
            } else if ($selectOrRadio.is(':radio')) {
                const radioName = $selectOrRadio.attr('name');
                initialValue = $('input[name="' + radioName + '"]:checked').val();
            } else {
                return;
            }

            // Only proceed if a specific saved address is selected initially (not '' or 'new')
            if (initialValue && initialValue !== 'new') {
                // A saved address (likely default) is pre-selected
                const addresses = hc_wcma_checkout_params.addresses[type] || {};
                if (addresses && addresses[initialValue]) {
                     populateAddressFields(type, addresses[initialValue]);
                    //  $addressWrapper.slideUp();
                } else {
                    populateAddressFields(type, null);
                    //  $addressWrapper.slideDown(); // Show form if default key is invalid
                }

            } else {
                // "New" or "-- Select --" is chosen initially
                if (initialValue !== 'new' && hc_wcma_checkout_params.allow_new === 'yes') {
                    // If default selection is empty ('-- Select --'), show the new form
                     populateAddressFields(type, null);
                } else if (initialValue !== 'new' && hc_wcma_checkout_params.allow_new !== 'yes') {
                    // If default selection is empty and NEW is disallowed, make sure form still shows
                    populateAddressFields(type, null);
                }
            }
        });

        // Init Select2 if using dropdowns
        if (hc_wcma_checkout_params.selector_style === 'dropdown') {
            $('.hc-wcma-address-select.select').filter(':not(.select2-hidden-accessible)').selectWoo().addClass('select2-hidden-accessible');
        }
    }

     // Attach event listeners
    // Use 'change' for select dropdowns and radio buttons
    checkoutForm.on('change', '.hc-wcma-address-select', handleAddressSelectionChange);

    // Trigger initialization after checkout potentially updates fragments
//    $(document.body).on('updated_checkout', initializeSelectors);

    // Run on initial load
    initializeSelectors();
});