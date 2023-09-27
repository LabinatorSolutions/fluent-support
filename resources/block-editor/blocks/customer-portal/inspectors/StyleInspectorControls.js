import InspectorsList from "../utils/inspectorsList";
export default function StyleInspectorControls({ attributes, setAttributes, selectedInspector}){
    const inspectorsList = InspectorsList({attributes, setAttributes});

    return (
        <div>
            {inspectorsList[selectedInspector]}
        </div>
    );
}
