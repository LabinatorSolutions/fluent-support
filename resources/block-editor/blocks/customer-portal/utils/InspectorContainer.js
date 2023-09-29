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

export default function InspectorContainer({attributes, setAttributes}) {
    return {
        buttonAllStyle: ButtonAllStyle({attributes, setAttributes}),
        buttonAllAdvanced: ButtonAllAdvanced({attributes, setAttributes}),
        buttonOpenStyle: ButtonOpenStyle({attributes, setAttributes}),
        buttonOpenAdvanced: ButtonOpenAdvanced({attributes, setAttributes}),
        buttonClosedStyle: ButtonClosedStyle({attributes, setAttributes}),
        buttonClosedAdvanced: ButtonClosedAdvanced({attributes, setAttributes}),
        buttonLogoutStyle: ButtonLogoutStyle({attributes, setAttributes}),
        buttonCreateTicketStyle: ButtonCreateTicketStyle({attributes, setAttributes}),
        allTicketsTableHeaderStyle: AllTicketsTableHeaderStyle({attributes, setAttributes}),
        allTicketsFooterStyle: AllTicketsFooterStyle({attributes, setAttributes})
    };
}
