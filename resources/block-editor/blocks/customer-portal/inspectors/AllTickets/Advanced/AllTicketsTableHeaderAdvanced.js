const {__} = wp.i18n;
const {PanelBody} = wp.components;
import icons from "../../../utils/icons";
import EnhancedTabPanel from "../../../utils/EnhancedTabPanel";
export default function AllTicketsTableHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <EnhancedTabPanel attributes={attributes} setAttributes={setAttributes} props={{
                title: __('Text Align', 'fluent-support'),
                attributeName: 'allTicketsTableHeaderTextAlign',
                icons: {
                    left: icons.TextAlignLeft,
                    center: icons.TextAlignCenter,
                    right: icons.TextAlignRight,
                }
            }}/>
        </PanelBody>
    )
}
