/* global hc_wcma_params, jQuery, wp, hc_wcma_existing_nicknames */
jQuery(function ($) {
    'use strict';

    function updateNicknameOptions() {
        if (typeof hc_wcma_existing_nicknames === 'undefined') {
            return;
        }

        const { billing: billingNicknames = [], shipping: shippingNicknames = [] } = hc_wcma_existing_nicknames;
        const $addForm = $('#hc_wcma_add_address_form');
        const $billingTypeSelect = $addForm.find('#billing_nickname_type');
        const $shippingTypeSelect = $addForm.find('#shipping_nickname_type');
        const addressType = $('#hc_wcma_address_type').val();
        const isSameAsBilling = $('#shipping_same_as_billing').is(':checked');

        // For billing nickname dropdown
        $billingTypeSelect.find('option').each(function() {
            const $option = $(this);
            const optionValue = $option.val();
            if (optionValue === 'Home' || optionValue === 'Work') {
                let isDisabled = billingNicknames.includes(optionValue);
                if (addressType === 'both' && isSameAsBilling) {
                    isDisabled = isDisabled || shippingNicknames.includes(optionValue);
                }
                $option.prop('disabled', isDisabled);
            }
        });

        // For shipping nickname dropdown
        $shippingTypeSelect.find('option').each(function() {
            const $option = $(this);
            const optionValue = $option.val();
            if (optionValue === 'Home' || optionValue === 'Work') {
                $option.prop('disabled', shippingNicknames.includes(optionValue));
            }
        });
    }
    
    function toggleNicknameFieldsRequired(type, isRequired) {
        const $nicknameTypeField = $('[name="' + type + '_nickname_type"]');
        const $nicknameTypeLabel = $nicknameTypeField.closest('.form-row').find('label'); 
        const $nicknameField = $('[name="' + type + '_nickname"]');
        const $nicknameLabel = $nicknameField.closest('.form-row').find('label');  

        if (isRequired) {
            $nicknameTypeField.prop('required', true).closest('.form-row').addClass('validate-required').show();          
            // Re-trigger change to handle 'Other' nickname field visibility
            $nicknameTypeLabel.find("span.optional").remove();
            $nicknameTypeField.trigger('change');
            $nicknameLabel.find("span.optional").remove();
        } else {
            $nicknameTypeField.prop('required', false).closest('.form-row').removeClass('validate-required').hide();
            $nicknameField.prop('required', false).closest('.form-row').removeClass('validate-required').hide();
        }
    }


    function initializeNicknameFieldToggle() {
        const formWrappers = ['#hc_wcma_add_address_form', '#hc_wcma_edit_address_form'];

        formWrappers.forEach(function(formSelector) {
            const $form = $(formSelector);
            if (!$form.length) {
                return;
            }

            const fieldPairs = [
                { type: '[name="billing_nickname_type"]', other: '[name="billing_nickname"]' },
                { type: '[name="shipping_nickname_type"]', other: '[name="shipping_nickname"]' }
            ];

            fieldPairs.forEach(function(pair) {
                const $typeSelect = $form.find(pair.type);
                const $otherField = $form.find(pair.other).closest('.form-row');

                if ($typeSelect.length) {
                    $typeSelect.on('change', function() {
                        if ($(this).val() === 'Other') {
                            $otherField.show();
                        } else {
                            $otherField.hide();
                        }
                    }).trigger('change');
                }
            });
        });
    }

    /**
     * Handles the logic for showing/hiding address fields based on the selected type in the "Add New Address" form.
     */
    function initializeAddFormAddressTypeToggle() {
        const wrapper = $('#hc_wcma_add_address_fields_wrapper');
        if (!wrapper.length) {
            return;
        }

        const billingFields = wrapper.find('.hc_wcma_billing_fields');
        const shippingFields = wrapper.find('.hc_wcma_shipping_fields');
        const addressTypeSelect = $('#hc_wcma_address_type');
        
        const shippingSameAsBillingCheckbox = $('#shipping_same_as_billing');

        addressTypeSelect.on('change', function () {
            const selectedType = $(this).val();
            billingFields.hide();
            shippingFields.hide();
            $(shippingSameAsBillingCheckbox).val('0');

            if (selectedType === 'billing') {
                billingFields.show();
                toggleNicknameFieldsRequired(selectedType, true);
            } else if (selectedType === 'shipping') {
                console.log('shipping selected');
                shippingFields.show();
                toggleNicknameFieldsRequired(selectedType, true);
            } else if (selectedType === 'both') {
                billingFields.show();
                toggleNicknameFieldsRequired('billing', true);
                wrapper.find('.hc_wcma_shipping_same_as_billing_wrapper').show();
                if (!shippingSameAsBillingCheckbox.is(':checked')) {
                    console.log('shipping selected');
                    toggleNicknameFieldsRequired('shipping', false);
                    shippingFields.show();
                }
            }
            // Trigger country change handler in case fields were hidden
            $(document.body).trigger('country_to_state_changed', ['billing', wrapper]);
            $(document.body).trigger('country_to_state_changed', ['shipping', wrapper]);
            
            if (addressTypeSelect.val() === 'both') {
                shippingSameAsBillingCheckbox.on('change', function() {
                    if ($(this).is(':checked')) {
                        $(this).val('1');
                        shippingFields.hide(); 
                        toggleNicknameFieldsRequired('shipping', false);
                    } else {
                        $(this).val('0');
                        console.log('shipping selected1');
                        toggleNicknameFieldsRequired('shipping', true);
                        shippingFields.show(); 
                    }
                }).trigger('change'); 
            }  
        }).trigger('change');  

              

        $(document.body).trigger('wc_address_i18n_ready');
        $(document.body).trigger('wc_country_select_ready');
    }

    // Run the form field toggle logic on page load.
    initializeAddFormAddressTypeToggle();
    initializeNicknameFieldToggle();
    updateNicknameOptions();
    $('#hc_wcma_address_type, #shipping_same_as_billing').on('change', updateNicknameOptions);

     // --- Swiper Initialization ---
    if (typeof Swiper === 'function') {
        const swiperOptions = {
            slidesPerView: 1,
            spaceBetween: 15,
            loop: false,
            breakpoints: {
                640: {
                  slidesPerView: 1,
                  spaceBetween: 20
                },
                1024: {
                  slidesPerView: 1,
                  spaceBetween: 30
                }
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
        };

        // Initialize Billing Carousel
        if ($('#billing-address-carousel .swiper-slide').length > 0) {
             new Swiper('#billing-address-carousel', {
                 ...swiperOptions,
                 pagination: {
                     el: '.billing-swiper-pagination',
                     clickable: true,
                 },
                 navigation: {
                     nextEl: '.billing-swiper-button-next',
                     prevEl: '.billing-swiper-button-prev',
                 },
             });
        } else {
             $('#billing-address-carousel').hide();
        }

         // Initialize Shipping Carousel
         if ($('#shipping-address-carousel .swiper-slide').length > 0) {
             new Swiper('#shipping-address-carousel', {
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
              $('#shipping-address-carousel').hide();
         }

    } else {
         $('.hc-wcma-address-carousel').addClass('no-swiper-fallback');
    }
    // --- End Swiper Initialization ---

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
                    return true;
                }
            }
            return false;
        };
    
        if (selection === 'billing') {
            limitReached = checkType('billing');
        } else if (selection === 'shipping') {
            limitReached = checkType('shipping');
        } else if (selection === 'both') {
            const billingReached = checkType('billing');
            const shippingReached = checkType('shipping');
            limitReached = billingReached && shippingReached;
        }
    
        if (message) {
            $limitNotice.html(message).show();
            if (limitReached && selection === 'both') {
                 $addSubmitButton.prop('disabled', true);
            } else if (limitReached && (selection === 'billing' || selection === 'shipping')) {
                 $addSubmitButton.prop('disabled', true);
            }
             else {
                 $addSubmitButton.prop('disabled', false);
            }
        } else {
            $limitNotice.hide().html('');
            $addSubmitButton.prop('disabled', false);
        }
    }

    $addressTypeSelect.on('change', checkAddressLimits);
    checkAddressLimits();

    // --- Add Address Form Handling ---
    $('#hc_wcma_add_address_form').on('submit', function (e) {
        e.preventDefault();
        checkAddressLimits();
        if ($addSubmitButton.prop('disabled')) {
            e.preventDefault();
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
            if (field.name !== 'hc_wcma_save_address_nonce' && field.name !== '_wp_http_referer' && field.name !== 'save_address') {
                addressData[field.name] = field.value;
            }
        });

        wp.ajax.send('hc_wcma_add_address', {
            data: {
                nonce: hc_wcma_params.nonce,
                hc_wcma_save_address_nonce: $form.find('#hc_wcma_save_address_nonce').val(),
                address_type_selection: $form.find('#hc_wcma_address_type').val(),
                address_data: addressData,
            },
            success: function (response) {
                $feedback.html(response.message).addClass('woocommerce-message');
                $form[0].reset();
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
                    window.location.reload();
                },
                error: function (error) {
                    alert(error.message || hc_wcma_params.i18n.error);
                    $button.prop('disabled', false).removeClass('loading');
                }
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

    // --- Open Edit Modal ---
    $('.hc-wcma-address-carousel').on('click', '.hc-wcma-edit-button', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $card = $button.closest('.hc-wcma-address-card');
        const addressKey = $card.data('address-key');
        const addressType = $card.data('address-type');
        let addressData = $button.data('address');

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
            return;
        }        

        $editFeedback.html('').removeClass('woocommerce-error woocommerce-message');

        $editForm.find('#hc_wcma_edit_address_key').val(addressKey);
        $editForm.find('#hc_wcma_edit_address_type').val(addressType);

        if (addressType === 'billing') {
            $editForm.find('.hc_wcma_edit_billing_fields').show();
            $editForm.find('.hc_wcma_edit_shipping_fields').hide();
            toggleNicknameFieldsRequired('billing', true);
        } else if (addressType === 'shipping') {
            $editForm.find('.hc_wcma_edit_billing_fields').hide();
            $editForm.find('.hc_wcma_edit_shipping_fields').show();
            toggleNicknameFieldsRequired('shipping', true);
        } else {
            $editForm.find('.hc_wcma_edit_billing_fields').show();
            $editForm.find('.hc_wcma_edit_shipping_fields').show();
        }

        const prefix = addressType + '_';

        // Special handling for nickname
        const nickname = addressData.nickname || '';
        const $nicknameTypeField = $editForm.find('[name="' + prefix + 'nickname_type"]');
        const $nicknameField = $editForm.find('[name="' + prefix + 'nickname"]');
        const currentNicknames = (addressType === 'billing') ? hc_wcma_existing_nicknames.billing : hc_wcma_existing_nicknames.shipping;

        // Temporarily enable all options to set the value
        $nicknameTypeField.find('option').prop('disabled', false);

        if (nickname === 'Home' || nickname === 'Work') {
            $nicknameTypeField.val(nickname);
            $nicknameField.val('');
        } else {
            $nicknameTypeField.val('Other');
            $nicknameField.val(nickname);
        }

        // Now disable the options that are already used by other addresses
        $nicknameTypeField.find('option').each(function() {
            const $option = $(this);
            const optionValue = $option.val();
            if ((optionValue === 'Home' || optionValue === 'Work') && optionValue !== nickname) {
                if (currentNicknames.includes(optionValue)) {
                    $option.prop('disabled', true);
                }
            }
        });

        $.each(addressData, function(key, value) {
            if (key === 'nickname') {
                return; // skip nickname as it's handled above
            }
            const fieldName = prefix + key;
            const $field = $editForm.find('[name="' + fieldName + '"]').not('[type="hidden"]');
            if ($field.length) {
                $field.val(value);
            }
        });

        $editModalOverlay.fadeIn(200);
        $editModal.fadeIn(300, function() {
            $( document.body ).trigger( 'wc_country_select_ready' );
            $( document.body ).trigger( 'wc_address_i18n_ready' );

            setTimeout(function() {   
                const countryFieldName = prefix + 'country';             
                const $countryField = $editForm.find('select[name="' + countryFieldName + '"]');
                if ($countryField.length) {
                    $countryField.trigger('change');
               }
           }, 100);

           const stateFieldName = prefix + 'state';
           const stateValueToSet = addressData.state || '';
          
           setTimeout(function() {
                const $currentStateField = $editForm.find('[name="' + stateFieldName + '"]');
                if ($currentStateField.length && stateValueToSet) {
                    if ($currentStateField.is('select')) {
                        if ($currentStateField.find('option[value="' + stateValueToSet + '"]').length > 0) {
                            $currentStateField.val(stateValueToSet).trigger('change');
                        } else {
                            let availableOptions = [];
                            $currentStateField.find('option').each(function(){ availableOptions.push($(this).val()); });
                        }
                    } else if ($currentStateField.is('input')) {
                        $currentStateField.val(stateValueToSet);
                   }
                }
           }, 1200);

           // Trigger change on nickname type field
           $nicknameTypeField.trigger('change');
       });
    });

    // --- Close Edit Modal ---
    function closeEditModal() {
        $editModal.fadeOut(200);
        $editModalOverlay.fadeOut(300);
        $editForm[0].reset();
    }

    $editModalOverlay.on('click', closeEditModal);
    $editModal.on('click', '#hc_wcma_edit_modal_close', closeEditModal);

    // --- Handle Edit Form Submission ---
    $editForm.on('submit', function(e) {
        e.preventDefault();
        const $button = $editForm.find('button[type="submit"]');

        $button.prop('disabled', true).addClass('loading');
        $editFeedback.html('').removeClass('woocommerce-error woocommerce-message');

        const formData = $editForm.serializeArray();
        const addressData = {};
        $.each(formData, function(i, field) {
            if (field.name !== 'hc_wcma_edit_address_nonce' && field.name !== '_wp_http_referer' && field.name !== 'update_address') {
                addressData[field.name] = field.value;
            }
        });

        const dataToSend = {
            hc_wcma_edit_address_nonce: $editForm.find('#hc_wcma_edit_address_nonce').val(), 
            address_key: $editForm.find('#hc_wcma_edit_address_key').val(),
            address_type: $editForm.find('#hc_wcma_edit_address_type').val(),
            address_data: addressData
        };
    
        wp.ajax.send('hc_wcma_update_address', {
            data: dataToSend,
            success: function(response) {
                $editFeedback.html(response.message).addClass('woocommerce-message');
                if (response.reload) {
                    setTimeout(function() { window.location.reload(); }, 1500);
                } else {
                    closeEditModal();
                }
            },
            error: function(error) {
                $editFeedback.html(error.message || hc_wcma_params.i18n.error).addClass('woocommerce-error');
                $button.prop('disabled', false).removeClass('loading');
            }
        });
    });

    $editForm.on('change', 'select.country_to_state', function() {
        setTimeout(function() {
            $editForm.find('select.select2-hidden-accessible').each(function() {
                const $originalSelect = $(this);
                const $select2Container = $originalSelect.next('.select2-container'); 
                if ($select2Container.length) {
                    $select2Container.remove(); 
                    $originalSelect.removeClass('select2-hidden-accessible')
                                   .css({
                                       position: '', 
                                       top: '',
                                       left: '',
                                       width: '',
                                       height: ''
                                    })
                                    .removeAttr('aria-hidden')
                                    .removeAttr('tabindex');
                }
            });
        }, 100);
    });
});
