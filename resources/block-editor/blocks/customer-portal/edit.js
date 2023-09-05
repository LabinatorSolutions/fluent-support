const { useBlockProps } = wp.blocks;
const { TextControl, ToolbarButton, ToolbarGroup} = wp.components;
const {  RichText, BlockControls, AlignmentToolbar } = wp.blockEditor;

const { apiFetch } = window.wp;
const { Fragment, useEffect } = wp.element;
//Import styles
import './editor.scss';
//Import Inspector controls
import Inspector from './inspector';
export default function Edit( { attributes, setAttributes } ) {
    const ticketHeaderStyle = {
        backgroundColor: attributes.ticketHeaderBgColor,
    }
    const ticketHeaderLeftStyle = {
        float: 'left',
    }
    const ticketHeaderRightStyle = {
        float: 'right',
    }
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
            <div className={'block-wrapper'}>
                <div className={'ticket-header'} style={ticketHeaderStyle}>
                    <div className={'ticket-header-left'} style={ticketHeaderLeftStyle}>
                        <button style={allBtnStyle}>
                            All
                        </button>
                        <button style={openBtnStyle}>
                            Open
                        </button>
                        <button style={closedBtnStyle}>
                            Closed
                        </button>
                    </div>
                    <div className={'ticket-header-right'} style={ticketHeaderRightStyle}>
                        <button style={createTicketBtnStyle}>
                            Create a New Ticket
                        </button>
                    </div>
                </div>
                <div className={'ticket-tables'}>
                    <table className={'ticket-table'}>
                        <thead className={'ticket-table-header'}>
                            <tr>
                                <th>Conversation</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Test Ticket</td>
                                <td>General</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Test Ticket</td>
                                <td>General</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div className={'ticket-footer'}>

                </div>
            </div>
        </Fragment>
    );
}
