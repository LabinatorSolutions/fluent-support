import { useState } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { useInstanceId } from '@wordpress/compose';
import AllTickets from './AllTickets/LandingPage';
import CreateTicket from './CreateTicket/LandingPage';
import ViewTicket from './ViewTicket/LandingPage';
import { previewCss } from './PreviewStyles';

import './preview.scss';

// The preview draws the portal's own markup (classes from resources/customer_portal)
// with sample data, styled by the portal CSS, so it matches the front end.
export default function Edit({attributes, setAttributes}) {
    const [showSection, setShowSection] = useState('allTickets');
    const previewId = `fs_block_portal_preview_${useInstanceId(Edit)}`;

    const sectionProps = {
        attributes,
        setAttributes,
        showSection: setShowSection
    };

    return (
        <div {...useBlockProps()}>
            <style>{previewCss(previewId, attributes)}</style>
            <div id={previewId} className="fs_block_portal_preview">
                <div className="fs_client_portal">
                    {showSection === 'allTickets' && <AllTickets {...sectionProps} />}
                    {showSection === 'createTicket' && <CreateTicket {...sectionProps} />}
                    {showSection === 'viewTicket' && <ViewTicket {...sectionProps} />}
                </div>
            </div>
        </div>
    );
}
