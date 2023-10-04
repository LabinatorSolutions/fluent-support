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
import ButtonRefreshStyle from "../inspectors/ViewTicket/Style/ButtonRefreshStyle";
import ViewTicketButtonAllStyle from "../inspectors/ViewTicket/Style/ViewTicketButtonAllStyle";
import ButtonCloseTicketStyle from "../inspectors/ViewTicket/Style/ButtonCloseTicketStyle";
import ButtonCloseTicketAdvanced from "../inspectors/ViewTicket/Advanced/ButtonCloseTicketAdvanced";
import ViewTicketBodyStyle from "../inspectors/ViewTicket/Style/ViewTicketBodyStyle";
import RibbonSupportStaffStyle from "../inspectors/ViewTicket/Style/RibbonSupportStaffStyle";
import RibbonThreadStarterStyle from "../inspectors/ViewTicket/Style/RibbonThreadStarterStyle";
import RibbonThreadFollowerStyle from "../inspectors/ViewTicket/Style/RibbonThreadFollowerStyle";
import ButtonLogoutAdvanced from "../inspectors/AllTickets/Advanced/ButtonLogoutAdvanced";
import ButtonCreateTicketAdvanced from "../inspectors/AllTickets/Advanced/ButtonCreateTicketAdvanced";

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
        createTicketsHeaderStyle: CreateTicketsHeaderStyle({attributes, setAttributes}),
        buttonViewAllStyle: ButtonViewAllStyle({attributes, setAttributes}),
        createTicketsBodyStyle: CreateTicketsBodyStyle({attributes, setAttributes}),
        buttonClickToUploadStyle: ButtonClickToUploadStyle({attributes, setAttributes}),
        buttonCreateStyle: ButtonCreateStyle({attributes, setAttributes}),

        //View Ticket
        viewTicketHeaderStyle: ViewTicketHeaderStyle({attributes, setAttributes}),
        buttonRefreshStyle: ButtonRefreshStyle({attributes, setAttributes}),
        viewTicketButtonAllStyle: ViewTicketButtonAllStyle({attributes, setAttributes}),
        buttonCloseTicketStyle: ButtonCloseTicketStyle({attributes, setAttributes}),
        buttonCloseTicketAdvanced: ButtonCloseTicketAdvanced({attributes, setAttributes}),
        viewTicketBodyStyle: ViewTicketBodyStyle({attributes, setAttributes}),
        ribbonSupportStaffStyle: RibbonSupportStaffStyle({attributes, setAttributes}),
        ribbonThreadStarterStyle: RibbonThreadStarterStyle({attributes, setAttributes}),
        ribbonThreadFollowerStyle: RibbonThreadFollowerStyle({attributes, setAttributes}),
    };
}
