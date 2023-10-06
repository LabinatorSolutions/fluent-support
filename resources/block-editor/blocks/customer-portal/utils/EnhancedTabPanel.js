const { __ } = wp.i18n;
const { Fragment } = wp.element;
const { TabPanel } = wp.components;
export default function EnhancedTabPanel({attributes, setAttributes, props}){
    const {title, attributeName, icons} = props;
    return (
        <Fragment>
            <div className="fst-text-alignment-buttons-group">
                <p>{title}</p>
                <TabPanel
                    className="alignment-buttons-group"
                    activeClass="active"
                    tabs={[
                        {
                            name: 'left',
                            title: icons.left,
                            className: `alignment-button left-button`,
                        },
                        {
                            name: 'center',
                            title: icons.center,
                            className: `alignment-button center-button`,
                        },
                        {
                            name: 'right',
                            title: icons.right,
                            className: `alignment-button right-button`,
                        },
                    ]}
                    initialTabName={attributes[attributeName]}
                >
                    {tab => {
                        setAttributes({[attributeName] : tab.name})
                    }}
                </TabPanel>
            </div>
        </Fragment>
    )
}
