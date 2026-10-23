document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const path = window.location.pathname.toLowerCase();
    const phpPage = /\/php\/[^/]+$/.test(path);
    const rootPrefix = phpPage ? '../' : '';
    const phpPrefix = phpPage ? '' : 'php/';
    const role = body.dataset.portalRole;
    const sidebar = document.querySelector('.sidebar') || document.createElement('aside');
    const isNewSidebar = !sidebar.isConnected;

    sidebar.classList.add('sidebar');
    if (isNewSidebar) {
        sidebar.classList.add('portal-common-sidebar');
        body.classList.add('portal-has-common-nav');
        body.insertBefore(sidebar, body.firstChild);
    }

    let logo = sidebar.querySelector('.logo');
    if (!logo) {
        logo = document.createElement('a');
        logo.className = 'logo';
        sidebar.prepend(logo);
    }
    logo.href = `${rootPrefix}index.html`;
    logo.textContent = 'Student Portal';

    let navigation = sidebar.querySelector('.main-nav');
    if (!navigation) {
        navigation = document.createElement('nav');
        navigation.className = 'main-nav';
        sidebar.append(navigation);
    }
    navigation.setAttribute('aria-label', 'Main navigation');

    const pages = [
        ['index.html', 'Home', 'fa-solid fa-house'],
        ['profile.html', 'Profile', 'fa-solid fa-user'],
        ['course.html', 'Courses', 'fa-solid fa-book-open'],
        ['attendance.html', 'Attendance', 'fa-solid fa-chart-pie'],
        ['timetable.html', 'Timetable', 'fa-solid fa-calendar-days'],
        ['assignments.html', 'Assignments', 'fa-solid fa-clipboard-list'],
        ['result.html', 'Results', 'fa-solid fa-graduation-cap'],
        ['notice.html', 'Notes', 'fa-solid fa-bell'],
        ['help.html', 'Help', 'fa-solid fa-circle-question'],
        ['php/contact.php', 'Contact', 'fa-solid fa-envelope'],
        ['json-explorer.html', 'Data View', 'fa-solid fa-database']
    ];
    const links = role === 'student' || role === 'admin'
        ? pages.map(([href, label, icon]) => [`${rootPrefix}${href}`, label, icon])
        : [
            [`${phpPrefix}login.php`, 'Log in', 'fa-solid fa-right-to-bracket'],
            [`${phpPrefix}registration.php`, 'Create account', 'fa-solid fa-user-plus']
        ];

    if (role === 'student') {
        links.splice(1, 0, [`${phpPrefix}dashboard.php`, 'Dashboard', 'fa-solid fa-gauge-high']);
    } else if (role === 'admin') {
        links.splice(1, 0,
            [`${phpPrefix}admin-dashboard.php`, 'Admin Dashboard', 'fa-solid fa-gauge-high'],
            [`${phpPrefix}manage-records.php`, 'Manage Records', 'fa-solid fa-users-gear'],
            [`${phpPrefix}database.php`, 'Database', 'fa-solid fa-database']
        );
    }

    navigation.replaceChildren(...links.map(([href, label, iconClass]) => {
        const link = document.createElement('a');
        link.href = href;
        link.append(createIcon(iconClass), document.createTextNode(label));
        if (path.endsWith(`/${href.replace(/^\.\.\//, '')}`)) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        }
        return link;
    }));

    const existingLogout = sidebar.querySelector('form[action$="logout.php"]');
    if (role && body.dataset.logoutCsrf && !existingLogout) {
        const logoutForm = document.createElement('form');
        logoutForm.className = 'portal-nav-logout';
        logoutForm.method = 'post';
        logoutForm.action = `${phpPrefix}logout.php`;
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = 'csrf_token';
        token.value = body.dataset.logoutCsrf;
        const button = document.createElement('button');
        button.type = 'submit';
        button.append(createIcon('fa-solid fa-right-from-bracket'), document.createTextNode('Log out'));
        logoutForm.append(token, button);
        sidebar.append(logoutForm);
    }

    function createIcon(iconClass) {
        const icon = document.createElement('i');
        icon.className = iconClass;
        icon.setAttribute('aria-hidden', 'true');
        const container = document.createElement('span');
        container.className = 'nav-icon';
        container.append(icon);
        return container;
    }
});
