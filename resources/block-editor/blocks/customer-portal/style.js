// styles.js
export const getAllTicketsHeaderStyle = (attributes) => ({
    background: attributes.allTicketsHeaderBgColor,
});

export const getAllTicketsAllButtonStyle = (attributes) => ({
    color: attributes.filterButtonAllTextColor,
    background: attributes.filterButtonAllBgColor,
});

export const getAllTicketsOpenButtonStyle = (attributes) => ({
    color: attributes.filterButtonOpenTextColor,
    background: attributes.filterButtonOpenBgColor,
});

export const getAllTicketsClosedButtonStyle = (attributes) => ({
    color: attributes.filterButtonClosedTextColor,
    background: attributes.filterButtonClosedBgColor,
});

export const getAllTicketsLogoutButtonStyle = (attributes) => ({
    color: attributes.allTicketsLogoutButtonTextColor,
    background: attributes.allTicketsLogoutButtonBgColor,
});

export const getAllTicketsCreateTicketButtonStyle = (attributes) => ({
    color: attributes.buttonCreateTicketTextColor,
    background: attributes.buttonCreateTicketBgColor,
});

export const getAllTicketsTableRowStyle = (attributes) => ({
    background: attributes.allTicketsTableRowBgColor,
    // background: attributes.buttonCreateTicketBgColor,
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

export const getViewTicketCustomerThreadRibbonTailStyle = (attributes)  =>({
    borderLeft: '4px solid ' + attributes.CustomerThreadRibbonColor,
});

export const getViewTicketCustomerThreadRibbonHeaderStyle = (attributes)  =>({
    background: attributes.CustomerThreadRibbonColor,
});

export const getViewTicketAgentThreadRibbonTailStyle = (attributes)  =>({
    borderLeft: '4px solid ' + attributes.AgentThreadRibbonColor,
});

export const getViewTicketAgentThreadRibbonHeaderStyle = (attributes)  =>({
    background: attributes.AgentThreadRibbonColor,
});
















