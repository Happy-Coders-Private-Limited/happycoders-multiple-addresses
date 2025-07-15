import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import metadata from './block.json'; // Import metadata from block.json

registerBlockType(metadata.name, {
    edit: ({ attributes, setAttributes }) => {
        const { addressType } = attributes;
        const blockProps = useBlockProps(); // Apply necessary classes etc.

        return (
            <>
                {/* Settings Sidebar Controls */}
                <InspectorControls>
                    <PanelBody title={__('Selector Settings', 'happycoders-multiple-addresses')}>
                        <SelectControl
                            label={__('Address Type', 'happycoders-multiple-addresses')}
                            help={__('Select whether this block handles Billing or Shipping addresses.', 'happycoders-multiple-addresses')}
                            value={addressType}
                            options={[
                                { label: __('Billing', 'happycoders-multiple-addresses'), value: 'billing' },
                                { label: __('Shipping', 'happycoders-multiple-addresses'), value: 'shipping' },
                            ]}
                            onChange={(newType) => setAttributes({ addressType: newType })}
                        />
                    </PanelBody>
                </InspectorControls>

                {/* Editor Placeholder */}
                <div {...blockProps}>
                    <p style={{ padding: '10px', border: '1px dashed #ccc', backgroundColor: '#f9f9f9' }}>
                        <strong>{__('HC Address Selector', 'happycoders-multiple-addresses')}</strong><br />
                        <small><em>({sprintf(__('Type: %s', 'happycoders-multiple-addresses'), addressType)})</em></small><br />
                        <small>{__('(Displays address selection on frontend checkout)', 'happycoders-multiple-addresses')}</small>
                    </p>
                </div>
            </>
        );
    },
    // No save function needed - dynamic block rendered via 'script' from block.json
    save: () => null,
});
