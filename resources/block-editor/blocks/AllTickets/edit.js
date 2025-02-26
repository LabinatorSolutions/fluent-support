const {Fragment} = wp.element;
import InspectorSettings from './InspectorSettings';

import { TicketsLandingBlock } from './LandingPage.jsx';

const Edit = props => {
    return (
        <Fragment>
            <div className="fluent-support-block">
                <TicketsLandingBlock
                    attributes={props.attributes}
                    setAttributes={props.setAttributes}
                />
            </div>
        </Fragment>
    );
};

export default Edit;
