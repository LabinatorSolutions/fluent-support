import InspectorContainer from "../utils/InspectorContainer";
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
                        if (tab.name === "styleInspector") {
                            let selectedInd = selectedInspector.replace('Advanced', 'Style');
                            return InspectorContainer({attributes, setAttributes})[selectedInd];
                        } else {
                            let selectedInd = selectedInspector.replace('Style', 'Advanced');
                            return InspectorContainer({attributes, setAttributes})[selectedInd];
                        }
                    }}
                </TabPanel>

                <PanelBody title={__('Default Business Inbox', 'fluent-support')}>
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
