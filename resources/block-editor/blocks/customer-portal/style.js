// styles.js
export const getAllTicketsHeaderStyle = (attributes) => ({
    background: attributes.allTicketsHeaderBgColor,
    borderTopLeftRadius: attributes.allTicketsHeaderRadiusTopLeft + 'px',
    borderTopRightRadius: attributes.allTicketsHeaderRadiusTopRight + 'px',
    borderBottomRightRadius: attributes.allTicketsHeaderRadiusBottomRight + 'px',
    borderBottomLeftRadius: attributes.allTicketsHeaderRadiusBottomLeft + 'px',
});

export const getAllTicketsAllButtonStyle = (attributes) => ({
    color: attributes.filterButtonAllTextColor,
    background: attributes.filterButtonAllBgColor,
    border: attributes.filterButtonAllBorderWidth + 'px solid ' + attributes.filterButtonAllBgColor,
    borderRadius: attributes.filterButtonAllBorderRadius + 'px'
});

export const getAllTicketsOpenButtonStyle = (attributes) => ({
    color: attributes.filterButtonOpenTextColor,
    background: attributes.filterButtonOpenBgColor,
    border: attributes.filterButtonOpenBorderWidth + 'px solid ' + attributes.filterButtonOpenBgColor,
    borderRadius: attributes.filterButtonOpenBorderRadius + 'px',
});

export const getAllTicketsClosedButtonStyle = (attributes) => ({
    color: attributes.filterButtonClosedTextColor,
    background: attributes.filterButtonClosedBgColor,
    border: attributes.filterButtonClosedBorderWidth + 'px solid ' + attributes.filterButtonClosedBgColor,
    borderRadius: attributes.filterButtonClosedBorderRadius + 'px',
});

export const getAllTicketsLogoutButtonStyle = (attributes) => ({
    color: attributes.allTicketsLogoutButtonTextColor,
    background: attributes.allTicketsLogoutButtonBgColor,
    border: attributes.allTicketsLogoutButtonBorderWidth + 'px solid ' + attributes.allTicketsLogoutButtonBgColor,
    borderRadius: attributes.allTicketsLogoutButtonBorderRadius + 'px',
});

export const getAllTicketsCreateTicketButtonStyle = (attributes) => ({
    color: attributes.buttonCreateTicketTextColor,
    background: attributes.buttonCreateTicketBgColor,
    border: attributes.buttonCreateTicketBorderWidth + 'px solid ' + attributes.buttonCreateTicketBgColor,
    borderRadius: attributes.buttonCreateTicketBorderRadius + 'px',
});

export const getAllTicketsFooterStyle = (attributes)  =>({
    background: attributes.allTicketsFooterBgColor,
    borderTopLeftRadius: attributes.allTicketsFooterRadiusTopLeft + 'px',
    borderTopRightRadius: attributes.allTicketsFooterRadiusTopRight + 'px',
    borderBottomRightRadius: attributes.allTicketsFooterRadiusBottomRight + 'px',
    borderBottomLeftRadius: attributes.allTicketsFooterRadiusBottomLeft + 'px',
});

export const getViewTicketHeaderStyle = (attributes)  =>({
    background: attributes.viewTicketHeaderStyleBgColor,
    color: attributes.viewTicketHeaderStyleTextColor,
    borderTop: attributes.viewTicketHeaderBorderWidthTop + 'px solid ' + attributes.viewTicketHeaderBorderColor,
    borderRight: attributes.viewTicketHeaderBorderWidthRight + 'px solid ' + attributes.viewTicketHeaderBorderColor,
    borderBottom: attributes.viewTicketHeaderBorderWidthBottom + 'px solid ' + attributes.viewTicketHeaderBorderColor,
    borderLeft: attributes.viewTicketHeaderBorderWidthLeft + 'px solid ' + attributes.viewTicketHeaderBorderColor,
    borderTopLeftRadius: attributes.viewTicketHeaderRadiusTopLeft + 'px',
    borderTopRightRadius: attributes.viewTicketHeaderRadiusTopRight + 'px',
    borderBottomRightRadius: attributes.viewTicketHeaderRadiusBottomRight + 'px',
    borderBottomLeftRadius: attributes.viewTicketHeaderRadiusBottomLeft + 'px',
});

export const getViewTicketIdAndTitleStyle = (attributes)  =>({
    color: attributes.viewTicketHeaderStyleTextColor,
});

