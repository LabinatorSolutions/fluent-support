import StyleInspectorControls from "./StyleInspectorControls";
import AdvancedInspectorControls from "./AdvancedInspectorControls";

const { __ } = wp.i18n;
const { InspectorControls } = wp.blockEditor;
const { TabPanel, PanelBody } = wp.components;
const Inspector = (inspectorProps) => {
    const { attributes, setAttributes, mailboxes, selectedInspector} = inspectorProps;

    return (
        <InspectorControls>
            <div>
                <TabPanel
                    className="fst-block-tab-panels"
                    activeClass="fst-block-active-tab"
                    tabs={[
                        {
                            name: "styleInspector",
                            title: "Style",
                            className: "fst-block-tab-panel"
                        },
                        {
                            name: "advancedInspector",
                            title: "Advanced",
                            className: "fst-block-tab-panel"
                        }
                    ]}
                >

                    {tab => {
                        let tabLayout;
                        if (tab.name === "styleInspector") {
                            tabLayout = <StyleInspectorControls  attributes={attributes} setAttributes={setAttributes} selectedInspector={selectedInspector}/>;
                        } else {
                            tabLayout = <AdvancedInspectorControls attributes={attributes} setAttributes={setAttributes} selectedInspector={selectedInspector}/>;
                        }
                        return tabLayout;
                    }}
                </TabPanel>

                <PanelBody title={__('Default Business Inbox', 'fluent-support')} initialOpen={ false }>
                    <select value={attributes.businessBoxId}
                            onChange={(v) => setAttributes({ businessBoxId: v.target.value })}
                    >
                        <option value="">{__('Select a mailbox', 'fluent-support')}</option>
                        {mailboxes.map((mailbox) => {
                            return (
                                <option value={mailbox.id} key={mailbox.id}>{mailbox.name +' ('+ mailbox.box_type +')' }</option>
                            );
                        })}
                    </select>
                </PanelBody>
            </div>
        </InspectorControls>
    );
};

export default Inspector;
