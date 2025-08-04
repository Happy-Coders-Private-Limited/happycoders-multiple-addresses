import { render, useState, useEffect, useCallback, Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, dispatch, useDispatch } from '@wordpress/data';
import { SelectControl, RadioControl } from '@wordpress/components';
import './style.scss';

// Get localized params (check if available)
const params = window.hc_wcma_block_params || {}; // Use params localized in PHP
const i18n = params.i18n || {};
// console.log("[HCMA Blocks Frontend] Initializing. Params:", params);

// --- !! VERIFY STORE KEYS & ACTIONS !! ---
const CART_STORE_KEY = 'wc/store/cart';
// --- End Verification ---

// --- Frontend React Component ---
const AddressSelectorFrontend = ({ addressType = 'billing' }) => {
    // console.log(`[HCMA Blocks Frontend] Rendering for type: ${addressType}`);

       // --- State & Data ---
    // Find the specific default key for this instance type
    const initialDefaultKey = params.defaultKeys?.[addressType] || '';
    const [selectedKey, setSelectedKey] = useState(initialDefaultKey);
    const [loading, setLoading] = useState(false);
    const allAddresses = params.addresses || {};

    // Ensure savedAddresses is an array, handling potential empty object from PHP
    const addressesForType = allAddresses[addressType] || {};
    const savedAddresses = Object.keys(addressesForType).length > 0 ? Object.values(addressesForType) : [];

    const allowNew = params.allow_new === 'yes';
    const selectorStyle = params.selector_style || 'dropdown';

    // console.log(`[HCMA Blocks Frontend ${addressType}] Initial Default Key:`, initialDefaultKey);
    // console.log(`[HCMA Blocks Frontend ${addressType}] Current Selected Key (State):`, selectedKey);

    const updateWcAddress = useCallback((addressData) => {
        setLoading(true);
        const actionPayload = { [addressType + '_address']: addressData };
        // console.log(`[HCMA Blocks ${addressType}] Dispatching action with payload:`, actionPayload);
        return dispatch(CART_STORE_KEY)
            .updateCustomerData(actionPayload, false) // Assuming updateCustomerData is correct
            .then(() => {
                // console.log(`[HCMA Blocks ${addressType}] Dispatch successful.`);
                setLoading(false);
            })
            .catch((err) => {
                // console.error(`[HCMA Blocks ${addressType}] Dispatch ERROR:`, err);
                setLoading(false);
                // Rethrow or handle error appropriately
                throw err; 
            });
    }, [dispatch, addressType]); // Add dispatch as dependency

    // --- Handle selection change ---
    const handleSelectionChange = useCallback((newKey) => {
        setSelectedKey(newKey);
        // console.log(`[HCMA Block Frontend ${addressType}] Selection changed to: ${newKey}`);
        setLoading(true);

        if (newKey === 'new' || newKey === '') {
            // console.log(`[HCMA Block Frontend ${addressType}] Clearing address in store.`);
                const clearAddress = { /* ... map fields EXACTLY as required by updateAddressAction ... */ };
                clearAddress.first_name = '';
                clearAddress.last_name = '';
                clearAddress.company = '';
                clearAddress.address_1 = '';
                clearAddress.address_2 = '';
                clearAddress.city = '';
                clearAddress.state = '';
                clearAddress.postcode = '';
                clearAddress.country = '';
                clearAddress.phone = '';
                clearAddress.email = '';
                updateWcAddress(clearAddress)
                .then(() => { setLoading(false); setEditingState(addressType, true); }) // Show form on success
                .catch(() => { setLoading(false); setEditingState(addressType, true) }); // Show form on error too
            
        } else {
            const selectedAddress = allAddresses[addressType]?.[newKey];
            if (selectedAddress) {
                // console.log(`[HCMA Block Frontend ${addressType}] Found saved address:`, selectedAddress);
                // --- Format address for store ---
                const addressForStore = { /* ... map fields EXACTLY as required by updateAddressAction ... */ };
                 addressForStore.first_name = selectedAddress.first_name || '';
                 addressForStore.last_name = selectedAddress.last_name || '';
                 addressForStore.company = selectedAddress.company || '';
                 addressForStore.address_1 = selectedAddress.address_1 || '';
                 addressForStore.address_2 = selectedAddress.address_2 || '';
                 addressForStore.city = selectedAddress.city || '';
                 addressForStore.state = selectedAddress.state || '';
                 addressForStore.postcode = selectedAddress.postcode || '';
                 addressForStore.country = selectedAddress.country || '';
                 addressForStore.phone = selectedAddress.phone || '';
                 addressForStore.email = selectedAddress.email || '';
                // --- End Formatting ---
                // console.log(`[HCMA Block Frontend ${addressType}] Dispatching update to store:`, addressForStore);
                updateWcAddress(addressForStore)
                    .then(() => { setLoading(false); setEditingState(addressType, false) }) // Show card on success
                    .catch(() => { setLoading(false); setEditingState(addressType, true) });
                 // TODO: Signal parent block to HIDE fields if needed
            } else {
                console.error(`[HCMA Block Frontend ${addressType}] Could not find address data for key:`, newKey);
                setEditingState(addressType, true);
                setLoading(false); 
            }
        }
    }, [addressType, allAddresses, updateWcAddress]); // Dependencies

    useEffect(() => {
        // console.log(`[HCMA Blocks ${addressType}] Initial load effect. Default Key:`, initialDefaultKey);
        // Check if initialDefaultKey exists and is valid in the saved addresses
        if (initialDefaultKey && addressesForType[initialDefaultKey]) {
            //  console.log(`[HCMA Blocks ${addressType}] Valid default key found. Setting state and dispatching update.`);
             setSelectedKey(initialDefaultKey); // Set dropdown state

             // Format the default address for the store
             const defaultAddressData = addressesForType[initialDefaultKey];
             const addressForStore = { /* ... map fields from defaultAddressData ... */ };
              addressForStore.first_name = defaultAddressData.first_name || '';
              addressForStore.last_name = defaultAddressData.last_name || '';
              addressForStore.company = defaultAddressData.company || '';
              addressForStore.address_1 = defaultAddressData.address_1 || '';
              addressForStore.address_2 = defaultAddressData.address_2 || '';
              addressForStore.city = defaultAddressData.city || '';
              addressForStore.state = defaultAddressData.state || '';
              addressForStore.postcode = defaultAddressData.postcode || '';
              addressForStore.country = defaultAddressData.country || '';
              addressForStore.phone = defaultAddressData.phone || '';
              addressForStore.email = defaultAddressData.email || '';

             // Dispatch the update to overwrite WC's default (e.g., last order address)
             updateWcAddress(addressForStore)
                .then(() => setEditingState(addressType, false)) // Show form on success
                .catch(() => setEditingState(addressType, true));
        } else {
            // No valid default key, ensure form is shown initially
            console.log(`[HCMA Blocks ${addressType}] No valid default key found. Ensuring form is visible.`);
             setSelectedKey(''); // Ensure dropdown shows '-- Select --'
             setEditingState(addressType, true);
        }
    // Run only once on initial mount (or if initialDefaultKey/address data somehow changes)
    }, [initialDefaultKey, addressType, addressesForType, updateWcAddress]); 

    // --- Prepare options ---
    // console.log(`[HCMA Blocks Frontend ${addressType}] Preparing options. Saved Addresses Count:`, savedAddresses.length);
    // console.log(`[HCMA Blocks Frontend ${addressType}] Allow New:`, allowNew);

    const options = [];
    // const selectLabel = addressType === 'billing' ? (i18n.select_billing || '-- Select Billing Address --') : (i18n.select_shipping || '-- Select Shipping Address --');
    // options.push({ label: selectLabel, value: '' });
     // Add saved addresses
     savedAddresses.forEach(addr => {
        // Use addr.key which PHP added
        if (!addr.key) {
             console.warn(`[HCMA Blocks Frontend ${addressType}] Address missing 'key':`, addr);
             return; // Skip if key is missing
        }
        let label = addr.nickname || addr.address_1 || addr.city || addr.key; // Improved label fallback
        if (addr.key === initialDefaultKey) {
            label += ` ${i18n.default_label || '(Default)'}`;
        }
        options.push({ label: label, value: addr.key });
    });
    
    // Conditionally add "Enter New Address" option
    if (allowNew) {
        options.push({ label: i18n.new_address || 'Enter a new address', value: 'new' });
    }
    // console.log(`[HCMA Blocks Frontend ${addressType}] Final options count:`, options.length);


    // --- Check if component should render ---
    if (options.length < 1) {
        console.log(`[HCMA Blocks Frontend ${addressType}] Conditions not met for display (options.length=${options.length}, allowNew=${allowNew}). Returning null.`);
        return null; // Render nothing if no actual addresses to choose from
    }

    // --- Render the component ---
    // console.log(`[HCMA Blocks Frontend ${addressType}] Rendering Select/Radio control. Selected Key: ${selectedKey}`);
    try {
        return (
            // Added CSS class from block.json name for styling wrapper
            <div className={`hc-wcma-block-checkout-selector hc-wcma-${addressType}-selector ${loading ? 'is-loading' : ''} wp-block-hc-wcma-address-selector`}>
                <div style={{ marginBottom: '1em', opacity: loading ? 0.5 : 1, pointerEvents: loading ? 'none' : 'auto' }}>
                {selectorStyle === 'list' ? (
                    <RadioControl
                        // label={selectLabel} // Label often redundant if header exists
                        selected={selectedKey}
                        options={options}
                        onChange={handleSelectionChange}
                        className="hc-wcma-address-radio-list"
                    />
                ) : (
                    <SelectControl
                        // label={selectLabel} // Label often redundant if header exists
                        value={selectedKey}
                        options={options}
                        onChange={handleSelectionChange}
                        className="hc-wcma-address-select"
                        // Help text if needed: __experimental__help={ __('Select a saved address or enter a new one.', 'happycoders-multiple-addresses')}
                    />
                )}
                </div>
                {loading && <p className="hc-wcma-loading-text">{i18n.loading || 'Loading...'}</p>}
            </div>
        );
    } catch (error) {
        console.error(`[HCMA Blocks Frontend ${addressType}] Error during render:`, error);
        return <p style={{color: 'red', border: '1px solid red', padding: '5px'}}>Error rendering HCMA selector!</p>; // Render an error message
    }
};

