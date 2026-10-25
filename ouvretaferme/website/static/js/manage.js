class WebsiteManage {

    static changeUrlAuto(fieldAuto) {

        const fieldUrl = fieldAuto.firstParent('.webpage-write-url').qs('[name="url"]');

        if(fieldAuto.checked) {
            fieldUrl.classList.add('disabled');
            this.updateUrlAuto();
        } else {
            fieldUrl.classList.remove('disabled');
        }

    }

    static changeAddress(field) {

        const warning = field.firstParent('[data-wrapper]').qs('.website-write-address-warning');

        (field.value !== field.defaultValue) ?
            warning.classList.remove('hide') :
            warning.classList.add('hide');

    }

    static updateUrlAuto(value) {

        const fieldTitle = qs('#webpage-create [name="title"]');
        const fieldUrl = qs('#webpage-create [name="url"]');

        if(fieldUrl.classList.contains('disabled') === false) {
            return;
        }

        fieldUrl.value = fieldTitle.value.toFqn();

    }
    
    static initMenuEditor() {

        const editor = qs('#website-menu-editor');

        if(editor === null || editor.dataset.ready === '1') {
            return;
        }

        editor.dataset.ready = '1';

        WebsiteManage.refreshMenuLevels();

        editor.qsa('.website-menu-editor-list').forEach(list => {

            const isRoot = list.classList.contains('website-menu-editor-children') === false;

            new Sortable(list, {
                group: 'website-menu',
                handle: isRoot ? '.website-menu-editor-handle-root' : '.website-menu-editor-handle-child',
                draggable: '.website-menu-editor-item',
                ghostClass: 'website-menu-editor-item-ghost',
                chosenClass: 'website-menu-editor-item-chosen',
                forceFallback: true,
                fallbackOnBody: true,
                emptyInsertThreshold: 20,

                onStart: e => {

                    const editor = qs('#website-menu-editor');
                    const hasChildren = e.item.qs('.website-menu-editor-children')?.children.length > 0;

                    const entry = e.item.qs('.website-menu-editor-entry');

                    editor.style.setProperty('--website-menu-editor-drop-height', entry.getBoundingClientRect().height + 'px');

                    editor.classList.add('website-menu-editor-dragging');
                    editor.classList.toggle('website-menu-editor-dragging-parent', hasChildren);

                },
                onMove: e => {

                    const isChildZone = e.to.classList.contains('website-menu-editor-children');
                    const hasChildren = e.dragged.qs('.website-menu-editor-children')?.children.length > 0;

                    return (isChildZone && hasChildren) === false;

                },
                onEnd: () => {
                    qs('#website-menu-editor').style.removeProperty('--website-menu-editor-drop-height');
                    qs('#website-menu-editor').classList.remove('website-menu-editor-dragging', 'website-menu-editor-dragging-parent');
                    WebsiteManage.refreshMenuLevels();
                    WebsiteManage.saveMenuOrder();
                }
            });

        });

    }

    static refreshMenuLevels() {

        qs('#website-menu-editor').qsa('.website-menu-editor-item').forEach(item => {

            const handle = item.querySelector(':scope > .website-menu-editor-entry .website-menu-editor-handle');

            if(handle === null) {
                return;
            }

            const isChild = item.parentElement.classList.contains('website-menu-editor-children');

            handle.classList.toggle('website-menu-editor-handle-root', isChild === false);
            handle.classList.toggle('website-menu-editor-handle-child', isChild);

        });

    }

    static saveMenuOrder() {

        const editor = qs('#website-menu-editor');
        const positions = [];

        editor.qsa('.website-menu-editor-item').forEach(item => {

            const list = item.parentElement;

            positions.push({
                id: parseInt(item.dataset.id),
                parent: list.dataset.parent ? parseInt(list.dataset.parent) : null
            });

        });

        new Ajax.Query()
            .url('/website/menu:doUpdatePositions')
            .body({
                id: editor.dataset.website,
                positions: JSON.stringify(positions)
            })
            .fetch();

    }

}

document.delegateEventListener('input', '#webpage-create [name="title"]', () => {

    WebsiteManage.updateUrlAuto();

});

document.addEventListener('navigation.wakeup', () => WebsiteManage.initMenuEditor());
