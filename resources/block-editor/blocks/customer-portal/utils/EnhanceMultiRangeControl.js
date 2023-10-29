const { __ } = wp.i18n;
const { Fragment } = wp.element;
const { TabPanel, RangeControl } = wp.components;
export default function EnhanceMultiRangeControl({attributes, setAttributes, props}){
    const {title, parentAttribute, TopAttribute, RightAttribute, BottomAttribute, LeftAttribute, icons, min, max, inc} = props;
    const setBoxValue = (value, boxName) => {
        if(boxName !== parentAttribute){
            setAttributes({
                [boxName] : parseInt(value)
            })
        }else {
            setAttributes({
                [parentAttribute] : parseInt(value),
                [parentAttribute+TopAttribute] : parseInt(value),
                [parentAttribute+RightAttribute] : parseInt(value),
                [parentAttribute+BottomAttribute] : parseInt(value),
                [parentAttribute+LeftAttribute] : parseInt(value),
            })
        }
    }
    const multiBoxTabSelect = (multiBoxName) => {
        if(multiBoxName && parentAttribute){
            let attr =  parentAttribute;
            multiBoxName !== parentAttribute ? attr += multiBoxName : attr;
            return (
                <div>
                    <RangeControl
                        value={attributes[attr]}
                        onChange={value => setBoxValue(value, attr)}
                        min={min}
                        max={max}
                        step={inc}
                    />
                </div>
            )
        }
    }
    return (
        <Fragment>
            <div className="fst-range-control-multiplebox">
                <p> {title} </p>
                <div className="box-icons">
                    <TabPanel
                        className="multiplebox-tab-panels"
                        activeClass="active-action-tab"
                        tabs={[
                            {
                                name: parentAttribute,
                                title: icons.all,
                                className:`icon icon-action-lock-all`
                            },
                            {
                                name: TopAttribute,
                                title: icons.top,
                                className:`icon icon-action-top`
                            },
                            {
                                name: RightAttribute,
                                title: icons.right,
                                className:`icon icon-action-right`
                            },
                            {
                                name: BottomAttribute,
                                title: icons.bottom,
                                className:`icon icon-action-bottom`
                            },
                            {
                                name: LeftAttribute,
                                title: icons.left,
                                className:`icon icon-action-left`
                            }
                        ]}
                    >
                        {tab => {
                            if (tab.name === TopAttribute ) {
                                return multiBoxTabSelect(TopAttribute)
                            }
                            else if (tab.name === RightAttribute) {
                                return multiBoxTabSelect(RightAttribute)
                            }
                            else if (tab.name === BottomAttribute) {
                                return multiBoxTabSelect(BottomAttribute)
                            }
                            else if(tab.name === LeftAttribute) {
                                return multiBoxTabSelect(LeftAttribute)
                            }else{
                                setBoxValue(attributes[parentAttribute], parentAttribute);
                                return multiBoxTabSelect(parentAttribute)
                            }
                        }}
                    </TabPanel>
                </div>
            </div>
        </Fragment>
    );
}
