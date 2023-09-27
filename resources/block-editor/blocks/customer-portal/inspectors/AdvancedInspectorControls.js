import InspectorsList from "../utils/inspectorsList";
export default function AdvancedInspectorControls({ attributes, setAttributes, selectedInspector}){
    const inspectorsList = InspectorsList({attributes, setAttributes});
    selectedInspector = selectedInspector.replace('Style', 'Advanced');
    return (
        <div>
            {inspectorsList[selectedInspector]}
        </div>
    );
}
