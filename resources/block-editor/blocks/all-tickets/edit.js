const { useBlockProps } = wp.blocks;
const { TextControl, ToolbarButton, ToolbarGroup} = wp.components;
const {  RichText, BlockControls, AlignmentToolbar } = wp.blockEditor;
const { Fragment, useEffect } = wp.element;

import Inspector from './inspector';
export default function Edit( { attributes, setAttributes } ) {
    const allBtnStyle = {
        backgroundColor: attributes.buttonAllBgColor,
        color: attributes.buttonAllTextColor,
        padding: '10px 20px',
    }

    const openBtnStyle = {
        backgroundColor: attributes.buttonOpenBgColor,
        color: attributes.buttonOpenTextColor,
        padding: '10px 20px',
    }

    const closedBtnStyle = {
        backgroundColor: attributes.buttonClosedBgColor,
        color: attributes.buttonClosedTextColor,
        padding: '10px 20px',
    }

    const createTicketBtnStyle = {
        backgroundColor: attributes.buttonCreateTicketBgColor,
        color: attributes.buttonCreateTicketTextColor,
        padding: '10px 20px',
    }
    return (
        <Fragment>
            <Inspector attributes={attributes} setAttributes={setAttributes} />
            <button style={allBtnStyle}>
                All
            </button>
            <button style={openBtnStyle}>
                Open
            </button>
            <button style={closedBtnStyle}>
                Closed
            </button>

            <button style={createTicketBtnStyle}>
                Create a New Ticket
            </button>
        </Fragment>
    );
}
