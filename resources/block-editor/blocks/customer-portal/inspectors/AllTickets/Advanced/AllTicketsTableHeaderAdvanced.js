const {__} = wp.i18n;
const {PanelBody} = wp.components;
import icons from "../../../utils/icons";
export default function AllTicketsTableHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <p><strong>{__('Text Align', 'fluent-support')}</strong></p>
            <div className="alignment-buttons-group">
                <button
                    className={`alignment-button left-button ${attributes.allTicketsTableHeaderTextAlign === 'left' ? 'active' : ''}`}
                    onClick={() => setAttributes({allTicketsTableHeaderTextAlign: 'left'})}
                >{icons.TextAlignLeft}</button>
                <button
                    className={`alignment-button center-button ${attributes.allTicketsTableHeaderTextAlign === 'center' ? 'active' : ''}`}
                    onClick={() => setAttributes({allTicketsTableHeaderTextAlign: 'center'})}
                >{icons.TextAlignCenter}</button>
                <button
                    className={`alignment-button right-button ${attributes.allTicketsTableHeaderTextAlign === 'right' ? 'active' : ''}`}
                    onClick={() => setAttributes({allTicketsTableHeaderTextAlign: 'right'})}
                >{icons.TextAlignRight}</button>
            </div>
        </PanelBody>
    )
}
