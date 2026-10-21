(() => {
    'use strict';
    const form = document.getElementById('cms-form');
    if (!form) return;
    const status = document.getElementById('cms-save-status');
    let dirty = form.dataset.hasError === '1';
    const markDirty = () => {
        dirty = true;
        status.textContent = 'Unsaved changes';
    };
    form.addEventListener('input', markDirty);
    form.addEventListener('submit', (event) => {
        if (event.submitter?.value === 'discard' && !window.confirm('Discard draft changes in this section and return to the published text?')) {
            event.preventDefault();
            return;
        }
        dirty = false;
        status.textContent = 'Saving…';
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    form.querySelectorAll('.cms-original').forEach((button) => {
        button.addEventListener('click', () => {
            const field = document.getElementById(button.dataset.field);
            field.value = field.dataset.default;
            field.focus();
            markDirty();
        });
    });
    const search = document.getElementById('cms-search');
    search.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        form.querySelectorAll('.cms-field').forEach((field) => {
            const value = field.querySelector('textarea, input').value;
            field.hidden = !(field.dataset.search + ' ' + value.toLocaleLowerCase()).includes(query);
            if (!field.hidden) visible++;
        });
        document.getElementById('cms-no-results').hidden = visible > 0;
    });
    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            const button = form.querySelector('button[value="draft"]');
            if (!button.disabled) {
                event.preventDefault();
                form.requestSubmit(button);
            }
        }
    });
})();
