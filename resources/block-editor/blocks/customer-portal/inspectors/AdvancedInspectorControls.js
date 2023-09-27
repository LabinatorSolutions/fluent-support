import ButtonAllAdvanced from './AllTickets/Advanced/ButtonAllAdvanced';
import ButtonOpenAdvanced from './AllTickets/Advanced/ButtonOpenAdvanced';
import ButtonClosedAdvanced from "./AllTickets/Advanced/ButtonClosedAdvanced";
export default function AdvancedInspectorControls({ attributes, setAttributes, selectedInspector}){
    const inspectorsList =  [
        ButtonAllAdvanced({attributes, setAttributes}),
        ButtonOpenAdvanced({attributes, setAttributes}),
        ButtonClosedAdvanced({attributes, setAttributes}),
    ];

    return (
        <div>
            {inspectorsList[selectedInspector]}
        </div>
    );
}
