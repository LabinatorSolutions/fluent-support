const { __ } = wp.i18n;
const { TabPanel } = wp.components;
import StyleInspectorControls from './StyleInspectorControls';
export default function AllTicketsInspectorControls({ attributes, setAttributes, selectedInspector }) {
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
                        tabLayout = '<div>Content for Tab 3</div>';
                    }
                    return tabLayout;
                }}
            </TabPanel>
        </div>
        );
}