function mountAddressSelectors() {
    // We need the localized data to know which addresses exist
    const addresses = params.addresses || {};
    const billingAddressesExist = addresses.billing && Object.keys(addresses.billing).length > 0;
    const shippingAddressesExist = addresses.shipping && Object.keys(addresses.shipping).length > 0;
    const allowNew = params.allow_new === 'yes';

    // --- Mount Billing Selector ---
    // Only mount if there are addresses or 'new' is allowed
    if (billingAddressesExist || allowNew) {
         // Find the TARGET PARENT block where the billing selector should go
        const billingParentTarget = document.querySelector('.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-checkout-step__content'); // Adjust selector if needed!

        if (billingParentTarget) {
            // Create a NEW container div for React each time
            const billingMountPoint = document.createElement('div');
            billingMountPoint.className = 'hc-wcma-billing-mount-point'; // Add class for styling/identification
            // Prepend it inside the target parent
            billingParentTarget.prepend(billingMountPoint);
            // console.log('[HCMA Blocks Frontend] Attempting to mount BILLING component into:', billingParentTarget);

             // Check if already mounted to prevent errors on HMR etc.
             if (!billingMountPoint.dataset.reactMounted) {
                 try {
                      render(<AddressSelectorFrontend addressType="billing" />, billingMountPoint);
                      billingMountPoint.dataset.reactMounted = 'true';
                    //   console.log('[HCMA Blocks Frontend] Billing component mounted.');
                 } catch (error) { console.error('[HCMA Blocks Frontend] Error mounting billing component:', error); }
             }
         } else {
              console.warn('[HCMA Blocks Frontend] Billing parent target not found.');
         }
    } else {
         console.log('[HCMA Blocks Frontend] Skipping Billing selector - no addresses and new not allowed.');
    }


     // --- Mount Shipping Selector ---
     // Only mount if there are addresses or 'new' is allowed AND shipping is needed? (Need shipping status)
     // TODO: Get shipping visibility status from WC Blocks data store if possible
     const shouldMountShipping = shippingAddressesExist || allowNew; // Add check for needsShipping later

     if (shouldMountShipping) {
         const shippingParentTarget = document.querySelector('.wp-block-woocommerce-checkout-shipping-address-block .wc-block-components-checkout-step__content'); // Adjust selector if needed!

         if (shippingParentTarget) {
             const shippingMountPoint = document.createElement('div');
             shippingMountPoint.className = 'hc-wcma-shipping-mount-point';
             shippingParentTarget.prepend(shippingMountPoint);
            //   console.log('[HCMA Blocks Frontend] Attempting to mount SHIPPING component into:', shippingParentTarget);

              if (!shippingMountPoint.dataset.reactMounted) {
                  try {
                       render(<AddressSelectorFrontend addressType="shipping" />, shippingMountPoint);
                       shippingMountPoint.dataset.reactMounted = 'true';
                    //    console.log('[HCMA Blocks Frontend] Shipping component mounted.');
                  } catch (error) { console.error('[HCMA Blocks Frontend] Error mounting shipping component:', error); }
              }
          } else {
               console.warn('[HCMA Blocks Frontend] Shipping parent target not found.');
          }
     } else {
          console.log('[HCMA Blocks Frontend] Skipping Shipping selector.');
     }
     setTimeout(hideWcEditButtons, 100);
} // End mountAddressSelectors

