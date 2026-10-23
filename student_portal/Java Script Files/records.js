document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const tabs = [...document.querySelectorAll('.record-tab')];
    const searchInput = document.querySelector('#record-search');
    const filterSelect = document.querySelector('#record-filter');
    const status = document.querySelector('#record-status');
    const tableHead = document.querySelector('#records-head');
    const tableBody = document.querySelector('#records-body');
    const countLabel = document.querySelector('#record-count');
    const pageLabel = document.querySelector('#record-page');
    const previousButton = document.querySelector('#previous-page');
    const nextButton = document.querySelector('#next-page');
    const addButton = document.querySelector('#add-record-button');
    const studentDialog = document.querySelector('#student-dialog');
    const eventDialog = document.querySelector('#event-dialog');
    const forms = {
        students: document.querySelector('#student-form'),
        events: document.querySelector('#event-form')
    };
    let collection = 'students';
    let page = 1;
    let pages = 1;
    let searchTimer;
    let requestVersion = 0;

    const configs = {
        students: {
            plural: 'students',
            singular: 'student',
            filterLabel: 'All departments',
            columns: ['Name', 'Email', 'Program', 'Department', 'Year', 'Location', 'Actions'],
            values: record => [
                record.name,
                record.email,
                record.program,
                record.department,
                `Year ${record.year}`,
                `${record.city}, ${record.state}, ${record.country}`
            ]
        },
        events: {
            plural: 'events',
            singular: 'event',
            filterLabel: 'All categories',
            columns: ['Poster', 'Title', 'Category', 'Date', 'Location', 'Description', 'Actions'],
            values: record => [
                record.title,
                record.category,
                record.event_date,
                record.location,
                record.description
            ]
        }
    };

    const setStatus = (message = '', isError = false) => {
        status.textContent = message;
        status.classList.toggle('error', isError);
    };

    const createCell = value => {
        const cell = document.createElement('td');
        cell.textContent = value == null ? '' : String(value);
        return cell;
    };

    const makeActionButton = (label, className, handler) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.textContent = label;
        button.addEventListener('click', handler);
        return button;
    };

    const editRecord = record => {
        const form = forms[collection];
        const dialog = collection === 'students' ? studentDialog : eventDialog;
        form.reset();
        form.elements.id.value = record.id;
        [...form.elements].forEach(field => {
            if (field.name && field.name !== 'id' && field.type !== 'file' && record[field.name] !== undefined) {
                field.value = record[field.name];
            }
        });
        dialog.querySelector('h2').textContent = `Edit ${configs[collection].singular}`;
        form.querySelector('.record-form-feedback').textContent = '';
        form.querySelector('.record-form-feedback').classList.remove('success');
        dialog.showModal();
    };

    const deleteRecord = async record => {
        const name = record.name || record.title;
        if (!window.confirm(`Delete "${name}"? This cannot be undone.`)) return;
        setStatus(`Deleting ${configs[collection].singular}...`);
        try {
            const response = await fetch('mysql-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ collection, operation: 'delete', id: record.id })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'The record could not be deleted.');
            setStatus(result.message);
            await loadRecords();
        } catch (error) {
            setStatus(error.message || 'The record could not be deleted. Try again.', true);
        }
    };

    const renderRecords = records => {
        const config = configs[collection];
        const headerRow = document.createElement('tr');
        config.columns.forEach(label => {
            const heading = document.createElement('th');
            heading.scope = 'col';
            heading.textContent = label;
            headerRow.append(heading);
        });
        tableHead.replaceChildren(headerRow);
        tableBody.replaceChildren();

        if (!records.length) {
            const row = document.createElement('tr');
            const cell = createCell(`No ${config.plural} match your search.`);
            cell.colSpan = config.columns.length;
            cell.className = 'empty-state';
            row.append(cell);
            tableBody.append(row);
            return;
        }

        records.forEach(record => {
            const row = document.createElement('tr');
            if (collection === 'events') {
                const posterCell = document.createElement('td');
                if (record.poster_url && /^\\.\\.\\/uploads\\/events\\/[a-f0-9]{32}\\.(?:jpg|png|webp)$/.test(record.poster_url)) {
                    const image = document.createElement('img');
                    image.className = 'record-poster';
                    image.src = record.poster_url;
                    image.alt = `Poster for ${record.title}`;
                    posterCell.append(image);
                } else {
                    posterCell.textContent = '—';
                }
                row.append(posterCell);
            }
            config.values(record).forEach(value => row.append(createCell(value)));
            const actions = document.createElement('td');
            actions.className = 'record-actions';
            actions.append(
                makeActionButton('Edit', 'edit-record', () => editRecord(record)),
                makeActionButton('Delete', 'delete-record', () => deleteRecord(record))
            );
            row.append(actions);
            tableBody.append(row);
        });
    };

    const configureFilters = values => {
        const previous = filterSelect.value;
        const config = configs[collection];
        filterSelect.replaceChildren(new Option(config.filterLabel, ''));
        values.forEach(value => filterSelect.add(new Option(value, value)));
        filterSelect.value = values.includes(previous) ? previous : '';
        filterSelect.setAttribute('aria-label', collection === 'students' ? 'Filter by department' : 'Filter by category');
    };

    const loadRecords = async () => {
        const currentRequest = ++requestVersion;
        const params = new URLSearchParams({
            collection,
            q: searchInput.value.trim(),
            filter: filterSelect.value,
            page: String(page)
        });
        setStatus('Loading records...');
        try {
            const response = await fetch(`mysql-api.php?${params}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const result = await response.json();
            if (currentRequest !== requestVersion) return;
            if (!response.ok) throw new Error(result.error || 'Records could not be loaded.');
            pages = result.pages;
            page = result.page;
            configureFilters(result.filters);
            renderRecords(result.records);
            countLabel.textContent = `${result.total} ${result.total === 1 ? 'record' : 'records'}`;
            pageLabel.textContent = `Page ${page} of ${pages}`;
            previousButton.disabled = page <= 1;
            nextButton.disabled = page >= pages;
            setStatus();
        } catch (error) {
            if (currentRequest !== requestVersion) return;
            tableHead.replaceChildren();
            tableBody.replaceChildren();
            setStatus(error.message || 'Records could not be loaded. Check the MySQL setup and try again.', true);
        }
    };

    const openActiveDialog = () => {
        const dialog = collection === 'students' ? studentDialog : eventDialog;
        const form = forms[collection];
        form.reset();
        form.elements.id.value = '';
        dialog.querySelector('h2').textContent = `Add ${configs[collection].singular}`;
        form.querySelector('.record-form-feedback').textContent = '';
        form.querySelector('.record-form-feedback').classList.remove('success');
        dialog.showModal();
        form.querySelector('input:not([type="hidden"]):not([type="file"])').focus();
    };

    const submitForm = async (event, currentCollection) => {
        event.preventDefault();
        const form = event.currentTarget;
        const submitButton = form.querySelector('[type="submit"]');
        const feedback = form.querySelector('.record-form-feedback');
        const id = form.elements.id.value;
        const formData = new FormData(form);
        formData.delete('id');
        formData.append('collection', currentCollection);
        formData.append('operation', id ? 'update' : 'create');
        if (id) formData.append('id', id);

        const poster = form.elements.poster;
        let body = formData;
        const headers = { 'X-CSRF-Token': csrfToken, Accept: 'application/json' };
        if (!poster || poster.files.length === 0) {
            const payload = Object.fromEntries(formData.entries());
            if (payload.poster instanceof File) delete payload.poster;
            body = JSON.stringify(payload);
            headers['Content-Type'] = 'application/json';
        }

        submitButton.disabled = true;
        feedback.classList.remove('success');
        feedback.textContent = 'Saving...';
        try {
            const response = await fetch('mysql-api.php', { method: 'POST', headers, body });
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || 'The record could not be saved.');
            feedback.classList.add('success');
            feedback.textContent = result.message;
            setStatus(result.message);
            window.setTimeout(() => {
                (currentCollection === 'students' ? studentDialog : eventDialog).close();
                page = 1;
                loadRecords();
            }, 300);
        } catch (error) {
            feedback.textContent = error.message || 'The record could not be saved. Try again.';
        } finally {
            submitButton.disabled = false;
        }
    };

    tabs.forEach(tab => tab.addEventListener('click', () => {
        collection = tab.dataset.collection;
        tabs.forEach(item => {
            const active = item === tab;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', String(active));
        });
        searchInput.value = '';
        filterSelect.value = '';
        page = 1;
        loadRecords();
    }));
    addButton.addEventListener('click', openActiveDialog);
    document.querySelectorAll('[data-dialog-close]').forEach(button => {
        button.addEventListener('click', () => button.closest('dialog').close());
    });
    forms.students.addEventListener('submit', event => submitForm(event, 'students'));
    forms.events.addEventListener('submit', event => submitForm(event, 'events'));
    searchInput.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => { page = 1; loadRecords(); }, 250);
    });
    filterSelect.addEventListener('change', () => { page = 1; loadRecords(); });
    previousButton.addEventListener('click', () => { page -= 1; loadRecords(); });
    nextButton.addEventListener('click', () => { page += 1; loadRecords(); });
    loadRecords();
});
