import ButtonAll from './buttonAll';
import ButtonOpen from "./ButtonOpen";
import ButtonClosed from "./ButtonClosed";
export default function StyleInspector({ attributes, setAttributes, selectedInspector}){
    const inspectorsList =  [
        ButtonAll({attributes, setAttributes}),
        ButtonOpen({attributes, setAttributes}),
        ButtonClosed({attributes, setAttributes}),
    ];
    const childInspector = inspectorsList[selectedInspector];
    return (
        <div>
            {childInspector}
        </div>
    );
}
