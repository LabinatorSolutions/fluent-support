import ButtonAllStyle from "../inspectors/AllTickets/Style/ButtonAllStyle";
import ButtonOpenStyle from "../inspectors/AllTickets/Style/ButtonOpenStyle";
import ButtonClosedStyle from "../inspectors/AllTickets/Style/ButtonClosedStyle";
import ButtonAllAdvanced from "../inspectors/AllTickets/Advanced/ButtonAllAdvanced";
import ButtonOpenAdvanced from "../inspectors/AllTickets/Advanced/ButtonOpenAdvanced";
import ButtonClosedAdvanced from "../inspectors/AllTickets/Advanced/ButtonClosedAdvanced";
import ButtonLogoutStyle from "../inspectors/AllTickets/Style/ButtonLogoutStyle"
import ButtonCreateTicketStyle from "../inspectors/AllTickets/Style/ButtonCreateTicket";
import AllTicketsTableHeaderStyle from "../inspectors/AllTickets/Style/AllTicketsTableHeaderStyle";
import AllTicketsFooterStyle from "../inspectors/AllTickets/Style/AllTicketsFooterStyle";
import AllTicketsHeaderStyle from "../inspectors/AllTickets/Style/AllTicketsheaderStyle";
import CreateTicketsHeaderStyle from "../inspectors/CreateTicket/Style/CreateTicketHeaderStyle";
import ButtonViewAllStyle from "../inspectors/CreateTicket/Style/ButtonViewAllStyle";
import CreateTicketsBodyStyle from "../inspectors/CreateTicket/Style/CreateTicketBodyStyle";
import ButtonClickToUploadStyle from "../inspectors/CreateTicket/Style/ButtonClickToUploadStyle";
import ButtonCreateStyle from "../inspectors/CreateTicket/Style/ButtonCreateStyle";
//View Ticket
import ViewTicketHeaderStyle from "../inspectors/ViewTicket/Style/ViewTicketHeaderStyle";
import ViewTicketHeaderAdvanced from "../inspectors/ViewTicket/Advanced/ViewTicketHeaderAdvanced";
import ButtonRefreshStyle from "../inspectors/ViewTicket/Style/ButtonRefreshStyle";
import ButtonRefreshAdvanced from "../inspectors/ViewTicket/Advanced/ButtonRefreshAdvanced";
import ViewTicketButtonAllStyle from "../inspectors/ViewTicket/Style/ViewTicketButtonAllStyle";
import ViewTicketButtonAllAdvanced from "../inspectors/ViewTicket/Advanced/ViewTicketButtonAllAdvanced";
import ButtonCloseTicketStyle from "../inspectors/ViewTicket/Style/ButtonCloseTicketStyle";
import ButtonCloseTicketAdvanced from "../inspectors/ViewTicket/Advanced/ButtonCloseTicketAdvanced";
import ViewTicketBodyStyle from "../inspectors/ViewTicket/Style/ViewTicketBodyStyle";
import RibbonSupportStaffStyle from "../inspectors/ViewTicket/Style/RibbonSupportStaffStyle";
import RibbonSupportStaffAdvanced from "../inspectors/ViewTicket/Advanced/RibbonSupportStaffAdvanced";
import RibbonThreadStarterStyle from "../inspectors/ViewTicket/Style/RibbonThreadStarterStyle";
import RibbonThreadStarterAdvanced from "../inspectors/ViewTicket/Advanced/RibbonThreadStarterAdvanced";
import RibbonThreadFollowerStyle from "../inspectors/ViewTicket/Style/RibbonThreadFollowerStyle";
import RibbonThreadFollowerAdvanced from "../inspectors/ViewTicket/Advanced/RibbonThreadFollowerAdvanced";
import ButtonLogoutAdvanced from "../inspectors/AllTickets/Advanced/ButtonLogoutAdvanced";
import ButtonCreateTicketAdvanced from "../inspectors/AllTickets/Advanced/ButtonCreateTicketAdvanced";
import AllTicketsTableHeaderAdvanced from "../inspectors/AllTickets/Advanced/AllTicketsTableHeaderAdvanced";
import AllTicketsHeaderAdvanced from "../inspectors/AllTickets/Advanced/AllTicketsHeaderAdvanced";
import AllTicketsFooterAdvanced from "../inspectors/AllTickets/Advanced/AllTicketsFooterAdvanced";
import ButtonViewAllAdvanced from "../inspectors/CreateTicket/Advanced/ButtonViewAllAdvanced";

