const {Fragment, useState, useEffect} = wp.element;
const { apiFetch } = wp;
const restUrl = window.fluent_support_vars.rest.url;

import AllTickets from './components/AllTickets';
import CreateTicket from './components/CreateTicket';
import ViewTicket from './components/ViewTicket';
//Import Inspector controls
import Inspector from './inspector';
//Import editor styles
import './editor.scss';

export default function Edit({attributes, setAttributes}) {
    const [showTickets, setShowTickets] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const [showTicket, setShowTicket] = useState(false);
    const [mailboxes, setMailboxes] = useState([]);

    useEffect(() => {
        apiFetch({
            path: restUrl + '/mailboxes',
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

    const inspectorProps = {
        attributes,
        setAttributes,
        showTickets,
        showForm,
        showTicket,
        mailboxes
    }

    return (
        <Fragment>
            <Inspector inspectorProps={inspectorProps}/>

            {showTickets === true ?
                <AllTickets
                    attributes={attributes}
                    showCreateTicket={showCreateTicket}
                    viewTicket={viewTicket}
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
        </Fragment>
    );
}
