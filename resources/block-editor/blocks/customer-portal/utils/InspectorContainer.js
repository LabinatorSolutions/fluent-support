import ButtonAllStyle from "../inspectors/AllTickets/Style/ButtonAllStyle";
import ButtonOpenStyle from "../inspectors/AllTickets/Style/ButtonOpenStyle";
import ButtonClosedStyle from "../inspectors/AllTickets/Style/ButtonClosedStyle";
import ButtonAllAdvanced from "../inspectors/AllTickets/Advanced/ButtonAllAdvanced";
import ButtonOpenAdvanced from "../inspectors/AllTickets/Advanced/ButtonOpenAdvanced";
import ButtonClosedAdvanced from "../inspectors/AllTickets/Advanced/ButtonClosedAdvanced";

export default function InspectorContainer({attributes, setAttributes}) {
    return {
        buttonAllStyle: ButtonAllStyle({attributes, setAttributes}),
        buttonAllAdvanced: ButtonAllAdvanced({attributes, setAttributes}),
        buttonOpenStyle: ButtonOpenStyle({attributes, setAttributes}),
        buttonOpenAdvanced: ButtonOpenAdvanced({attributes, setAttributes}),
        buttonClosedStyle: ButtonClosedStyle({attributes, setAttributes}),
        buttonClosedAdvanced: ButtonClosedAdvanced({attributes, setAttributes}),
    };
}
