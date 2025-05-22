/* global hc_wcma_params, jQuery, wp */
jQuery(function ($) {
    'use strict';

     // --- Swiper Initialization ---
    // Check if Swiper is loaded
    if (typeof Swiper === 'function') {
        const swiperOptions = {
            // Optional parameters
            slidesPerView: 1, // Default slides per view
            spaceBetween: 15, // Space between slides
            loop: false, // Don't loop if you only have a few addresses

            // Responsive breakpoints
            breakpoints: {
                // when window width is >= 640px
                640: {
                  slidesPerView: 1,
                  spaceBetween: 20
                },
                // when window width is >= 1024px
                1024: {
                  slidesPerView: 1,
                  spaceBetween: 30
                }
            },

            // If you added pagination elements
            pagination: {
                el: '.swiper-pagination', // General class, might need specific below
                clickable: true,
            },

            // If you added navigation elements
            navigation: {
                nextEl: '.swiper-button-next', // General class, might need specific below
                prevEl: '.swiper-button-prev',
            },
        };

        // Initialize Billing Carousel
        if ($('#billing-address-carousel .swiper-slide').length > 0) {
             const billingSwiper = new Swiper('#billing-address-carousel', {
                 ...swiperOptions, // Spread common options
                 pagination: { // Specific pagination for this instance
                     el: '.billing-swiper-pagination',
                     clickable: true,
                 },
                 navigation: { // Specific navigation for this instance
                     nextEl: '.billing-swiper-button-next',
                     prevEl: '.billing-swiper-button-prev',
                 },
             });
        } else {
             $('#billing-address-carousel').hide(); // Hide container if no slides
        }


         // Initialize Shipping Carousel
         if ($('#shipping-address-carousel .swiper-slide').length > 0) {
             const shippingSwiper = new Swiper('#shipping-address-carousel', {
                 ...swiperOptions,
                  pagination: {
                     el: '.shipping-swiper-pagination',
                     clickable: true,
                 },
                 navigation: {
                     nextEl: '.shipping-swiper-button-next',
                     prevEl: '.shipping-swiper-button-prev',
                 },
             });
         } else {
              $('#shipping-address-carousel').hide(); // Hide container if no slides
         }


        //  console.log('Swiper initialized.');
    } else {
        // console.warn('Swiper library not found. Carousel will not function.');
        // Optional: Add a fallback class if Swiper fails
         $('.hc-wcma-address-carousel').addClass('no-swiper-fallback');
    }
    // --- End Swiper Initialization ---

    // Placeholder if no carousel library is present
    if (typeof $.fn.slick !== 'function') { // Check for a common lib like slick
        $('.hc-wcma-address-carousel').addClass('no-carousel'); // Add class for basic list styling
    }

    const $addForm = $('#hc_wcma_add_address_form');
    const $addSubmitButton = $addForm.find('button[type="submit"]');
    const $limitNotice = $('#hc_wcma_limit_notice');
    const $addressTypeSelect = $addForm.find('#hc_wcma_address_type');

    function checkAddressLimits() {
        const selection = $addressTypeSelect.val();
        let limitReached = false;
        let message = '';
    
        const checkType = (type) => {
            if (hc_wcma_params.limits && hc_wcma_params.limits[type] > 0) {
                const limit = hc_wcma_params.limits[type];
                const count = hc_wcma_params.counts ? (hc_wcma_params.counts[type] || 0) : 0;
                if (count >= limit) {
                    message += (message ? '<br>' : '') + hc_wcma_params.limit_message_tpl
                                .replace('%limit%', limit)
                                .replace('%type%', type);
                    return true; // Limit reached for this type
                }
            }
            return false; // Limit not reached or no limit set
        };
    
        if (selection === 'billing') {
            limitReached = checkType('billing');
        } else if (selection === 'shipping') {
            limitReached = checkType('shipping');
        } else if (selection === 'both') {
            const billingReached = checkType('billing');
            const shippingReached = checkType('shipping');
            limitReached = billingReached && shippingReached; // Only fully disabled if BOTH limits reached
            // If only one is reached, PHP will handle saving the allowed one.
            // Message will show warnings for both if applicable.
        }
    
        if (message) {
            $limitNotice.html(message).show();
            // Disable submit only if limits are reached for ALL selected types
            if (limitReached && selection === 'both') {
                 $addSubmitButton.prop('disabled', true);
            } else if (limitReached && (selection === 'billing' || selection === 'shipping')) {
                 $addSubmitButton.prop('disabled', true);
            }
             else {
                 $addSubmitButton.prop('disabled', false); // Re-enable if only one limit hit for 'both'
            }
        } else {
            $limitNotice.hide().html('');
            $addSubmitButton.prop('disabled', false); // Enable if no limits reached
        }
    }

    $addressTypeSelect.on('change', checkAddressLimits);
    checkAddressLimits();

    // --- Add Address Form Handling ---
    $('#hc_wcma_add_address_form').on('submit', function (e) {
        e.preventDefault();
        checkAddressLimits();
        if ($addSubmitButton.prop('disabled')) {
            console.warn('Add address submission blocked by frontend limit check.');
            e.preventDefault(); // Stop submission if button is disabled
            return;
       }
        const $form = $(this);
        const $button = $form.find('button[type="submit"]');
        const $feedback = $('#hc_wcma_form_feedback');

        $button.prop('disabled', true).addClass('loading');
        $feedback.html('').removeClass('woocommerce-error woocommerce-message');

        const formData = $form.serializeArray();
        const addressData = {};
        $.each(formData, function (i, field) {
            // Exclude nonce and type selector from the main address data object
            if (field.name !== 'hc_wcma_save_address_nonce' && field.name !== '_wp_http_referer' && field.name !== 'save_address') {
                 // Remove potential billing_/shipping_ prefix if fields were generated with it (adjust if needed)
                addressData[field.name] = field.value;
            }
        });

        wp.ajax.send('hc_wcma_add_address', {
            data: {
                nonce: hc_wcma_params.nonce, // Use the main AJAX nonce here
                hc_wcma_save_address_nonce: $form.find('#hc_wcma_save_address_nonce').val(), // Pass form nonce too
                address_type_selection: $form.find('#hc_wcma_address_type').val(),
                address_data: addressData,
            },
            success: function (response) {
                $feedback.html(response.message).addClass('woocommerce-message');
                $form[0].reset();
                // **Crucially**: Reload the page or dynamically add the new address card(s)
                // and re-initialize the carousel for the best UX. Reload is simpler.
                if (response.reload) {
                    window.location.reload();
                }
            },
            error: function (error) {
                $feedback.html(error.message || hc_wcma_params.i18n.error).addClass('woocommerce-error');
            },
            complete: function () {
                $button.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // --- Delete Address Handling ---
    $('.hc-wcma-address-carousel').on('click', '.hc-wcma-delete-button', function (e) {
        e.preventDefault();
        const $button = $(this);
        const $card = $button.closest('.hc-wcma-address-card');
        const addressKey = $card.data('address-key');
        const addressType = $card.data('address-type');

        if (confirm(hc_wcma_params.i18n.delete_confirm)) {
            $button.prop('disabled', true).addClass('loading');

            wp.ajax.send('hc_wcma_delete_address', {
                data: {
                    nonce: hc_wcma_params.nonce,
                    address_key: addressKey,
                    address_type: addressType,
                },
                success: function (response) {
                    // Remove the card from the carousel/display
                    // If using Slick: $('#' + addressType + '-address-carousel').slick('slickRemove', $card.data('slick-index'));
                    // Simple removal:
                    $card.fadeOut(300, function() { $(this).remove(); });
                     // Optionally show a success message
                     // Reloading might be easier if default status changes etc.
                     window.location.reload(); // Simple way to ensure everything updates
                },
                error: function (error) {
                    alert(error.message || hc_wcma_params.i18n.error);
                    $button.prop('disabled', false).removeClass('loading');
                }
                // No 'complete' needed as button is removed on success
            });
        }
    });

    // --- Set Default Address Handling ---
    $('.hc-wcma-address-carousel').on('click', '.hc-wcma-set-default-button', function (e) {
        e.preventDefault();
        const $button = $(this);
        const $card = $button.closest('.hc-wcma-address-card');
        const addressKey = $card.data('address-key');
        const addressType = $card.data('address-type');

        $button.prop('disabled', true).addClass('loading');

         wp.ajax.send('hc_wcma_set_default_address', {
            data: {
                nonce: hc_wcma_params.nonce,
                address_key: addressKey,
                address_type: addressType,
            },
            success: function (response) {
                 // Reload the page to reflect the change accurately (simplest)
                 window.location.reload();
            },
            error: function (error) {
                 alert(error.message || hc_wcma_params.i18n.error);
                 $button.prop('disabled', false).removeClass('loading');
            }
        });
    });



    const $editModalOverlay = $('#hc_wcma_edit_modal_overlay');
    const $editModal = $('#hc_wcma_edit_modal');
    const $editForm = $('#hc_wcma_edit_address_form');
    const $editFeedback = $('#hc_wcma_edit_form_feedback');
    var wrapper = $('.hc_wcma_edit_fields');

    // --- Open Edit Modal ---
    $('.hc-wcma-address-carousel').on('click', '.hc-wcma-edit-button', function(e) { // Ensure selector matches button class
        e.preventDefault();
        console.log("--- Edit Button Clicked ---");
        const $button = $(this);
        const $card = $button.closest('.hc-wcma-address-card'); // Ensure selector matches card class
        const addressKey = $card.data('address-key');
        const addressType = $card.data('address-type');
        // Use the data attribute added in render_endpoint_content
        let addressData = $button.data('address');

        // Basic check if data is valid JSON object
        if (typeof addressData !== 'object' || addressData === null) {
            try {
                // Fallback attempt if it's somehow a string
                addressData = JSON.parse(addressData);
            } catch(err) {
                console.error("Could not parse address data for edit:", $button.data('address'));
                return;
            }
        }


        if (!addressKey || !addressType || !addressData) {
            console.error('Error: Missing data required to edit address.');
            return;
        }        

        // Clear previous feedback
        $editFeedback.html('').removeClass('woocommerce-error woocommerce-message');

        // $editForm.find('input[type="text"], input[type="email"], input[type="tel"], input[type="url"], input[type="number"], input[type="search"], select, textarea')
        // .not('[type="hidden"]')
        // .val('');

        // Store key and type in hidden fields
        $editForm.find('#hc_wcma_edit_address_key').val(addressKey);
        $editForm.find('#hc_wcma_edit_address_type').val(addressType);

        // Show/Hide Billing/Shipping sections in modal based on type
        if (addressType === 'billing') {
            $editForm.find('.hc_wcma_edit_billing_fields').show();
            $editForm.find('.hc_wcma_edit_shipping_fields').hide();
        } else if (addressType === 'shipping') {
            $editForm.find('.hc_wcma_edit_billing_fields').hide();
            $editForm.find('.hc_wcma_edit_shipping_fields').show();
        } else {
            // Should not happen based on current setup, but handle defensively
            $editForm.find('.hc_wcma_edit_billing_fields').show();
            $editForm.find('.hc_wcma_edit_shipping_fields').show();
        }

        // Populate form fields
        console.log("Populating visible fields (no triggers yet)...");
        const prefix = addressType + '_';
        $.each(addressData, function(key, value) {
            const fieldName = prefix + key;
            const $field = $editForm.find('[name="' + fieldName + '"]').not('[type="hidden"]');
            if ($field.length) {
            //     if (key === 'country' || key === 'state') {
            //         console.log(`Setting INITIAL value for ${fieldName}: ${value}`);
            //    }
                $field.val(value); // Just set the value
            }
        });
        console.log("Visible fields populated.");

        // Show the modal
        $editModalOverlay.fadeIn(200);
        $editModal.fadeIn(300, function() {
            console.log("Modal visible. Triggering WC Body events...");

            // console.log("Initializing/Re-initializing SelectWoo with dropdownParent...");
            // $editForm.find('select.country_select, select.state_select, select.wc-enhanced-select')
            //    .each(function() {
            //         try {
            //             if ($(this).data('select2')) { $(this).selectWoo('destroy'); }
            //             $(this).selectWoo({ dropdownParent: $editModal, width: 'style' });
            //             console.log('Initialized SelectWoo w/ Parent for:', this.id || this.name);
            //         } catch(e) { console.error(e); }
            //    });            

            console.log("Triggering WC Body events...");
            $( document.body ).trigger( 'wc_country_select_ready' );
            $( document.body ).trigger( 'wc_address_i18n_ready' );

            // --- 8. Trigger change on the CORRECT country field ---
            setTimeout(function() {   
                const countryFieldName = prefix + 'country';             
                const $countryField = $editForm.find('select[name="' + countryFieldName + '"]'); // Target by name
                if ($countryField.length) {
                    console.log(`Triggering 'change' on specific country select: ${countryFieldName}`);
                    $countryField.trigger('change');
                } else {
                    console.warn(`Country select field '${countryFieldName}' not found.`);
               }
           }, 100);

           const stateFieldName = prefix + 'state';
           const stateValueToSet = addressData.state || ''; // Ensure addressData is accessible
           console.log(`State value to set for ${stateFieldName} is: ${stateValueToSet}`);
          

           const stateSetAttemptTimeout = setTimeout(function() {
                console.log(`Running DELAYED state set attempt for ${stateFieldName}...`);
                const $currentStateField = $editForm.find('[name="' + stateFieldName + '"]');
                if ($currentStateField.length && stateValueToSet) {
                    if ($currentStateField.is('select')) {
                        if ($currentStateField.find('option[value="' + stateValueToSet + '"]').length > 0) {
                            console.log(`Setting state SELECT ${stateFieldName} via TIMEOUT: ${stateValueToSet}`);
                            $currentStateField.val(stateValueToSet).trigger('change');
                        } else {
                            let availableOptions = [];
                            $currentStateField.find('option').each(function(){ availableOptions.push($(this).val()); });
                            console.warn(`State option '${stateValueToSet}' not found in SELECT '${stateFieldName}'. Available:`, availableOptions);
                        }
                    } else if ($currentStateField.is('input')) {
                        console.log(`State field ${stateFieldName} is an INPUT.`);
                        console.log(`Setting state INPUT value via TIMEOUT: ${stateValueToSet}`);
                        $currentStateField.val(stateValueToSet);
                    } else {
                        console.warn(`State field ${stateFieldName} is not a SELECT or INPUT.`);
                   }
                } else if (stateValueToSet) {
                    console.warn(`State field '${stateFieldName}' not found during delayed set.`);
                } else {
                        console.log("No state value needed for delayed set.");
                }
           }, 1200);
       });

    //    setTimeout(function() { // Delay this slightly too
    //         console.log("Initializing/Re-initializing SelectWoo with dropdownParent...");
    //         $editForm.find('select.country_select, select.state_select, select.wc-enhanced-select')
    //         .each(function() {
    //             try {
    //                     // Destroy first if already initialized? Might help reset state.
    //                     if ($(this).data('select2')) { $(this).selectWoo('destroy'); }
    //                     // Initialize with dropdownParent
    //                     $(this).selectWoo({
    //                         dropdownParent: $editModal,
    //                         width: 'style' // Try 'style'
    //                     });
    //                     console.log('Initialized SelectWoo w/ Parent for:', this.id || this.name);
    //             } catch(e) { console.error(e); }
    //         });
    //     }, 100);
    });

    // --- Close Edit Modal ---
    function closeEditModal() {
        $editModal.fadeOut(200);
        $editModalOverlay.fadeOut(300);
        $editForm[0].reset(); // Clear the form
    }

    $editModalOverlay.on('click', closeEditModal);
    $editModal.on('click', '#hc_wcma_edit_modal_close', closeEditModal);


    // --- Handle Edit Form Submission ---
    $editForm.on('submit', function(e) {
        e.preventDefault();
        const $button = $editForm.find('button[type="submit"]');

        $button.prop('disabled', true).addClass('loading');
        $editFeedback.html('').removeClass('woocommerce-error woocommerce-message');

        // Serialize form data
        const formData = $editForm.serializeArray();
        const addressData = {};
        $.each(formData, function(i, field) {
            // Skip nonces, submit button names etc.
            if (field.name !== 'hc_wcma_edit_address_nonce' && field.name !== '_wp_http_referer' && field.name !== 'update_address') {
                addressData[field.name] = field.value;
            }
        });

        const dataToSend = {
            // The nonce field name MUST match the second parameter in check_ajax_permissions
            hc_wcma_edit_address_nonce: $editForm.find('#hc_wcma_edit_address_nonce').val(), 
            address_key: $editForm.find('#hc_wcma_edit_address_key').val(),
            address_type: $editForm.find('#hc_wcma_edit_address_type').val(),
            address_data: addressData // Assuming addressData is serialized correctly above
        };
    
        console.log("Data being sent via AJAX:", dataToSend); 

        wp.ajax.send('hc_wcma_update_address', { // Ensure action name matches
            data: dataToSend,
            success: function(response) {
                $editFeedback.html(response.message).addClass('woocommerce-message');
                if (response.reload) {
                    // Wait a moment to show message, then reload
                    setTimeout(function() { window.location.reload(); }, 1500);
                } else {
                    closeEditModal(); // Close if no reload needed (though reload is safer)
                }
            },
            error: function(error) {
                $editFeedback.html(error.message || hc_wcma_params.i18n.error).addClass('woocommerce-error');
                $button.prop('disabled', false).removeClass('loading');
            }
        });
    });

    $editForm.on('change', 'select.country_to_state', function() {
        console.log("Country select changed.");
        setTimeout(function() {
            console.log("Running delayed Select2 removal and scrollbar fix...");
            
            // --- Target Select2 containers associated with selects INSIDE the form ---
            $editForm.find('select.select2-hidden-accessible').each(function() {
                const $originalSelect = $(this);
                // Select2 often puts its container immediately after the hidden original select
                const $select2Container = $originalSelect.next('.select2-container'); 
                
                if ($select2Container.length) {
                    const selectName = $originalSelect.attr('name') || '[unknown select]';
                    console.log(`Removing Select2 container for ${selectName}`);
                    $select2Container.remove(); 
                    // Make the original select visible and usable again
                    $originalSelect.removeClass('select2-hidden-accessible')
                                   .css({ // Remove inline styles that hide it
                                       position: '', 
                                       top: '',
                                       left: '',
                                       width: '', // Let CSS control width
                                       height: ''
                                    })
                                    .removeAttr('aria-hidden')
                                    .removeAttr('tabindex');
                }
            });
            // --- End Target Select2 ---
        }, 100);
    });

});