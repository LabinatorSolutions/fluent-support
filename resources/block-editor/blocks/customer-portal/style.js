// styles.js
export const getAllTicketsHeaderStyle = (attributes) => ({
    background: attributes.allTicketsHeaderBgColor,
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
    border: attributes.filterButtonLogoutBorderWidth + 'px solid ' + attributes.allTicketsLogoutButtonBgColor,
    borderRadius: attributes.filterButtonLogoutBorderRadius + 'px',
});

export const getAllTicketsCreateTicketButtonStyle = (attributes) => ({
    color: attributes.buttonCreateTicketTextColor,
    background: attributes.buttonCreateTicketBgColor,
    border: attributes.buttonCreateTicketBorderWidth + 'px solid ' + attributes.buttonCreateTicketBgColor,
    borderRadius: attributes.buttonCreateTicketBorderRadius + 'px',
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
    border: attributes.refreshButtonBorderWidth + 'px solid ' + attributes.refreshButtonBgColor,
    borderRadius: attributes.refreshButtonBorderRadius + 'px',
});

export const getViewTicketAllButtonStyle = (attributes)  =>({
    background: attributes.allButtonBgColor,
    color: attributes.allButtonTextColor,
    border: attributes.allButtonBorderWidth + 'px solid ' + attributes.allButtonBgColor,
    borderRadius: attributes.allButtonBorderRadius + 'px',
});

export const getViewTicketCloseButtonStyle = (attributes)  =>({
    background: attributes.closeTicketButtonBgColor,
    color: attributes.closeTicketButtonTextColor,
    border: attributes.closeTicketButtonBorderWidth + 'px solid ' + attributes.closeTicketButtonBgColor,
    borderRadius: attributes.closeTicketButtonBorderRadius + 'px',
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
