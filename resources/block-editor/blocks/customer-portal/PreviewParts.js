// Static copies of the portal pieces the preview reuses. The markup follows what
// resources/customer_portal renders (Element Plus included) so the portal CSS styles it.
import { __ } from '@wordpress/i18n';

export const ChevronDownIcon = () => (
    <i className="el-icon el-select__caret el-select__icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
            <path fill="currentColor"
                  d="M831.872 340.864 512 652.672 192.128 340.864a30.592 30.592 0 0 0-42.752 0 29.12 29.12 0 0 0 0 41.6L489.664 714.24a32 32 0 0 0 44.672 0l340.288-331.712a29.12 29.12 0 0 0 0-41.728 30.592 30.592 0 0 0-42.752 0z"/>
        </svg>
    </i>
);

export const SelectMock = ({value, className = ''}) => (
    <div className={`el-select ${className}`}>
        <div className="el-select__wrapper">
            <div className="el-select__selection">
                <div className="el-select__selected-item el-select__placeholder">
                    <span>{value}</span>
                </div>
            </div>
            <div className="el-select__suffix">
                <ChevronDownIcon/>
            </div>
        </div>
    </div>
);

export const BackNav = ({onBack}) => (
    <div className="fs_back_nav">
        <button type="button" className="el-button is-link fs_back_button" onClick={onBack}>
            <span>
                <svg className="fs_svg_back" width="6" height="10" viewBox="0 0 6 10" fill="none"
                     xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M2.2045 4.99907L5.917 8.71157L4.8565 9.77207L0.0834961 4.99907L4.8565 0.226074L5.917 1.28657L2.2045 4.99907Z"
                        fill="currentColor"/>
                </svg>
                <span className="fs_back_btn">{__('Back to All Tickets', 'fluent-support')}</span>
            </span>
        </button>
    </div>
);

export const StatusBadge = ({status, label, tag = 'span', className = ''}) => {
    const Tag = tag;
    return (
        <Tag className={`fs_status_badge ${className} ${status}`}>
            <span className="fs_status_dot"></span> {label}
        </Tag>
    );
};
