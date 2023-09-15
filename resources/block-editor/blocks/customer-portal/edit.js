const {Fragment, useState, useEffect} = wp.element;
const {useBlockProps} = wp.blockEditor;
const { apiFetch } = wp;
const restUrl = window.fluent_support_vars.rest.url;

import AllTickets from './components/AllTickets';
import CreateTicket from './components/CreateTicket';
import ViewTicket from './components/ViewTicket';
//Import Inspector controls
import Inspector from './inspectors/Inspector';
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

    return (
        <Fragment>
            <Inspector
                attributes={attributes}
                setAttributes={setAttributes}
                showTickets={showTickets}
                showForm={showForm}
                showTicket={showTicket}
                mailboxes={mailboxes}
            />
            <div {...useBlockProps()}>
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
            </div>
        </Fragment>
    );
}
