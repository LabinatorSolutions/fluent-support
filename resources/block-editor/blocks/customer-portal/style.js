// styles.js
export const getAllTicketsHeaderStyle = (attributes) => ({
    background: attributes.allTicketsHeaderBgColor,
});

export const getAllTicketsAllButtonStyle = (attributes) => ({
    color: attributes.filterButtonAllTextColor,
    background: attributes.filterButtonAllBgColor,
    border: `1px solid ${attributes.filterButtonAllBorderColor}`,
});

export const getAllTicketsOpenButtonStyle = (attributes) => ({
    color: attributes.filterButtonOpenTextColor,
    background: attributes.filterButtonOpenBgColor,
});

export const getAllTicketsClosedButtonStyle = (attributes) => ({
    color: attributes.filterButtonClosedTextColor,
    background: attributes.filterButtonClosedBgColor,
    borderRadius: attributes.filterButtonClosedBorderRadius + 'px',
});

export const getAllTicketsLogoutButtonStyle = (attributes) => ({
    color: attributes.allTicketsLogoutButtonTextColor,
    background: attributes.allTicketsLogoutButtonBgColor,
    // border: `1px solid ${attributes.allTicketsLogoutButtonBorderColor}`
});

export const getAllTicketsCreateTicketButtonStyle = (attributes) => ({
    color: attributes.buttonCreateTicketTextColor,
    background: attributes.buttonCreateTicketBgColor,
    border: `1px solid ${attributes.buttonCreateTicketBorderColor}`
});

export const getAllTicketsFooterStyle = (attributes)  =>({
    background: attributes.allTicketsFooterBgColor,
});

export const getViewTicketHeaderStyle = (attributes)  =>({
    background: attributes.viewTicketHeaderStyleBgColor,
    color: attributes.viewTicketHeaderStyleTextColor,
});

export const getViewTicketIdStyle = (attributes)  =>({
    color: attributes.viewTicketHeaderIdTextColor,
});

export const getViewTicketRefreshButtonStyle = (attributes)  =>({
    background: attributes.refreshButtonBgColor,
    color: attributes.refreshButtonTextColor,
});

export const getViewTicketAllButtonStyle = (attributes)  =>({
    background: attributes.allButtonBgColor,
    color: attributes.allButtonTextColor,
});

export const getViewTicketCloseButtonStyle = (attributes)  =>({
    background: attributes.closeTicketButtonBgColor,
    color: attributes.closeTicketButtonTextColor,
});

export const getViewTicketPageBodyStyle = (attributes)  =>({
    background: attributes.viewTicketPageBodyBgColor,
});

export const getViewTicketThreadStarterTailStyle = (attributes)  =>({
    borderLeft: '4px solid ' + attributes.viewTicketThreadStarterBgColor,
});

export const getViewTicketThreadStarterStyle = (attributes)  =>({
    background: attributes.viewTicketThreadStarterBgColor,
    color: attributes.viewTicketThreadStarterTextColor,
});

export const getViewTicketAgentThreadRibbonTailStyle = (attributes)  =>({
    borderLeft: '4px solid ' + attributes.ribbonSupportStaffBgColor,
});

export const getViewTicketAgentThreadRibbonHeaderStyle = (attributes)  =>({
    background: attributes.ribbonSupportStaffBgColor,
    color: attributes.ribbonSupportStaffTextColor,
});

export const getViewTicketThreadFollowerTailStyle = (attributes)  =>({
    borderLeft: '4px solid ' + attributes.viewTicketThreadFollowerBgColor,
});

export const getViewTicketThreadFollowerStyle = (attributes)  =>({
    background: attributes.viewTicketThreadFollowerBgColor,
    color: attributes.viewTicketThreadFollowerTextColor,
});

export const getCreateTicketHeaderStyle = (attributes)  =>({
    background: attributes.createTicketHeaderBgColor,
});

export const getCreateTicketHeaderTextStyle = (attributes)  =>({
    color: attributes.createTicketHeaderTextColor,
});

export const getCreateTicketViewAllButtonStyle = (attributes)  =>({
    background: attributes.createTicketViewAllButtonBgColor,
    color: attributes.createTicketViewAllButtonTextColor,
});

export const getCreateTicketBodyStyle = (attributes)  =>({
    background: attributes.createTicketBodyBgColor,
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
    border: `1 px solid ${attributes.allTicketsLogoutButtonBorderColor}`
});

export const getCreateTicketCreateButtonStyle = (attributes) =>({
    color: attributes.createTicketCreateButtonTextColor,
    background: attributes.createTicketCreateButtonBgColor,
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
