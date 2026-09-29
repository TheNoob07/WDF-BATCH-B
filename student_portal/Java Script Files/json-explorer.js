document.addEventListener('DOMContentLoaded', () => {
    const collections = {
        events: { file: 'data/events.json', cache: 'student-portal-events', filterLabel: 'Event type', filterKey: 'category', titleKey: 'title' },
        students: { file: 'data/students.json', cache: 'student-portal-students', filterLabel: 'Department', filterKey: 'department', titleKey: 'name' },
        faqs: { file: 'data/faqs.json', cache: 'student-portal-faqs', filterLabel: 'Topic', filterKey: 'category', titleKey: 'question' }
    };
    const pageSize = 6;
    const tabs = [...document.querySelectorAll('.collection-tab')];
    const searchInput = document.querySelector('#search-input');
    const filterSelect = document.querySelector('#filter-select');
    const sortSelect = document.querySelector('#sort-select');
    const locationFilters = document.querySelector('#location-filters');
    const countrySelect = document.querySelector('#country-select');
    const stateSelect = document.querySelector('#state-select');
    const citySelect = document.querySelector('#city-select');
    const recordList = document.querySelector('#record-list');
    const dataStatus = document.querySelector('#data-status');
    const resultCount = document.querySelector('#result-count');
    const sourceLabel = document.querySelector('#data-source');
    const previousButton = document.querySelector('#previous-page');
    const nextButton = document.querySelector('#next-page');
    const pageIndicator = document.querySelector('#page-indicator');
    let activeCollection = 'events';
    let records = [];
    let currentPage = 1;
    let loadVersion = 0;

    const setStatus = (message = '') => {
        dataStatus.textContent = message;
        dataStatus.classList.toggle('visible', Boolean(message));
    };

    const loadCollection = async (useCache = false) => {
        const config = collections[activeCollection];
        const requestVersion = ++loadVersion;
        setStatus('Loading JSON data...');
        sourceLabel.textContent = 'Connecting';
        sourceLabel.classList.remove('cached');
        recordList.replaceChildren();

        try {
            if (useCache) throw new Error('Using saved copy');
            const response = await fetch(config.file, { cache: 'no-store' });
            if (!response.ok) throw new Error(`Request failed (${response.status})`);
            const data = await response.json();
            if (!Array.isArray(data)) throw new Error('The JSON file must contain an array of records.');
            if (requestVersion !== loadVersion) return;
            records = data;
            localStorage.setItem(config.cache, JSON.stringify(data));
            sourceLabel.textContent = 'Fetched just now';
            setStatus();
            configureFilter();
            render();
        } catch (error) {
            if (requestVersion !== loadVersion) return;
            const savedData = localStorage.getItem(config.cache);
            if (savedData) {
                try {
                    records = JSON.parse(savedData);
                    if (!Array.isArray(records)) throw new Error('Saved data is invalid.');
                    sourceLabel.textContent = 'Offline copy';
                    sourceLabel.classList.add('cached');
                    setStatus('The JSON file could not be reached. Showing the last saved collection.');
                    configureFilter();
                    render();
                    return;
                } catch (cacheError) {
                    localStorage.removeItem(config.cache);
                }
            }
            records = [];
            sourceLabel.textContent = 'Unavailable';
            setStatus(`Could not load ${config.file}. Start a local web server and check that the JSON file is valid.`);
            render();
        }
    };

    const configureFilter = () => {
        const config = collections[activeCollection];
        const values = [...new Set(records.map(record => record[config.filterKey]).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        filterSelect.replaceChildren(new Option(`All ${config.filterLabel.toLowerCase()}s`, 'all'));
        values.forEach(value => filterSelect.add(new Option(value, value)));
        filterSelect.setAttribute('aria-label', `Filter by ${config.filterLabel.toLowerCase()}`);
        locationFilters.hidden = activeCollection !== 'students';
        if (activeCollection === 'students') configureCountries();
    };

    const replaceLocationOptions = (select, label, values) => {
        select.replaceChildren(new Option(`All ${label.toLowerCase()}`, 'all'));
        values.forEach(value => select.add(new Option(value, value)));
    };

    const configureCountries = () => {
        const countries = [...new Set(records.map(record => record.country).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        replaceLocationOptions(countrySelect, 'countries', countries);
        configureStates();
    };

    const configureStates = () => {
        const country = countrySelect.value;
        const states = [...new Set(records.filter(record => country === 'all' || record.country === country).map(record => record.state).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        replaceLocationOptions(stateSelect, 'states', states);
        configureCities();
    };

    const configureCities = () => {
        const country = countrySelect.value;
        const state = stateSelect.value;
        const cities = [...new Set(records.filter(record => (country === 'all' || record.country === country) && (state === 'all' || record.state === state)).map(record => record.city).filter(Boolean))].sort((a, b) => a.localeCompare(b));
        replaceLocationOptions(citySelect, 'cities', cities);
    };

    const makeElement = (tag, className, text) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };

    const addMeta = (parent, icon, text) => {
        if (!text) return;
        const item = makeElement('span');
        const symbol = makeElement('i');
        symbol.className = icon;
        symbol.setAttribute('aria-hidden', 'true');
        item.append(symbol, document.createTextNode(` ${text}`));
        parent.append(item);
    };

    const renderEvent = record => {
        const card = makeElement('article', 'record-card');
        const top = makeElement('div', 'record-topline');
        top.append(makeElement('h2', '', record.title), makeElement('span', 'category-pill', record.category));
        const description = makeElement('p', '', record.description);
        const meta = makeElement('div', 'record-meta');
        addMeta(meta, 'fa-regular fa-calendar', new Date(`${record.date}T00:00:00`).toLocaleDateString('en', { month: 'short', day: 'numeric', year: 'numeric' }));
        addMeta(meta, 'fa-solid fa-location-dot', record.location);
        card.append(top, description, meta);
        return card;
    };

    const renderStudent = record => {
        const card = makeElement('article', 'record-card');
        const top = makeElement('div', 'record-topline');
        const name = makeElement('h2', '', record.name);
        const initials = record.name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
        top.append(name, makeElement('span', 'student-initials', initials));
        const program = makeElement('p', '', record.program);
        const meta = makeElement('div', 'record-meta');
        addMeta(meta, 'fa-solid fa-building-columns', record.department);
        addMeta(meta, 'fa-solid fa-graduation-cap', `Year ${record.year}`);
        addMeta(meta, 'fa-regular fa-envelope', record.email);
        card.append(top, program, meta);
        return card;
    };

    const renderFaq = record => {
        const card = makeElement('article', 'record-card');
        const top = makeElement('div', 'record-topline');
        top.append(makeElement('h2', '', record.question), makeElement('span', 'category-pill', record.category));
        const details = makeElement('details');
        details.append(makeElement('summary', '', 'Read answer'), makeElement('p', '', record.answer));
        card.append(top, details);
        return card;
    };

    const render = () => {
        const config = collections[activeCollection];
        const query = searchInput.value.trim().toLocaleLowerCase();
        const selectedFilter = filterSelect.value || 'all';
        const country = countrySelect.value || 'all';
        const state = stateSelect.value || 'all';
        const city = citySelect.value || 'all';
        const direction = sortSelect.value === 'title-desc' ? -1 : 1;
        const matching = records.filter(record => {
            const matchesFilter = selectedFilter === 'all' || record[config.filterKey] === selectedFilter;
            const matchesLocation = activeCollection !== 'students' || ((country === 'all' || record.country === country) && (state === 'all' || record.state === state) && (city === 'all' || record.city === city));
            const matchesSearch = !query || Object.values(record).some(value => String(value).toLocaleLowerCase().includes(query));
            return matchesFilter && matchesLocation && matchesSearch;
        }).sort((first, second) => String(first[config.titleKey] || '').localeCompare(String(second[config.titleKey] || ''), undefined, { sensitivity: 'base' }) * direction);

        const totalPages = Math.max(1, Math.ceil(matching.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const visible = matching.slice((currentPage - 1) * pageSize, currentPage * pageSize);
        const renderers = { events: renderEvent, students: renderStudent, faqs: renderFaq };
        recordList.replaceChildren();
        if (visible.length) visible.forEach(record => recordList.append(renderers[activeCollection](record)));
        else recordList.append(makeElement('p', 'empty-state', 'No records match your search and filters.'));

        resultCount.textContent = `${matching.length} ${matching.length === 1 ? 'record' : 'records'}`;
        pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`;
        previousButton.disabled = currentPage <= 1;
        nextButton.disabled = currentPage >= totalPages;
    };

    tabs.forEach(tab => tab.addEventListener('click', () => {
        activeCollection = tab.dataset.collection;
        tabs.forEach(item => {
            const isActive = item === tab;
            item.classList.toggle('active', isActive);
            item.setAttribute('aria-selected', String(isActive));
        });
        searchInput.value = '';
        currentPage = 1;
        loadCollection();
    }));
    searchInput.addEventListener('input', () => { currentPage = 1; render(); });
    filterSelect.addEventListener('change', () => { currentPage = 1; render(); });
    sortSelect.addEventListener('change', () => { currentPage = 1; render(); });
    countrySelect.addEventListener('change', () => { configureStates(); currentPage = 1; render(); });
    stateSelect.addEventListener('change', () => { configureCities(); currentPage = 1; render(); });
    citySelect.addEventListener('change', () => { currentPage = 1; render(); });
    previousButton.addEventListener('click', () => { currentPage -= 1; render(); });
    nextButton.addEventListener('click', () => { currentPage += 1; render(); });
    document.querySelector('#refresh-button').addEventListener('click', () => loadCollection());

    loadCollection();
});