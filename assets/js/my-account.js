/* global hc_wcma_params, jQuery, wp */
jQuery(function ($) {
    'use strict';
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

        addressTypeSelect.on('change', function () {
            const selectedType = $(this).val();
            billingFields.hide();
            shippingFields.hide();

            if (selectedType === 'billing' || selectedType === 'both') {
                billingFields.show();
            }
            if (selectedType === 'shipping' || selectedType === 'both') {
                shippingFields.show();
            }
            // Trigger country change handler in case fields were hidden
            $(document.body).trigger('country_to_state_changed', ['billing', wrapper]);
            $(document.body).trigger('country_to_state_changed', ['shipping', wrapper]);

        }).trigger('change');

        $(document.body).trigger('wc_address_i18n_ready');
        $(document.body).trigger('wc_country_select_ready');
    }

    // Run the form field toggle logic on page load.
    initializeAddFormAddressTypeToggle();

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
        } else if (addressType === 'shipping') {
            $editForm.find('.hc_wcma_edit_billing_fields').hide();
            $editForm.find('.hc_wcma_edit_shipping_fields').show();
        } else {
            $editForm.find('.hc_wcma_edit_billing_fields').show();
            $editForm.find('.hc_wcma_edit_shipping_fields').show();
        }

        const prefix = addressType + '_';
        $.each(addressData, function(key, value) {
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