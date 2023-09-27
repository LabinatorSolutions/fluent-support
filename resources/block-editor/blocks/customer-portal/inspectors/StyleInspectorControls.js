import ButtonAllStyle from './AllTickets/Style/ButtonAllStyle';
import ButtonOpen from './AllTickets/Style/ButtonOpenStyle';
import ButtonClosedStyle from "./AllTickets/Style/ButtonClosedStyle";
export default function StyleInspectorControls({ attributes, setAttributes, selectedInspector}){
    const inspectorsList =  [
        ButtonAllStyle({attributes, setAttributes}),
        ButtonOpen({attributes, setAttributes}),
        ButtonClosedStyle({attributes, setAttributes}),
    ];

    return (
        <div>
            {inspectorsList[selectedInspector]}
        </div>
    );
}
