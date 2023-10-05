const {__} = wp.i18n;
const {PanelBody} = wp.components;
export default function AllTicketsTableHeaderAdvanced({attributes, setAttributes}) {
    return (
        <PanelBody title={__('Header', 'fluent-support')}>
            <p><strong>{__('Text Align', 'fluent-support')}</strong></p>
            <div className="alignment-buttons-group">
                <button
                    className={`alignment-button left-button ${attributes.allTicketsTableHeaderTextAlign === 'left' ? 'active' : ''}`}
                    onClick={() => setAttributes({allTicketsTableHeaderTextAlign: 'left'})}
                >
                    <svg width="17" height="14" viewBox="0 0 17 14" fill="currentColor"
                         xmlns="http://www.w3.org/2000/svg">
                        <g id="Frame" clip-path="url(#clip0_225_1898)">
                            <g id="Shape">
                                <path
                                    d="M0.833008 1.375H15.833C16.1747 1.375 16.458 1.09167 16.458 0.75C16.458 0.408333 16.1747 0.125 15.833 0.125H0.833008C0.491341 0.125 0.208008 0.408333 0.208008 0.75C0.208008 1.09167 0.491341 1.375 0.833008 1.375Z"
                                    fill="#697483"/>
                                <path
                                    d="M0.833008 5.54167H8.72467C9.07467 5.54167 9.34967 5.25833 9.34967 4.91667C9.34967 4.575 9.06634 4.29167 8.72467 4.29167H0.833008C0.491341 4.29167 0.208008 4.575 0.208008 4.91667C0.208008 5.25833 0.491341 5.54167 0.833008 5.54167Z"
                                    fill="#697483"/>
                                <path
                                    d="M15.833 9.70833H0.833008C0.491341 9.70833 0.208008 9.425 0.208008 9.08333C0.208008 8.74167 0.491341 8.45833 0.833008 8.45833H15.833C16.1747 8.45833 16.458 8.74167 16.458 9.08333C16.458 9.425 16.1747 9.70833 15.833 9.70833Z"
                                    fill="#697483"/>
                                <path
                                    d="M0.833008 13.875H8.72467C9.07467 13.875 9.34967 13.5917 9.34967 13.25C9.34967 12.9083 9.06634 12.625 8.72467 12.625H0.833008C0.491341 12.625 0.208008 12.9083 0.208008 13.25C0.208008 13.5917 0.491341 13.875 0.833008 13.875Z"
                                    fill="#697483"/>
                            </g>
                        </g>
                        <defs>
                            <clipPath id="clip0_225_1898">
                                <rect width="16.25" height="13.75" fill="white"
                                      transform="translate(0.208008 0.125)"/>
                            </clipPath>
                        </defs>
                    </svg>
                </button>
                <button
                    className={`alignment-button center-button ${attributes.allTicketsTableHeaderTextAlign === 'center' ? 'active' : ''}`}
                    onClick={() => setAttributes({allTicketsTableHeaderTextAlign: 'center'})}
                >
                    <svg width="18" height="14" viewBox="0 0 18 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="Frame" clip-path="url(#clip0_225_2963)">
                            <g id="Shape">
                                <path
                                    d="M16.5 1.375H1.5C1.15833 1.375 0.875 1.09167 0.875 0.75C0.875 0.408333 1.15833 0.125 1.5 0.125H16.5C16.8417 0.125 17.125 0.408333 17.125 0.75C17.125 1.09167 16.8417 1.375 16.5 1.375Z"
                                    fill="#697483"/>
                                <path
                                    d="M12.95 5.54167H5.05C4.70833 5.54167 4.425 5.25833 4.425 4.91667C4.425 4.575 4.70833 4.29167 5.05 4.29167H12.9417C13.2833 4.29167 13.5667 4.575 13.5667 4.91667C13.5667 5.25833 13.2917 5.54167 12.95 5.54167Z"
                                    fill="#697483"/>
                                <path
                                    d="M16.5 9.70833H1.5C1.15833 9.70833 0.875 9.425 0.875 9.08333C0.875 8.74167 1.15833 8.45833 1.5 8.45833H16.5C16.8417 8.45833 17.125 8.74167 17.125 9.08333C17.125 9.425 16.8417 9.70833 16.5 9.70833Z"
                                    fill="#697483"/>
                                <path
                                    d="M12.95 13.875H5.05C4.70833 13.875 4.425 13.5917 4.425 13.25C4.425 12.9083 4.70833 12.625 5.05 12.625H12.9417C13.2833 12.625 13.5667 12.9083 13.5667 13.25C13.5667 13.5917 13.2917 13.875 12.95 13.875Z"
                                    fill="#697483"/>
                            </g>
                        </g>
                        <defs>
                            <clipPath id="clip0_225_2963">
                                <rect width="16.25" height="13.75" fill="white" transform="translate(0.875 0.125)"/>
                            </clipPath>
                        </defs>
                    </svg>

                </button>
                <button
                    className={`alignment-button right-button ${attributes.allTicketsTableHeaderTextAlign === 'right' ? 'active' : ''}`}
                    onClick={() => setAttributes({allTicketsTableHeaderTextAlign: 'right'})}
                >
                    <svg width="17" height="14" viewBox="0 0 17 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <g id="Frame" clip-path="url(#clip0_225_2711)">
                            <g id="Shape">
                                <path
                                    d="M16.166 1.375H1.16602C0.824349 1.375 0.541016 1.09167 0.541016 0.75C0.541016 0.408333 0.824349 0.125 1.16602 0.125H16.166C16.5077 0.125 16.791 0.408333 16.791 0.75C16.791 1.09167 16.5077 1.375 16.166 1.375Z"
                                    fill="#697483"/>
                                <path
                                    d="M16.1654 5.54167H8.27376C7.9321 5.54167 7.64876 5.25833 7.64876 4.91667C7.64876 4.575 7.9321 4.29167 8.27376 4.29167H16.1654C16.5071 4.29167 16.7904 4.575 16.7904 4.91667C16.7904 5.25833 16.5071 5.54167 16.1654 5.54167Z"
                                    fill="#697483"/>
                                <path
                                    d="M16.166 9.70833H1.16602C0.824349 9.70833 0.541016 9.425 0.541016 9.08333C0.541016 8.74167 0.824349 8.45833 1.16602 8.45833H16.166C16.5077 8.45833 16.791 8.74167 16.791 9.08333C16.791 9.425 16.5077 9.70833 16.166 9.70833Z"
                                    fill="#697483"/>
                                <path
                                    d="M16.1654 13.875H8.27376C7.9321 13.875 7.64876 13.5917 7.64876 13.25C7.64876 12.9083 7.9321 12.625 8.27376 12.625H16.1654C16.5071 12.625 16.7904 12.9083 16.7904 13.25C16.7904 13.5917 16.5071 13.875 16.1654 13.875Z"
                                    fill="#697483"/>
                            </g>
                        </g>
                        <defs>
                            <clipPath id="clip0_225_2711">
                                <rect width="16.25" height="13.75" fill="white" transform="translate(0.541016 0.125)"/>
                            </clipPath>
                        </defs>
                    </svg>
                </button>
            </div>
        </PanelBody>
    )
}
