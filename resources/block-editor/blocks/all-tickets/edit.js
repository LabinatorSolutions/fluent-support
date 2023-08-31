const { useBlockProps } = wp.blocks;
const { TextControl, ToolbarButton, ToolbarGroup} = wp.components;
const {  RichText, BlockControls, AlignmentToolbar } = wp.blockEditor;

const { apiFetch } = window.wp;
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

    const state = {
        saving: false,
        saved: false,
    }
    let apiCallTimeout;
    let apiCallInProgress = false;

    wp.data.subscribe(() => {
        const isSavingPost = wp.data.select('core/editor').isSavingPost();
        const isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();
        if (isSavingPost && !isAutosavingPost && !apiCallInProgress) {
            let restUrl = window.rest_url.url.replace('%2F', '');
            console.log(restUrl);
            apiCallInProgress = true;

            clearTimeout(apiCallTimeout);

            apiCallTimeout = setTimeout(() => {
                apiFetch({
                    path: 'index.php?rest_route=/fluent-support/v2/settings/custom-css',
                    method: 'POST',
                    data: {
                        css: attributes.customCss,
                    }
                }).then((response) => {
                    console.log(response);
                }).catch((error) => {
                    console.log(error);
                }).finally(() => {
                    apiCallInProgress = false;
                    state.saved = true;
                    state.saving = false;
                });
            }, 5000); // Adjust the timeout as needed
        }
    });




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
