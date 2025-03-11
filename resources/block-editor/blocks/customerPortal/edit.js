const {Fragment, useState, useEffect} = wp.element;
const {useBlockProps} = wp.blockEditor;
const { apiFetch } = wp;
import AllTickets from './AllTickets/LandingPage';
import CreateTicket from './CreateTicket/LandingPage';
import ViewTicket from "./ViewTicket/LandingPage";


export default function Edit({attributes, setAttributes}) {
    const restInfo = window.fluent_support_vars.rest;
    const basePath = restInfo.namespace+'/'+restInfo.version+'/';
    // State variables to manage UI state
    // const [showSection, setShowSection] = useState('allTickets');
    // const [showSection, setShowSection] = useState('createTicket')
    const [showSection, setShowSection] = useState('viewTicket');
    const [mailboxes, setMailboxes] = useState([]);
    const [selectedInspector, setSelectedInspector] = useState('allTicketsStyle');
    console.log(showSection);
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
        console.log(selectedInspector, inspectorName);
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
            <div {...useBlockProps()}>
                {/* Render different components based on the showSection state */}
                {showSection === 'allTickets' && (
                    <AllTickets
                        attributes={attributes}
                        setAttributes={setAttributes}
                        showSection={setShowSection}
                        activeClass={getActiveClass}
                        selectedInspector={setSelectedInspector}
                        preventParentPropagation={preventParentPropagation}
                    />
                )}
                {showSection === 'createTicket' && (
                    <CreateTicket
                        attributes={attributes}
                        setAttributes={setAttributes}
                        showSection={setShowSection}
                        activeClass={getActiveClass}
                        selectedInspector={setSelectedInspector}
                        preventParentPropagation={preventParentPropagation}
                    />
                )}
                {showSection === 'viewTicket' && (
                    <ViewTicket
                        attributes={attributes}
                        setAttributes={setAttributes}
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
