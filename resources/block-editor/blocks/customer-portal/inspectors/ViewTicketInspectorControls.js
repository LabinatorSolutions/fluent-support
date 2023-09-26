import StyleInspectorControls from "@/block-editor/blocks/customer-portal/inspectors/StyleInspectorControls";

const { __ } = wp.i18n;
const { TabPanel } = wp.components;
export default function ViewTicketInspectorControls({ attributes, setAttributes, selectedInspector }) {
    return (
        <div>
            <TabPanel
                className="fst-block-tab-panels"
                activeClass="active-tab"
                tabs={[
                    {
                        name: "styleInspector",
                        title: "Style",
                        className: "tab-panel"
                    },
                    {
                        name: "advancedInspector",
                        title: "Advanced",
                        className: "tab-panel"
                    }
                ]}
            >

                {tab => {
                    let tabLayout;
                    if (tab.name === "styleInspector") {
                        tabLayout = <StyleInspectorControls  attributes={attributes} setAttributes={setAttributes} selectedInspector={selectedInspector}/>;
                    } else {
                        tabLayout = '<div>Advanced Layout for view Tickets</div>';
                    }
                    return tabLayout;
                }}
            </TabPanel>
        </div>
    )
}
