export default function ThreadItem(props) {
    const {
        threadArticleStyleClass,
        getThreadTailStyle,
        threadStyleClass,
        activeClass,
        ribbonTypeStyleClass,
        getRibbonHeaderStyle,
        preventParentPropagation,
        ribbonStyle,
        threadContent
    } = props;
    return (
        <article className={`show-thread ${threadArticleStyleClass}`} style={getThreadTailStyle}>
            {threadContent.type !== 'starter' && (
            <div  className={`${threadStyleClass} ${activeClass}`}  onClick={(e) =>preventParentPropagation(ribbonStyle, e)}>
                <span className={`${ribbonTypeStyleClass}`} style={getRibbonHeaderStyle}>
                    {threadContent.type}
                </span>
            </div>)}
            <div className="show-thread-content">
                <section className="show-avatar"><img
                    src={threadContent.avatar}
                    alt={threadContent.alterAvatar}/>
                </section>
                <section className="show-thread-wrap">
                    <section className="show-thread-message">
                        <div className="show-thread-head">
                            <div className="show-thread-title">
                                <strong>{threadContent.person}</strong> {threadContent.title}
                            </div>
                            <div className="show-thread-actions"
                            >{threadContent.date}
                            </div>
                        </div>
                        <div className="show-thread-body">
                            <p>{threadContent.body}</p>
                        </div>
                    </section>
                </section>
            </div>
        </article>
    );
}
