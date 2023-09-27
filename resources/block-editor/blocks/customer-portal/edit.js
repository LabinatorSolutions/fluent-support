const {Fragment, useState, useEffect} = wp.element;
const {useBlockProps} = wp.blockEditor;
const { apiFetch } = wp;
const restInfo = window.fluent_support_vars.rest;
const basePath = restInfo.namespace+'/'+restInfo.version+'/';

import AllTickets from './components/AllTickets';
import CreateTicket from './components/CreateTicket';
import ViewTicket from './components/ViewTicket';
//Import Inspector controls
import Inspector from './inspectors/Inspector';
//Import editor styles
import './editor.scss';
import InspectorsList from "./utils/inspectorsList";

export default function Edit({attributes, setAttributes}) {
    const [showTickets, setShowTickets] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const [showTicket, setShowTicket] = useState(false);
    const [mailboxes, setMailboxes] = useState([]);
    useEffect(() => {
        apiFetch({
            path: basePath + 'mailboxes',
        }).then((res) => {
            setMailboxes(res.mailboxes)
        });
    }, []);

    useEffect(() => {
        setShowTickets(true);
    }, true);
    const showCreateTicket = () => {
        setShowForm(true);
        setShowTickets(false);
        setShowTicket(false);
    }
    const showTicketsList = () => {
        setShowForm(false);
        setShowTickets(true);
        setShowTicket(false);
    }

    const viewTicket = () => {
        setShowForm(false);
        setShowTickets(false);
        setShowTicket(true);
    }

    const [selectedInspector, setSelectedInspector] = useState(false);
    const toggleInspector = (ind) => {
        const inspectorsList = InspectorsList({attributes, setAttributes});
        if (inspectorsList[ind] === undefined) {
            return;
        }
        setSelectedInspector(ind);
    }

    return (
        <Fragment>
            <Inspector
                attributes={attributes}
                setAttributes={setAttributes}
                mailboxes={mailboxes}
                selectedInspector={selectedInspector}
            />
            <div {...useBlockProps()}>
            {showTickets === true ?
                <AllTickets
                    attributes={attributes}
                    showCreateTicket={showCreateTicket}
                    viewTicket={viewTicket}
                    toggleInspector={toggleInspector}
                    selectedInspector={selectedInspector}
                />
                : showForm === true ?
                    <CreateTicket
                        attributes={attributes}
                        showTicketsList={showTicketsList}
                    />
                    : showTicket === true ?
                        <ViewTicket
                            attributes={attributes}
                            showTicketsList={showTicketsList}
                        />
                        : null}
            </div>
        </Fragment>
    );
}