export const getViewTicketIdStyle = (attributes)  =>({
    color: attributes.viewTicketHeaderIdTextColor,
});

export const getViewTicketRefreshButtonStyle = (attributes)  =>({
    background: attributes.refreshButtonBgColor,
    color: attributes.refreshButtonTextColor,
    borderTop: attributes.refreshButtonBorderWidthTop + 'px solid ' + attributes.refreshButtonBorderColor,
    borderRight: attributes.refreshButtonBorderWidthRight + 'px solid ' + attributes.refreshButtonBorderColor,
    borderBottom: attributes.refreshButtonBorderWidthBottom + 'px solid ' + attributes.refreshButtonBorderColor,
    borderLeft: attributes.refreshButtonBorderWidthLeft + 'px solid ' + attributes.refreshButtonBorderColor,
    borderTopLeftRadius: attributes.refreshButtonBorderRadiusTopLeft + 'px',
    borderTopRightRadius: attributes.refreshButtonBorderRadiusTopRight + 'px',
    borderBottomRightRadius: attributes.refreshButtonBorderRadiusBottomRight + 'px',
    borderBottomLeftRadius: attributes.refreshButtonBorderRadiusBottomLeft + 'px',
});

export const getViewTicketAllButtonStyle = (attributes)  =>({
    background: attributes.allButtonBgColor,
    color: attributes.allButtonTextColor,
    borderTop: attributes.allButtonBorderWidthTop + 'px solid ' + attributes.allButtonBorderColor,
    borderRight: attributes.allButtonBorderWidthRight + 'px solid ' + attributes.allButtonBorderColor,
    borderBottom: attributes.allButtonBorderWidthBottom + 'px solid ' + attributes.allButtonBorderColor,
    borderLeft: attributes.allButtonBorderWidthLeft + 'px solid ' + attributes.allButtonBorderColor,
    borderTopLeftRadius: attributes.allButtonBorderRadiusTopLeft + 'px',
    borderTopRightRadius: attributes.allButtonBorderRadiusTopRight + 'px',
    borderBottomRightRadius: attributes.allButtonBorderRadiusBottomRight + 'px',
    borderBottomLeftRadius: attributes.allButtonBorderRadiusBottomLeft + 'px',
});

export const getViewTicketCloseButtonStyle = (attributes)  =>({
    background: attributes.closeTicketButtonBgColor,
    color: attributes.closeTicketButtonTextColor,
    borderTop: attributes.closeTicketButtonBorderWidthTop + 'px solid ' + attributes.closeTicketButtonBorderColor,
    borderRight: attributes.closeTicketButtonBorderWidthRight + 'px solid ' + attributes.closeTicketButtonBorderColor,
    borderBottom: attributes.closeTicketButtonBorderWidthBottom + 'px solid ' + attributes.closeTicketButtonBorderColor,
    borderLeft: attributes.closeTicketButtonBorderWidthLeft + 'px solid ' + attributes.closeTicketButtonBorderColor,
    borderTopLeftRadius: attributes.closeTicketButtonBorderRadiusTopLeft + 'px',
    borderTopRightRadius: attributes.closeTicketButtonBorderRadiusTopRight + 'px',
    borderBottomRightRadius: attributes.closeTicketButtonBorderRadiusBottomRight + 'px',
    borderBottomLeftRadius: attributes.closeTicketButtonBorderRadiusBottomLeft + 'px',
});

export const getViewTicketPageBodyStyle = (attributes)  =>({
    background: attributes.viewTicketPageBodyBgColor,
});

export const getViewTicketThreadStarterTailStyle = (attributes)  =>({
    borderLeft: attributes.viewTicketThreadStarterTailWidth+'px solid ' + attributes.viewTicketThreadStarterBgColor,
});

export const getViewTicketThreadStarterStyle = (attributes)  =>({
    background: attributes.viewTicketThreadStarterBgColor,
    color: attributes.viewTicketThreadStarterTextColor,
    PaddingTop: attributes.viewTicketThreadStarterPaddingTop + 'px',
    paddingRight: attributes.viewTicketThreadStarterPaddingRight + 'px',
    paddingBottom: attributes.viewTicketThreadStarterPaddingBottom + 'px',
    paddingLeft: attributes.viewTicketThreadStarterPaddingLeft + 'px',
});

