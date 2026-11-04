import { Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import ViewTicketInspectorControls from './InspectorSettings';
import { BackNav, StatusBadge } from '../PreviewParts';

// Mirrors resources/customer_portal/components/Ticket.vue with a sample conversation
export const ViewTicketBlock = ({attributes, setAttributes, showSection}) => {
    const [showPrivacyNotice, setShowPrivacyNotice] = useState(true);

    return (
        <Fragment>
            <ViewTicketInspectorControls attributes={attributes} setAttributes={setAttributes}/>
            <div className="fs_ticket_wrapper fs_ticket">
                <BackNav onBack={() => showSection('allTickets')}/>
                <div className="fs_tickets_container">
                    <div className="fs_ticket_heroarea">
                        <div className="fs_tk_subject">
                            <div className="fs_ticket_heading">
                                <h2 className="fs_ticket_subject">{__('Unable to log in after password reset', 'fluent-support')}</h2>
                                <div className="fs_ticket_meta">
                                    <span> #<strong>2053</strong></span>
                                </div>
                            </div>
                            <StatusBadge tag="div" className="capitalized" status="active"
                                         label={__('Active', 'fluent-support')}/>
                        </div>
                        <div className="fs_ticket_actions">
                            <div className="fs_product_name"></div>
                            <div className="fs_ticket_actions_btn">
                                <div className="fs_ticket_refresh_btn">
                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M3.09725 2.32476C4.45817 1.1455 6.19924 0.497495 8 0.500007C12.1423 0.500007 15.5 3.85776 15.5 8.00001C15.5 9.60201 14.9975 11.087 14.1425 12.305L11.75 8.00001H14C14.0001 6.82373 13.6544 5.67336 13.006 4.69195C12.3576 3.71054 11.4349 2.94138 10.3529 2.4801C9.27082 2.01882 8.07704 1.88578 6.91997 2.09752C5.7629 2.30926 4.69359 2.85643 3.845 3.67101L3.09725 2.32476ZM12.9028 13.6753C11.5418 14.8545 9.80076 15.5025 8 15.5C3.85775 15.5 0.5 12.1423 0.5 8.00001C0.5 6.39801 1.0025 4.91301 1.8575 3.69501L4.25 8.00001H2C1.9999 9.17629 2.34556 10.3267 2.994 11.3081C3.64244 12.2895 4.56505 13.0586 5.64712 13.5199C6.72918 13.9812 7.92296 14.1142 9.08003 13.9025C10.2371 13.6908 11.3064 13.1436 12.155 12.329L12.9028 13.6753Z"
                                            fill="currentColor"/>
                                    </svg>
                                    <span>{__('Refresh', 'fluent-support')}</span>
                                </div>
                                <div className="fs_close_ticket">
                                    <span className="fs_close_ticket_title">{__('Close Ticket', 'fluent-support')}</span>
                                </div>
                            </div>
                        </div>
                        <div className="fs_ticket_response">
                            {showPrivacyNotice && (
                                <div className="fs_ticket_alert">
                                    <div className="el-alert el-alert--info is-light" role="alert">
                                        <i className="el-icon el-alert__icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                <path fill="currentColor"
                                                      d="M512 64a448 448 0 1 1 0 896.064A448 448 0 0 1 512 64m67.2 275.072c33.28 0 60.288-23.104 60.288-57.344s-27.072-57.344-60.288-57.344c-33.28 0-60.16 23.104-60.16 57.344s26.88 57.344 60.16 57.344M590.912 699.2c0-6.848 2.368-24.64 1.024-34.752l-52.608 60.544c-10.88 11.456-24.512 19.392-30.912 17.28a12.992 12.992 0 0 1-8.256-14.72l87.68-276.992c7.168-35.136-12.544-67.2-54.336-71.296-44.096 0-108.992 44.736-148.48 101.504 0 6.784-1.28 23.68.064 33.792l52.544-60.608c10.88-11.328 23.552-19.328 29.952-17.152a12.8 12.8 0 0 1 7.808 16.128L388.48 728.576c-10.048 32.256 8.96 63.872 55.04 71.04 67.84 0 107.904-43.648 147.456-100.416z"/>
                                            </svg>
                                        </i>
                                        <div className="el-alert__content">
                                            <span className="el-alert__title">{__('This ticket is Private. Only you and official support agents can view this conversation', 'fluent-support')}</span>
                                            <i className="el-icon el-alert__close-btn" onClick={() => setShowPrivacyNotice(false)}>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
                                                    <path fill="currentColor"
                                                          d="M764.288 214.592 512 466.88 259.712 214.592a31.936 31.936 0 0 0-45.12 45.12L466.752 512 214.528 764.224a31.936 31.936 0 1 0 45.12 45.184L512 557.184l252.288 252.288a31.936 31.936 0 0 0 45.12-45.12L557.12 512.064l252.288-252.352a31.936 31.936 0 1 0-45.12-45.184z"/>
                                                </svg>
                                            </i>
                                        </div>
                                    </div>
                                </div>
                            )}
                            <div className="fs_ticket_reply_box">
                                <h3>{__('Write a reply', 'fluent-support')}</h3>
                                <textarea className="fs_ticket_reply_text" readOnly
                                          placeholder={__('Click Here to Write a reply', 'fluent-support')}></textarea>
                            </div>
                        </div>
                    </div>

                    <div className="fs_ticket_body">
                        <div className="fs_ticket_threads_container">
                            <article className="fs_customer_conversation fs_ticket_thread fs_person_agent fs_agent fs_conv_type_response">
                                <div className="fs_ticket_thread_content">
                                    <section className="fs_ticket_avatar fs_block_avatar_initials">AM</section>
                                    <section className="fs_ticket_thread_wrap">
                                        <div className="fs_thread_message">
                                            <div className="fs_thread_head">
                                                <div className="fs_thread_name">
                                                    <strong>{__('Alex Morgan', 'fluent-support')}</strong> <span
                                                    className="fs_thread_ribbon fs_thread_ribbon_agent">{__('Support Staff', 'fluent-support')}</span>
                                                </div>
                                            </div>
                                            <div className="fs_thread_body">
                                                <p>{__('Thanks for the details. I have reset your session from our side. Please clear your browser cache and try logging in again with your new password.', 'fluent-support')}</p>
                                            </div>
                                            <div className="fs_actions_head">
                                                <div className="fs_thread_actions">{__('2 hours ago', 'fluent-support')}</div>
                                            </div>
                                        </div>
                                    </section>
                                </div>
                            </article>
                            <article className="fs_ticket_thread fs_conversion_starter">
                                <div className="fs_conversion_starter_section">
                                    <div className="fs_join_thread_custom_field">
                                        <div className="fs_ticket_thread_content fs_starter_thread_content">
                                            <section className="fs_ticket_avatar fs_block_avatar_initials">JD</section>
                                            <section className="fs_ticket_thread_wrap">
                                                <section className="fs_thread_message">
                                                    <div className="fs_thread_head">
                                                        <div className="fs_thread_name">
                                                            <strong>{__('You', 'fluent-support')}</strong> {__('started the conversation', 'fluent-support')}
                                                        </div>
                                                    </div>
                                                    <div className="fs_thread_body">
                                                        <p>{__('After resetting my password, I am unable to log in. The new credentials are not being recognized.', 'fluent-support')}</p>
                                                    </div>
                                                    <div className="fs_actions_head">
                                                        <div className="fs_thread_actions">{__('Yesterday', 'fluent-support')}</div>
                                                    </div>
                                                </section>
                                            </section>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </Fragment>
    );
};

export default ViewTicketBlock;
