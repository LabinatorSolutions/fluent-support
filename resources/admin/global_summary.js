/* eslint-disable */
import '../scss/globalSummary.scss';

const elem = function (tagName, attributes, children, isHTML = null) {
    let parent;

    if (typeof tagName == "string") {
        parent = document.createElement(tagName);
    } else if (tagName instanceof HTMLElement) {
        parent = tagName;
    }

    if (attributes) {
        for (let attribute in attributes) {
            parent.setAttribute(attribute, attributes[attribute]);
        }
    }

    if (children || children == 0) {
        elem.append(parent, children, isHTML);
    }

    return parent;
};

elem.append = function (parent, children, isHTML) {
    if (parent instanceof HTMLTextAreaElement || parent instanceof HTMLInputElement) {

        if (children instanceof Text || typeof children == "string" || typeof children == "number") {
            parent.value = children;
        } else if (children instanceof Array) {
            children.forEach(function (child) {
                elem.append(parent, child);
            });
        } else if (typeof children == "function") {
            elem.append(parent, children());
        }

    } else {

        if (children instanceof HTMLElement || children instanceof Text) {
            parent.appendChild(children);
        } else if (typeof children == "string" || typeof children == "number") {
            if (isHTML) {
                parent.innerHTML += children;
            } else {
                parent.appendChild(document.createTextNode(children));
            }
        } else if (children instanceof Array) {
            children.forEach(function (child) {
                elem.append(parent, child);
            });
        } else if (typeof children == "function") {
            elem.append(parent, children());
        }

    }
};

function trans(string) {
    return window.fst_bar_vars.trans[string] || string;
}

const FsGlobalSummary = {
    init() {
        this.initButton();
    },
    current_page: 1,
    initButton() {
        const that = this;
        let show_summary = window.fst_bar_vars.show_summary;
        const parentElement = document.getElementById('wp-admin-bar-fst_global_summary');
        const $parent = jQuery('#wp-admin-bar-fst_global_summary');

        if(!show_summary){
            parentElement.classList.add("fs_ts_hide_me");
        }

        const mainDom = this.getSearchDom();

        mainDom.append(this.getQuickLinks());

        parentElement.append(mainDom);

        $parent.on('mouseenter', function () {
            const $container = $parent.find('.fs_summary_container');
            $container.addClass('fs_show');
        }).on('mouseleave', function () {
            const $container = $parent.find('.fs_summary_container');
            $container.removeClass('fs_show');
        });

    },
    getSearchDom() {
        return elem('div', {class: 'fs_summary_container'}, '', '');
    },
    getQuickLinks() {
        const linkDoms = [];
        jQuery.each(window.fst_bar_vars.links, (index, link) => {
            linkDoms.push(elem('li', {}, [
                elem('a', {href: link.url}, link.title+': ' + link.number)
            ]));
        });

        return elem('div', {class: 'fs_quick_links_wrapper'}, [
            elem('h4', {}, trans('Quick Summary')),
            elem('ul', {class: 'fs_quick_links'}, linkDoms)
        ]);
    }
};

FsGlobalSummary.init();
