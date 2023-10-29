/**
 * A component representing a single thread item for the ViewTicket component.
 * @param {Object} props - The component's props.
 * @param {string} props.threadArticleStyleClass - CSS class for the article element.
 * @param {function} props.getThreadTailStyle - Function to get the thread tail style.
 * @param {string} props.threadStyleClass - CSS class for the thread element.
 * @param {string} props.activeClass - CSS class for active elements.
 * @param {string} props.ribbonTypeStyleClass - CSS class for ribbon type elements.
 * @param {function} props.getRibbonHeaderStyle - Function to get the ribbon header style.
 * @param {function} props.preventParentPropagation - Function to prevent parent event propagation.
 * @param {Object} props.ribbonStyle - CSS style for the ribbon.
 * @param {Object} props.threadContent - Content for the thread.
 * @returns {JSX.Element} The ThreadItem component.
 */
export default function ThreadItem(props) {
    const {
        threadArticleStyleClass,
        getThreadTailStyle,
        threadStyleClass,
        activeClassName,
        activeClass,
        ribbonTypeStyleClass,
        getRibbonHeaderStyle,
        preventParentPropagation,
        ribbonStyle,
        dateTimeColorPicker,
        contentColorPicker,
        threadContent,
        pageBodyStyle
    } = props;

    return (
        <article className={`show-thread ${threadArticleStyleClass}`} style={getThreadTailStyle}>
            {threadContent.type !== 'starter' && (
                <div className={`${threadStyleClass} ${activeClass(activeClassName)}`} onClick={(e) => preventParentPropagation(ribbonStyle, e)}>
                    <span className={`${ribbonTypeStyleClass}`} style={getRibbonHeaderStyle}>
                        {threadContent.type}
                    </span>
                </div>
            )}
            <div className="show-thread-content">
                <section className="show-avatar">
                    <img src={threadContent.avatar} alt={threadContent.alterAvatar} />
                </section>
                <section className="show-thread-wrap">
                    <section className="show-thread-message">
                        <div className="show-thread-head">
                            <div className="show-thread-title" style={pageBodyStyle.actionColor}>
                                <p>
                                    <span className={`show-thread-title-person ${activeClass('personColorPickerStyle')}`} onClick={(e) => preventParentPropagation('personColorPickerStyle', e)}>
                                        <strong style={pageBodyStyle.nameColor}>{threadContent.person}</strong>
                                    </span>&nbsp;
                                    <span className={`show-thread-title-action ${activeClass('actionColorPickerStyle')}`} onClick={(e) => preventParentPropagation('actionColorPickerStyle', e)}>
                                        {threadContent.title}</span>
                                </p>
                            </div>
                            <div className={`show-thread-action-datetime ${activeClass('dateTimeColorPickerStyle')}`} style={pageBodyStyle.dateColor} onClick={(e) => preventParentPropagation('dateTimeColorPickerStyle', e)}>
                                {threadContent.date}
                            </div>
                        </div>
                        <div className={`show-thread-body ${activeClass('contentColorPickerStyle')}`} style={pageBodyStyle.contentColor}  onClick={(e) => preventParentPropagation('contentColorPickerStyle', e)}>
                            <p>{threadContent.body}</p>
                        </div>
                    </section>
                </section>
            </div>
        </article>
    );
}
