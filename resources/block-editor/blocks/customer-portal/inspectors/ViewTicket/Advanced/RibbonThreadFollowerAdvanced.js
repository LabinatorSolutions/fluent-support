import RibbonThreadFollowerStyle
    from "@/block-editor/blocks/customer-portal/inspectors/ViewTicket/Style/RibbonThreadFollowerStyle";

const {__} = wp.i18n;
const {PanelBody, RangeControl} = wp.components;
export default function RibbonThreadFollowerAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Thread Follower', 'fluent-support')}>
            <p><strong>{__('Ribbon Tail Width', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadFollowerTailWidth }
                onChange={(v) => setAttributes({ viewTicketThreadFollowerTailWidth: v })}
                min={ 1 }
                max={ 10 }
            />

            <p><strong>{__('Padding Top', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadFollowerPaddingTop }
                onChange={(v) => setAttributes({ viewTicketThreadFollowerPaddingTop: v })}
                min={ 0 }
                max={ 15 }
            />

            <p><strong>{__('Padding Bottom', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadFollowerPaddingBottom }
                onChange={(v) => setAttributes({ viewTicketThreadFollowerPaddingBottom: v })}
                min={ 0 }
                max={ 15 }
            />
            <p><strong>{__('Padding Right', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadFollowerPaddingRight }
                onChange={(v) => setAttributes({ viewTicketThreadFollowerPaddingRight: v })}
                min={ 0 }
                max={ 15 }
            />

            <p><strong>{__('Padding Left', 'fluent-support')}</strong></p>
            <RangeControl
                value={ attributes.viewTicketThreadFollowerPaddingLeft }
                onChange={(v) => setAttributes({ viewTicketThreadFollowerPaddingLeft: v })}
                min={ 0 }
                max={ 15 }
            />
        </PanelBody>
    )
}
