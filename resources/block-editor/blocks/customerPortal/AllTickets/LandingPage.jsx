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

    // Get styles from the utility function in TicketsInspectorControls
    const {
        blockStyles,
        primaryButtonStyles,
    } = generateStyles(blockAttributes);

    // These handler functions should stay in the main component since they interact with state variables
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
            <div className="fs_tickets_block_container" style={blockStyles}>
                {/* Header */}
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
