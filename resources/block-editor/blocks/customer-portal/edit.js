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

    /**
     * Get the CSS class for an inspector based on whether it's active.
     * @param {string} inspectorName - The name of the inspector.
     * @returns {string} - The CSS class.
     */
    function getActiveClass(inspectorName) {
        return selectedInspector === inspectorName ? " fst-block-active-components" : "";
    }
    /**
     * Handle clicking on an inspector element.
     * @param {string} inspectorName - The inspector name to toggle.
     * @param {Object} e - The event object.
     * @returns {Function} - Event handler function.
     */
    const preventParentPropagation = (inspectorName, e) => {
        e.stopPropagation();
        setSelectedInspector(inspectorName);
    };

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
                        activeClass={getActiveClass}
                        selectedInspector={setSelectedInspector}
                        preventParentPropagation={preventParentPropagation}
                    />
                )}
                {showSection === 'createTicket' && (
                    <CreateTicket
                        attributes={attributes}
                        showSection={setShowSection}
                        activeClass={getActiveClass}
                        selectedInspector={setSelectedInspector}
                        preventParentPropagation={preventParentPropagation}
                    />
                )}
                {showSection === 'viewTicket' && (
                    <ViewTicket
                        attributes={attributes}
                        showSection={setShowSection}
                        activeClass={getActiveClass}
                        selectedInspector={setSelectedInspector}
                        preventParentPropagation={preventParentPropagation}
                    />
                )}
            </div>
        </Fragment>
    );
}
