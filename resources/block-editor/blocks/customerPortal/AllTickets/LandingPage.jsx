const {Fragment, useState} = wp.element;
const {
    SelectControl,
    TextControl,
    Button,
    Spinner,
    SearchControl,
    ToggleControl,
} = wp.components;
const {__} = wp.i18n;

import './all-tickets.scss';
import TicketsInspectorControls, {generateStyles} from './InspectorSettings';

export const TicketsLandingBlock = props => {
    const {attributes: blockAttributes, setAttributes, showSection} = props;

    const [loading, setLoading] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [currentPage, setCurrentPage] = useState(2);
    const [currentFilter, setCurrentFilter] = useState('all');
    const [selectedProduct, setSelectedProduct] = useState('All Products');
    const totalPages = 16;

    // Sample ticket data that matches the screenshot
    const sampleTickets = Array(5).fill({
        id: 1,
        title: "Sudden charge without notice",
        description: "This is Zawad from Authlab, facing a isshue about...",
        date: "05 Jul, 2024",
        status: "active",
        count: 2
    }).map((ticket, index) => ({...ticket, id: index + 1}));

    const {
        blockStyles,
        primaryButtonStyles,
        showLogout
    } = generateStyles(blockAttributes);

    const handlePageChange = (page) => {
        setCurrentPage(page);
    };

    const handleFilterChange = (filter) => {
        setCurrentFilter(filter);
    };

    return (
        <Fragment>
            <TicketsInspectorControls
                attributes={blockAttributes}
                setAttributes={setAttributes}
            />
            {(showLogout.showLogoutButton) && (
            <div className={'fs_logout_container'}>
                <div className="fs_logout_button">
                <a
                    href="#"
                    className="fs_btn fs_btn_logout"
                >
                    <svg
                        className="fs_logout_icon"
                        width="20"
                        height="20"
                        viewBox="0 0 20 20"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M4.75 17.5C4.55109 17.5 4.36032 17.421 4.21967 17.2803C4.07902 17.1397 4 16.9489 4 16.75V3.25C4 3.05109 4.07902 2.86032 4.21967 2.71967C4.36032 2.57902 4.55109 2.5 4.75 2.5H15.25C15.4489 2.5 15.6397 2.57902 15.7803 2.71967C15.921 2.86032 16 3.05109 16 3.25V5.5H14.5V4H5.5V16H14.5V14.5H16V16.75C16 16.9489 15.921 17.1397 15.7803 17.2803C15.6397 17.421 15.4489 17.5 15.25 17.5H4.75ZM14.5 13V10.75H9.25V9.25H14.5V7L18.25 10L14.5 13Z"
                            fill="#FB3748"
                        />
                    </svg>
                    <span className="fs_logout_text">Log Out</span>
                </a>
            </div>
            </div>
            )}
            <div className="fs_tickets_block_container" style={blockStyles}>
                <div className="fs_tickets_header">
                    <label className="fs_tickets_title">
                        {'All Tickets'}
                    </label>
                    <button className="fs_create_ticket_button" style={primaryButtonStyles}
                            onClick={() => showSection('createTicket')}>
                        <span className="fs_plus_icon">+</span>
                        {'Create Ticket'}
                    </button>
                </div>

                <div className="fs_tickets_filters">
                    <div className="fs_filters_left">
                        <div className={'fs_status_filter'}>
                            <button
                                className={`fs_filter_button ${currentFilter === 'all' ? 'active' : ''}`}
                                onClick={() => handleFilterChange('all')}
                            >
                                All
                            </button>
                            <button
                                className={`fs_filter_button ${currentFilter === 'open' ? 'active' : ''}`}
                                onClick={() => handleFilterChange('open')}
                            >
                                Open
                            </button>
                            <button
                                className={`fs_filter_button ${currentFilter === 'closed' ? 'active' : ''}`}
                                onClick={() => handleFilterChange('closed')}
                            >
                                Closed
                            </button>
                        </div>
                        <div className="fs_product_dropdown">
                            <select
                                value={selectedProduct}
                                onChange={(e) => setSelectedProduct(e.target.value)}
                            >
                                <option value="All Products">All Products</option>
                                <option value="Product 1">Product 1</option>
                                <option value="Product 2">Product 2</option>
                            </select>
                        </div>

                        <button className="fs_filter_toggle_button">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 4H21V6H3V4ZM5 11H19V13H5V11ZM7 18H17V20H7V18Z" fill="currentColor"/>
                            </svg>
                        </button>
                    </div>

                    <div className="fs_filters_right">
                        <div className="fs_search_wrapper">
                            <input
                                type="text"
                                placeholder="Search..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="fs_search_input"
                            />
                        </div>
                    </div>
                </div>

                {/* Table Header */}
                <div className={'fs_ticket_table'}>
                    <div className="fs_tickets_table_header">
                        <div className="fs_header_conversation">Conversation</div>
                        <div className="fs_header_date">
                            Date
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path d="M7 10l5 5 5-5z" fill="currentColor"/>
                            </svg>
                        </div>
                        <div className="fs_header_status">Status</div>
                    </div>

                    {/* Tickets List */}
                    <div className="fs_tickets_list">
                        {sampleTickets.map(ticket => (
                            <div key={ticket.id} className="fs_ticket_item"
                                 onClick={() => showSection('viewTicket')}
                                 style={{cursor: 'pointer'}}>
                                <div className="fs_ticket_conversation">
                                    <div className="fs_ticket_title">
                                        {ticket.title}
                                        <span className="fs_ticket_count">{ticket.count}</span>
                                    </div>
                                    <div className="fs_ticket_description">
                                        {ticket.description}
                                    </div>
                                </div>
                                <div className="fs_ticket_date">
                                    {ticket.date}
                                </div>
                                <span className="fs_status_badge">
                                    <span className="fs_status_dot"></span>
                                    {ticket.status}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Pagination */}
                <div className="fs_tickets_pagination">
                    <div className={'fs_pagination_left'}>
                        <div className="fs_pagination_info">
                            Page {currentPage} of {totalPages}
                        </div>

                        <div className="fs_pagination_per_page">
                            <select value={`${blockAttributes.perPage || 10} / page`}>
                                <option value="10 / page">10 / page</option>
                                <option value="20 / page">20 / page</option>
                                <option value="30 / page">30 / page</option>
                            </select>
                        </div>
                    </div>
                    <div className={'fs_pagination_right'}>
                        <div className="fs_pagination_controls">
                            <button className="fs_pagination_button"
                                    onClick={() => handlePageChange(Math.max(1, currentPage - 1))}>‹
                            </button>

                            <button
                                className={`fs_pagination_button ${currentPage === 1 ? 'active' : ''}`}
                                onClick={() => handlePageChange(1)}>1
                            </button>
                            <button
                                className={`fs_pagination_button ${currentPage === 2 ? 'active' : ''}`}
                                onClick={() => handlePageChange(2)}>2
                            </button>
                            <button
                                className={`fs_pagination_button ${currentPage === 3 ? 'active' : ''}`}
                                onClick={() => handlePageChange(3)}>3
                            </button>
                            <button
                                className={`fs_pagination_button ${currentPage === 4 ? 'active' : ''}`}
                                onClick={() => handlePageChange(4)}>4
                            </button>
                            <button
                                className={`fs_pagination_button ${currentPage === 5 ? 'active' : ''}`}
                                onClick={() => handlePageChange(5)}>5
                            </button>

                            <span className="pagination-ellipsis">...</span>

                            <button
                                className={`fs_pagination_button ${currentPage === totalPages ? 'active' : ''}`}
                                onClick={() => handlePageChange(totalPages)}>
                                {totalPages}
                            </button>

                            <button className="fs_pagination_button"
                                    onClick={() => handlePageChange(Math.min(totalPages, currentPage + 1))}>›
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Fragment>
    );
};

export default TicketsLandingBlock;
