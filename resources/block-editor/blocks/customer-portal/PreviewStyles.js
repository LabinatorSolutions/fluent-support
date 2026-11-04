// Mirrors BlockHelper::processAttributesAndPrepareStyle() so the editor preview
// applies the block attributes to the same portal elements the front end does.
// The preview markup uses the portal's classes and the portal CSS (registered as
// an editor style in BlockEditorHandler), so only these overrides live here.
// Scoping to the preview's id gives the rules the same weight as the front end's
// #fluent_support_client_app.

// Attribute values come from post content; keep them to a single CSS value
const cssValue = (value) => String(value).replace(/[<>{};]/g, '');

export const previewCss = (scopeId, attributes) => {
    const scope = `#${scopeId}`;

    return `
${scope} .fs_tickets_container,
${scope} .fs_ticket_form_container {
    border-radius: ${cssValue(attributes.containerBorderRadius)}px;
}
${scope} .fs_create_ticket_container .el-input__wrapper,
${scope} .fs_create_ticket_container .el-select__wrapper,
${scope} .fs_create_ticket_container .fs_block_editor_mock {
    border-radius: ${cssValue(attributes.ticketInputBorderRadius)}px;
}
${scope} .fs_ticket_threads_container .fs_ticket_thread .fs_ticket_thread_content .fs_ticket_avatar {
    border-radius: ${cssValue(attributes.avatarBorderRadius)};
}
${scope} .fs_create_ticket_btn,
${scope} .fs_create_ticket_button,
${scope} .fs_reply_btn {
    background-color: ${cssValue(attributes.primaryButtonBgColor)};
    color: ${cssValue(attributes.primaryButtonTextColor)};
}
${scope} .fs_ticket_refresh_btn,
${scope} .fs_upload_button {
    background-color: ${cssValue(attributes.secondaryButtonBgColor)};
    color: ${cssValue(attributes.secondaryButtonTextColor)};
}
${scope} .fs_ticket_refresh_btn span,
${scope} .fs_upload_button span {
    color: inherit !important;
}
${scope} .fs_close_ticket {
    background-color: ${cssValue(attributes.secondaryButtonBgColor)};
    color: ${cssValue(attributes.secondaryButtonTextColor)};
    opacity: 0.8;
    filter: brightness(0.8);
}
`;
};