function hideWcEditButtons() {
    // console.log('[HCMA Blocks Frontend] Attempting to hide WC Edit buttons...');
    const editButtons = document.querySelectorAll('.wc-block-components-address-card .wc-block-components-address-card__edit');
    if (editButtons.length > 0) {
         editButtons.forEach(button => {
             button.style.display = 'none';
         });
        //  console.log(`[HCMA Blocks Frontend] Hide ${editButtons.length} WC Edit button(s).`);
    } else {
        // console.log('[HCMA Blocks Frontend] No WC Edit buttons found to hide.');
    }
}

function mountSingleAddressSelector(addressType) {
    // logWithTimestamp(`Attempting to mount selector for type: ${addressType}`);
    const addresses = params.addresses || {};
    const addressesExist = addresses[addressType] && Object.keys(addresses[addressType]).length > 0;
    const allowNew = params.allow_new === 'yes';

    if (!addressesExist && !allowNew) { /* ... skip message ... */ return; }

    // --- Find PARENT Block ---
    const parentSelector = `.wp-block-woocommerce-checkout-billing-address-block .wc-block-components-checkout-step__content`;
    const parentTarget = document.querySelector(parentSelector);

    if (parentTarget) {
        // Find or Create Mount Point
        let mountPoint = parentTarget.querySelector(`.hc-wcma-${addressType}-mount-point`);
        if (!mountPoint) {
            mountPoint = document.createElement('div');
            mountPoint.className = `hc-wcma-${addressType}-mount-point`;
             // --- Inject relative to a specific inner element if possible ---
             // Example: Inject before the checkout step content div
             const contentDiv = parentTarget.querySelector('.wc-block-components-checkout-step__content');
             if (contentDiv) {
                 contentDiv.parentNode.insertBefore(mountPoint, contentDiv); // Insert before content
                //  logWithTimestamp(`Prepended mount point for ${addressType} before content div.`);
             } else {
                 parentTarget.prepend(mountPoint); // Fallback to prepending to parent
                //  logWithTimestamp(`Prepended mount point for ${addressType} to parent.`);
             }
        } else {
             logWithTimestamp(`Mount point already exists for ${addressType}.`);
        }

        // Mount React component
        if (!mountPoint.dataset.reactMounted) {
            try {
                 render(<AddressSelectorFrontend addressType={addressType} />, mountPoint);
                 mountPoint.dataset.reactMounted = 'true';

                 setEditingState(addressType, false);
                //  logWithTimestamp(`${addressType} component mounted.`);
            } catch (error) { console.error(`Error mounting ${addressType} component:`, error); }
        } else { /* ... already mounted log ... */ }
    } else { /* ... parent not found log ... */ }
}