import CreateTicketHeaderAdvanced from "../inspectors/CreateTicket/Advanced/CreateTicketHeaderAdvanced";
import CreateTicketHeaderStyle from "../inspectors/CreateTicket/Style/CreateTicketHeaderStyle";
import ButtonCreateAdvanced from "../inspectors/CreateTicket/Advanced/ButtonCreateAdvanced";
import ButtonClickToUploadAdvanced from "../inspectors/CreateTicket/Advanced/ButtonClickToUploadAdvanced";

export default function InspectorContainer({attributes, setAttributes}) {
    return {
        //View All tickets
        buttonAllStyle: ButtonAllStyle({attributes, setAttributes}),
        buttonAllAdvanced: ButtonAllAdvanced({attributes, setAttributes}),
        buttonOpenStyle: ButtonOpenStyle({attributes, setAttributes}),
        buttonOpenAdvanced: ButtonOpenAdvanced({attributes, setAttributes}),
        buttonClosedStyle: ButtonClosedStyle({attributes, setAttributes}),
        buttonClosedAdvanced: ButtonClosedAdvanced({attributes, setAttributes}),
        buttonLogoutStyle: ButtonLogoutStyle({attributes, setAttributes}),
        buttonLogoutAdvanced: ButtonLogoutAdvanced({attributes, setAttributes}),
        buttonCreateTicketStyle: ButtonCreateTicketStyle({attributes, setAttributes}),
        buttonCreateTicketAdvanced: ButtonCreateTicketAdvanced({attributes, setAttributes}),
        allTicketsTableHeaderStyle: AllTicketsTableHeaderStyle({attributes, setAttributes}),
        allTicketsFooterStyle: AllTicketsFooterStyle({attributes, setAttributes}),
        allTicketsHeaderStyle: AllTicketsHeaderStyle({attributes, setAttributes}),
        allTicketsTableHeaderAdvanced: AllTicketsTableHeaderAdvanced({attributes, setAttributes}),
        allTicketsHeaderAdvanced: AllTicketsHeaderAdvanced({attributes, setAttributes}),
        allTicketsFooterAdvanced: AllTicketsFooterAdvanced({attributes, setAttributes}),

        //Create Ticket
        createTicketHeaderStyle: CreateTicketHeaderStyle({attributes, setAttributes}),
        buttonViewAllStyle: ButtonViewAllStyle({attributes, setAttributes}),
        createTicketsBodyStyle: CreateTicketsBodyStyle({attributes, setAttributes}),
        buttonClickToUploadStyle: ButtonClickToUploadStyle({attributes, setAttributes}),
        buttonCreateStyle: ButtonCreateStyle({attributes, setAttributes}),
        buttonViewAllAdvanced:ButtonViewAllAdvanced({attributes, setAttributes}),
        createTicketHeaderAdvanced: CreateTicketHeaderAdvanced({attributes, setAttributes}),
        buttonCreateAdvanced: ButtonCreateAdvanced({attributes, setAttributes}),
        buttonClickToUploadAdvanced: ButtonClickToUploadAdvanced({attributes, setAttributes}),

        //View Ticket
        viewTicketHeaderStyle: ViewTicketHeaderStyle({attributes, setAttributes}),
        viewTicketHeaderAdvanced: ViewTicketHeaderAdvanced({attributes, setAttributes}),
        buttonRefreshStyle: ButtonRefreshStyle({attributes, setAttributes}),
        buttonRefreshAdvanced: ButtonRefreshAdvanced({attributes, setAttributes}),
        viewTicketButtonAllStyle: ViewTicketButtonAllStyle({attributes, setAttributes}),
        viewTicketButtonAllAdvanced: ViewTicketButtonAllAdvanced({attributes, setAttributes}),
        buttonCloseTicketStyle: ButtonCloseTicketStyle({attributes, setAttributes}),
        buttonCloseTicketAdvanced: ButtonCloseTicketAdvanced({attributes, setAttributes}),
        viewTicketBodyStyle: ViewTicketBodyStyle({attributes, setAttributes}),
        ribbonSupportStaffStyle: RibbonSupportStaffStyle({attributes, setAttributes}),
        ribbonSupportStaffAdvanced: RibbonSupportStaffAdvanced({attributes, setAttributes}),
        ribbonThreadStarterStyle: RibbonThreadStarterStyle({attributes, setAttributes}),
        ribbonThreadStarterAdvanced: RibbonThreadStarterAdvanced({attributes, setAttributes}),
        ribbonThreadFollowerStyle: RibbonThreadFollowerStyle({attributes, setAttributes}),
        ribbonThreadFollowerAdvanced: RibbonThreadFollowerAdvanced({attributes, setAttributes}),
    };
}