export const getViewTicketAgentThreadRibbonTailStyle = (attributes)  =>({
    borderLeft: attributes.ribbonSupportStaffTailWidth+'px solid ' + attributes.ribbonSupportStaffBgColor,
});

export const getViewTicketAgentThreadRibbonHeaderStyle = (attributes)  =>({
    background: attributes.ribbonSupportStaffBgColor,
    color: attributes.ribbonSupportStaffTextColor,
    paddingTop: attributes.ribbonSupportStaffPaddingTop,
    paddingRight: attributes.ribbonSupportStaffPaddingRight,
    paddingBottom: attributes.ribbonSupportStaffPaddingBottom,
    paddingLeft: attributes.ribbonSupportStaffPaddingLeft,
});

export const getViewTicketThreadFollowerTailStyle = (attributes)  =>({
    borderLeft: attributes.viewTicketThreadFollowerTailWidth+'px solid ' + attributes.viewTicketThreadFollowerBgColor,
});

export const getViewTicketThreadFollowerStyle = (attributes)  =>({
    background: attributes.viewTicketThreadFollowerBgColor,
    color: attributes.viewTicketThreadFollowerTextColor,
    paddingTop: attributes.viewTicketThreadFollowerPaddingTop + 'px ',
    paddingRight: attributes.viewTicketThreadFollowerPaddingRight + 'px',
    paddingBottom: attributes.viewTicketThreadFollowerPaddingBottom + 'px',
    paddingLeft: attributes.viewTicketThreadFollowerPaddingLeft + 'px',
});

export const getCreateTicketHeaderStyle = (attributes)  =>({
    background: attributes.createTicketHeaderBgColor,
    borderTopLeftRadius: attributes.createTicketHeaderRadiusTopLeft + 'px',
    borderTopRightRadius: attributes.createTicketHeaderRadiusTopRight + 'px',
    borderBottomRightRadius: attributes.createTicketHeaderRadiusBottomRight + 'px',
    borderBottomLeftRadius: attributes.createTicketHeaderRadiusBottomLeft + 'px',
});

export const getCreateTicketHeaderTextStyle = (attributes)  =>({
    color: attributes.createTicketHeaderTextColor,
});

export const getCreateTicketViewAllButtonStyle = (attributes)  =>({
    background: attributes.createTicketViewAllButtonBgColor,
    color: attributes.createTicketViewAllButtonTextColor,
    border: attributes.createTicketViewAllButtonBorderWidth + 'px solid ' + attributes.createTicketViewAllButtonBgColor,
    borderRadius: attributes.createTicketViewAllButtonBorderRadius + 'px',
});

export const getCreateTicketBodyStyle = (attributes)  =>({
    background: attributes.createTicketBodyBgColor,
    color: attributes.createTicketInputLabelTextColor,
});

export const getCreateTicketFormStyle = (attributes)  =>({
    color: attributes.createTicketInputLabelTextColor,
});

export const getCreateTicketHintMessageStyle = (attributes)  =>({
    color: attributes.createTicketHintMessageTextColor,
});

export const getCreateTicketUploadButtonStyle = (attributes) =>({
    color: attributes.createTicketUploadButtonTextColor,
    background: attributes.createTicketUploadButtonBgColor,
    border: attributes.createTicketUploadButtonBorderWidth + 'px solid ' + attributes.createTicketUploadButtonBgColor,
    borderRadius: attributes.createTicketUploadButtonBorderRadius + 'px',
});

export const getCreateTicketCreateButtonStyle = (attributes) =>({
    color: attributes.createTicketCreateButtonTextColor,
    background: attributes.createTicketCreateButtonBgColor,
    border: attributes.createTicketCreateButtonBorderWidth + 'px solid ' + attributes.createTicketCreateButtonBgColor,
    borderRadius: attributes.createTicketCreateButtonBorderRadius + 'px',
});

export const getAllTicketsTableHeaderStyle = (attributes) =>({
    background: attributes.allTicketsTableHeaderBgColor,
    textAlign: attributes.allTicketsTableHeaderTextAlign,
    color: attributes.allTicketsTableHeaderTextColor,
});

export const showAllTickets = (showTickets)  =>({
    display: showTickets === true ? '': 'none',
});

export const showCreateTicketForm = (createTicket) =>({
    display: createTicket === true ? '': 'none',
});

export const viewTicketPage = (ticketPage) =>({
    display: ticketPage === true ? '': 'none',
});
