import { Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import TicketsInspectorControls from './InspectorSettings';
import { SelectMock, StatusBadge } from '../PreviewParts';

// Mirrors resources/customer_portal/components/Tickets.vue with sample tickets
const sampleTickets = () => [
    {
        id: 1,
        title: __('Unable to log in after password reset', 'fluent-support'),
        preview: __('After resetting my password, I am unable to log in. The new credentials are not being recognized.', 'fluent-support'),
        status: 'active',
        statusLabel: __('Active', 'fluent-support'),
        replies: 3,
        waitingOnYou: true,
        date: __('2 hours ago', 'fluent-support')
    },
    {
        id: 2,
        title: __('Payment processed but service not activated', 'fluent-support'),
        preview: __('I was charged for my subscription renewal, but my account still shows inactive. Please check.', 'fluent-support'),
        status: 'new',
        statusLabel: __('New', 'fluent-support'),
        replies: 0,
        date: __('Yesterday', 'fluent-support')
    },
    {
        id: 3,
        title: __('Delayed email notifications for support tickets', 'fluent-support'),
        preview: __('I am not receiving email notifications when a support ticket is updated. This issue started recently.', 'fluent-support'),
        status: 'active',
        statusLabel: __('Active', 'fluent-support'),
        replies: 1,
        date: __('3 days ago', 'fluent-support')
    },
    {
        id: 4,
        title: __('Request for account data export', 'fluent-support'),
        preview: __('I need to export my account data. Can you provide a downloadable report?', 'fluent-support'),
        status: 'closed',
        statusLabel: __('Closed', 'fluent-support'),
        replies: 2,
        date: __('2 weeks ago', 'fluent-support')
    }
];

const statusFilters = () => [
    {key: 'all', label: __('All', 'fluent-support')},
    {key: 'open', label: __('Open', 'fluent-support')},
    {key: 'closed', label: __('Closed', 'fluent-support')}
];

export const TicketsLandingBlock = ({attributes, setAttributes, showSection}) => {
    const [currentFilter, setCurrentFilter] = useState('all');

    const tickets = sampleTickets().filter(ticket => {
        if (currentFilter === 'all') {
            return true;
        }
        return currentFilter === 'closed' ? ticket.status === 'closed' : ticket.status !== 'closed';
    });

    return (
        <Fragment>
            <TicketsInspectorControls attributes={attributes} setAttributes={setAttributes}/>
            <div className="fst_tickets">
                <div className="fs_ticket_wrapper">
                    <div className="fs_tickets_container">
                        <div className="fs_tickets_header">
                            <h2 className="fs_tickets_heading">{__('All Tickets', 'fluent-support')}</h2>
                            <div className="fs_tickets_actions">
                                <button type="button" className="el-button fs_create_ticket_btn"
                                        onClick={() => showSection('createTicket')}>
                                    <span>+ {__('Create Ticket', 'fluent-support')}</span>
                                </button>
                                {attributes.showLogoutButton && (
                                    <button type="button" className="el-button fs_logout_btn">
                                        <span>
                                            <svg width="18" height="18" viewBox="0 0 20 20" fill="none"
                                                 aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M4.75 17.5C4.55109 17.5 4.36032 17.421 4.21967 17.2803C4.07902 17.1397 4 16.9489 4 16.75V3.25C4 3.05109 4.07902 2.86032 4.21967 2.71967C4.36032 2.57902 4.55109 2.5 4.75 2.5H15.25C15.4489 2.5 15.6397 2.57902 15.7803 2.71967C15.921 2.86032 16 3.05109 16 3.25V5.5H14.5V4H5.5V16H14.5V14.5H16V16.75C16 16.9489 15.921 17.1397 15.7803 17.2803C15.6397 17.421 15.4489 17.5 15.25 17.5H4.75ZM14.5 13V10.75H9.25V9.25H14.5V7L18.25 10L14.5 13Z"
                                                    fill="currentColor"/>
                                            </svg>
                                            <span>{__('Log Out', 'fluent-support')}</span>
                                        </span>
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="fs_filters_section">
                            <div className="fs_filter_container">
                                <div className="fs_filter_left">
                                    <div className="fs_status_filter">
                                        <div className="fs_button_groups">
                                            {statusFilters().map(filter => (
                                                <button key={filter.key} type="button"
                                                        className={`status-btn ${currentFilter === filter.key ? 'active' : ''}`}
                                                        onClick={() => setCurrentFilter(filter.key)}>
                                                    {filter.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="fs_product_filter_head">
                                        <div className="fs_sorting">
                                            <button type="button" className="el-button"
                                                    aria-label={__('Sort', 'fluent-support')}>
                                                <span>
                                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                         xmlns="http://www.w3.org/2000/svg">
                                                        <path
                                                            d="M16 4V13H18.25L15.25 16.75L12.25 13H14.5V4H16ZM10 14.5V16H3.25V14.5H10ZM11.5 9.25V10.75H3.25V9.25H11.5ZM11.5 4V5.5H3.25V4H11.5Z"
                                                            fill="#99A0AE"/>
                                                    </svg>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div className="fs_filter_right">
                                    <div className="fs_search_filter">
                                        <div className="el-input el-input--prefix search-input">
                                            <div className="el-input__wrapper">
                                                <span className="el-input__prefix">
                                                    <span className="el-input__prefix-inner">
                                                        <svg className="search-icon" viewBox="0 0 24 24"
                                                             xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                                                  stroke="currentColor" strokeWidth="2" fill="none"/>
                                                        </svg>
                                                    </span>
                                                </span>
                                                <input className="el-input__inner" type="text" readOnly
                                                       placeholder={__('Search...', 'fluent-support')}/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="fs_ticket_list_wrap">
                            <ul className="fs_ticket_list">
                                {tickets.map(ticket => (
                                    <li key={ticket.id} className="fs_ticket_item">
                                        <a href="#" className="fs_ticket_link" onClick={(event) => {
                                            event.preventDefault();
                                            showSection('viewTicket');
                                        }}>
                                            <div className="fs_ticket_main">
                                                <div className="fs_ticket_title">
                                                    <strong>{ticket.title}</strong>
                                                    {ticket.replies > 0 && (
                                                        <span className="fs_response_count">{ticket.replies}</span>
                                                    )}
                                                </div>
                                                <p className="fs_ticket_preview">{ticket.preview}</p>
                                            </div>
                                            <div className="fs_ticket_meta">
                                                {ticket.waitingOnYou && (
                                                    <span className="fs_waiting_on_you">{__('Waiting for your reply', 'fluent-support')}</span>
                                                )}
                                                <StatusBadge status={ticket.status} label={ticket.statusLabel}/>
                                                <span className="fs_ticket_date">{ticket.date}</span>
                                            </div>
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="fs_pagination_section" style={{paddingBottom: '20px'}}>
                            <div className="fs_pagination_container">
                                <div className="fs_pagination_left">
                                    <span className="fs_page_text">{__('Page 1 of 3', 'fluent-support')}</span>
                                    <SelectMock className="fs_per_page_select" value={__('10 / page', 'fluent-support')}/>
                                </div>
                                <div className="el-pagination">
                                    <button type="button" className="btn-prev is-first" disabled
                                            aria-label={__('Go to previous page', 'fluent-support')}>
                                        <i className="el-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                <path fill="currentColor"
                                                      d="M609.408 149.376 277.76 489.6a32 32 0 0 0 0 44.672l331.648 340.352a29.12 29.12 0 0 0 41.728 0 30.592 30.592 0 0 0 0-42.752L339.264 511.936l311.872-319.872a30.592 30.592 0 0 0 0-42.688 29.12 29.12 0 0 0-41.728 0z"/>
                                            </svg>
                                        </i>
                                    </button>
                                    <ul className="el-pager">
                                        <li className="is-active number">1</li>
                                        <li className="number">2</li>
                                        <li className="number">3</li>
                                    </ul>
                                    <button type="button" className="btn-next"
                                            aria-label={__('Go to next page', 'fluent-support')}>
                                        <i className="el-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                <path fill="currentColor"
                                                      d="M340.864 149.312a30.592 30.592 0 0 0 0 42.752L652.736 512 340.864 831.872a30.592 30.592 0 0 0 0 42.752 29.12 29.12 0 0 0 41.728 0L714.24 534.336a32 32 0 0 0 0-44.672L382.592 149.376a29.12 29.12 0 0 0-41.728 0z"/>
                                            </svg>
                                        </i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Fragment>
    );
};

export default TicketsLandingBlock;
