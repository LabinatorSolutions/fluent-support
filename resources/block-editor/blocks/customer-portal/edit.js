const {Fragment, useState, useEffect} = wp.element;
const {useBlockProps} = wp.blockEditor;
const { apiFetch } = wp;
import AllTickets from './components/AllTickets';
import CreateTicket from './components/CreateTicket';
import ViewTicket from './components/ViewTicket';
import Inspector from './inspectors/Inspector';
import './editor.scss';
import InspectorContainer from './utils/InspectorContainer';

export default function Edit({attributes, setAttributes}) {
    const restInfo = window.fluent_support_vars.rest;
    const basePath = restInfo.namespace+'/'+restInfo.version+'/';
    // State variables to manage UI state
    const [showSection, setShowSection] = useState('allTickets');
    const [mailboxes, setMailboxes] = useState([]);
    const [selectedInspector, setSelectedInspector] = useState('allTicketsStyle');

    // Fetch mailboxes data from the REST API on component mount
    useEffect(() => {
        apiFetch({
            path: basePath + 'mailboxes',
        }).then((res) => {
            setMailboxes(res.mailboxes)
        });
    }, []);

    const toggleInspector = (ind) => {
        const inspectorsList = InspectorContainer({attributes, setAttributes});
        if (inspectorsList[ind] === undefined) {
            return;
        }
        setSelectedInspector(ind);
    }
    return (
        <Fragment>
            {/* Inspector component for customization */}
            <Inspector
                attributes={attributes}
                setAttributes={setAttributes}
                mailboxes={mailboxes}
                selectedInspector={selectedInspector}
            />
            <div {...useBlockProps()}>
                {/* Render different components based on the showSection state */}
                {showSection === 'allTickets' && (
                    <AllTickets
                        attributes={attributes}
                        showSection={setShowSection}
                        toggleInspector={toggleInspector}
                        selectedInspector={selectedInspector}
                    />
                )}
                {showSection === 'createTicket' && (
                    <CreateTicket
                        attributes={attributes}
                        showSection={setShowSection}
                        toggleInspector={toggleInspector}
                        selectedInspector={selectedInspector}
                    />
                )}
                {showSection === 'viewTicket' && (
                    <ViewTicket
                        attributes={attributes}
                        showSection={setShowSection}
                        toggleInspector={toggleInspector}
                        selectedInspector={selectedInspector}
                    />
                )}
            </div>
        </Fragment>
    );
}