// --- Helper: Toggle Editing Class ---
function setEditingState(addressType, isEditing) {
    // --- !! VERIFY THIS SELECTOR !! ---
    const wrapperSelector = `#${addressType}-fields .wc-block-components-address-address-wrapper`; 
    const wrapper = document.querySelector(wrapperSelector);
    if (wrapper) {
        if (isEditing) {
            wrapper.classList.add('is-editing');
            // console.log(`[HCMA Blocks ${addressType}] Added 'is-editing' class`);
        } else {
            wrapper.classList.remove('is-editing');
            // console.log(`[HCMA Blocks ${addressType}] Removed 'is-editing' class`);
            setTimeout(() => hideWcEditButtons(addressType), 100); // Hide WC edit button when showing card
        }
    } else {
        console.warn(`[HCMA Blocks ${addressType}] Wrapper '${wrapperSelector}' not found to toggle is-editing.`);
    }
}

// --- *** Mutation Observer to Detect Billing Block Re-Add *** ---
let checkoutObserver = null;

function startCheckoutObserver() {
    // Target an element guaranteed to contain the checkout blocks
    const checkoutContainer = document.querySelector('.wp-block-woocommerce-checkout'); // Or 'form.woocommerce-checkout' ?
    if (!checkoutContainer) { console.warn('[HCMA Blocks] Checkout container not found for observer.'); return; }
    if (checkoutObserver) { logWithTimestamp('Checkout observer already running.'); return; } // Prevent multiple observers

    // logWithTimestamp('Starting checkout DOM observer...');
    checkoutObserver = new MutationObserver((mutationsList) => {
        let billingBlockAdded = false;
        for (const mutation of mutationsList) {
             // Check if nodes were added AND if the billing block was among them or their children
             if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                 mutation.addedNodes.forEach(node => {
                     if (node.nodeType === 1) { 
                          if (node.matches('.wp-block-woocommerce-checkout-billing-address-block') || node.querySelector('.wp-block-woocommerce-checkout-billing-address-block')) {
                              billingBlockAdded = true;
                          }
                     }
                  });
             }
             if (billingBlockAdded) break; // Stop checking mutations if found
        }

        // If the billing block was added in this batch of mutations
        if (billingBlockAdded) {
            // logWithTimestamp('Billing address block RE-ADDED, attempting to re-mount selector...');
            // Wait a tiny bit for WC scripts potentially, then remount
            setTimeout(() => mountSingleAddressSelector('billing'), 150); 
        }
    });

    checkoutObserver.observe(checkoutContainer, { childList: true, subtree: true });
}

function logWithTimestamp(message, ...args) {
    const now = new Date();
    // Format time as HH:MM:SS.milliseconds (e.g., 14:05:21.123)
    const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}.${String(now.getMilliseconds()).padStart(3, '0')}`;
    
    // Use console.log, passing the formatted prefix and any additional arguments
    console.log(`[${time}] ${message}`, ...args);
}

document.addEventListener('DOMContentLoaded', () => {
    // console.log('[HCMA Blocks Frontend] DOMContentLoaded fired. Waiting slightly to mount...');
    setTimeout(mountAddressSelectors, 750); // Wait 500ms for WC Blocks to potentially render
    setTimeout(startCheckoutObserver, 1500);
});