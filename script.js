// Farsi Comments: فایل اسکریپت اصلی برای مدیریت کلاینت ساید

document.addEventListener('DOMContentLoaded', () => {
    // --- State Management ---
    const appState = {
        currentUser: null,
        currentPage: null,
        dataCache: {},
    };

    // --- Element Selectors ---
    const mainContent = document.getElementById('main-content');
    const loginContainer = document.getElementById('login-container');
    const dashboardContainer = document.getElementById('dashboard-container');
    const pageContent = document.getElementById('page-content');
    const themeToggleBtn = document.getElementById('theme-toggle-btn');
    const logoutBtn = document.getElementById('logout-btn');
    const loginForm = document.getElementById('login-form');

    // --- API Helper ---
    async function fetchAPI(endpoint, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        };
        const config = { ...defaultOptions, ...options };
        if (config.body) {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(endpoint, config);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            alert(`خطا در ارتباط با سرور: ${error.message}`);
            return null;
        }
    }

    // --- Template Loader ---
    async function loadView(viewName, callback) {
        try {
            const response = await fetch(`views/${viewName}.html`);
            if (!response.ok) {
                throw new Error(`View not found: ${viewName}`);
            }
            const html = await response.text();
            pageContent.innerHTML = html;
            if (callback) {
                callback();
            }
        } catch (error) {
            console.error('View Loading Error:', error);
            pageContent.innerHTML = `<h2>خطا در بارگذاری صفحه</h2><p>${error.message}</p>`;
        }
    }

    // --- Theme (Night Mode) ---
    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggleBtn.innerHTML = '<i class="fa-solid fa-sun"></i>';
        } else {
            document.body.classList.remove('dark-mode');
            themeToggleBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
        }
    }

    function toggleTheme() {
        const currentTheme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
        localStorage.setItem('ftth_theme', currentTheme);
        applyTheme(currentTheme);
    }

    themeToggleBtn.addEventListener('click', toggleTheme);

    // Load saved theme
    const savedTheme = localStorage.getItem('ftth_theme') || 'light';
    applyTheme(savedTheme);

    // --- Authentication ---
    async function handleLogin(e) {
        e.preventDefault();
        const username = loginForm.username.value;
        const password = loginForm.password.value;
        const loginError = document.getElementById('login-error');
        loginError.textContent = '';

        const result = await fetchAPI('login.php', {
            method: 'POST',
            body: { username, password },
        });

        if (result && result.status === 'success') {
            appState.currentUser = result.data; // This now contains username and role
            showDashboard();
        } else {
            loginError.textContent = result ? result.message : 'خطای ناشناخته در ورود.';
        }
    }

    async function handleLogout() {
        await fetchAPI('logout.php');
        appState.currentUser = null;
        showLogin();
    }

    function showDashboard() {
        loginContainer.style.display = 'none';
        dashboardContainer.style.display = 'block';
        logoutBtn.style.display = 'inline-block';

        // Filter tiles based on user role
        const userRole = appState.currentUser.role;
        document.querySelectorAll('#tile-menu .tile').forEach(tile => {
            const requiredRoles = tile.dataset.role;
            if (!requiredRoles) {
                // No role requirement, show to all
                tile.style.display = 'block';
            } else {
                // Show if user's role is in the list of required roles
                if (requiredRoles.trim().split(' ').includes(userRole)) {
                    tile.style.display = 'block';
                } else {
                    tile.style.display = 'none';
                }
            }
        });

        navigateTo('dashboard'); // Navigate to the main tile menu view
    }

    function showLogin() {
        loginContainer.style.display = 'block';
        dashboardContainer.style.display = 'none';
        logoutBtn.style.display = 'none';
        pageContent.innerHTML = ''; // Clear page content on logout
    }

    loginForm.addEventListener('submit', handleLogin);
    logoutBtn.addEventListener('click', handleLogout);

    // --- Navigation ---
    function navigateTo(page, params = {}) {
        appState.currentPage = page;
        pageContent.innerHTML = '<h2><i class="fa-solid fa-spinner fa-spin"></i> در حال بارگذاری...</h2>';
        const tileMenu = document.getElementById('tile-menu');

        if (page === 'dashboard') {
            tileMenu.style.display = 'grid';
        } else {
            tileMenu.style.display = 'none';
        }

        switch (page) {
            case 'dashboard':
                pageContent.innerHTML = ''; // Clear for tile menu
                break;
            case 'telecom-centers-management':
                renderTelecomCenters();
                break;
            case 'fats-management':
                renderFatsManagement();
                break;
            case 'reports-management':
                renderReportsManagement();
                break;
            case 'subscribers-management':
                renderSubscribersManagement();
                break;
            case 'subscriptions-management':
                renderSubscriptionsManagement();
                break;
            case 'companies-management':
                renderCompaniesManagement();
                break;
            case 'users-management':
                renderUsersManagement();
                break;
            case 'tickets-management':
                renderTicketsManagement();
                break;
            case 'installer-dashboard':
                renderInstallerDashboard();
                break;
            case 'support-dashboard':
                renderSupportDashboard();
                break;
            default:
                tileMenu.style.display = 'grid'; // Show menu again if page not found
                pageContent.innerHTML = '<h2>صفحه مورد نظر یافت نشد</h2>';
                break;
        }
    }

    document.getElementById('tile-menu').addEventListener('click', (e) => {
        const tile = e.target.closest('.tile');
        if (tile) {
            const targetPage = tile.dataset.target;
            navigateTo(targetPage);
        }
    });

    // --- Content Rendering (Example for Telecom Centers) ---
    async function renderTelecomCenters() {
        const result = await fetchAPI('api/telecom_centers.php');
        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری مراکز مخابراتی</h2>';
            return;
        }

        const centers = result.data;
        let tableRows = centers.map(center => `
            <tr>
                <td>${center.id}</td>
                <td>${center.name}</td>
                <td>${new Date(center.created_at).toLocaleDateString('fa-IR')}</td>
                <td>
                    <button class="btn-edit" data-id="${center.id}">ویرایش</button>
                    <button class="btn-delete danger" data-id="${center.id}">حذف</button>
                </td>
            </tr>
        `).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>مدیریت مراکز مخابراتی</h2>
            </div>
            <button id="add-new-center-btn">افزودن مرکز جدید</button>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                        <th>شناسه</th>
                        <th>نام مرکز</th>
                        <th>تاریخ ایجاد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        `;

        // Add event listeners for new buttons
        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        document.getElementById('add-new-center-btn').addEventListener('click', () => renderAddEditCenterForm());
        pageContent.querySelectorAll('.btn-edit').forEach(btn => btn.addEventListener('click', (e) => renderAddEditCenterForm(e.target.dataset.id)));
        pageContent.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', (e) => deleteCenter(e.target.dataset.id)));
    }

    // --- Form Handling & CRUD (Example for Telecom Centers) ---
    async function renderAddEditCenterForm(id = null) {
        let center = { id: null, name: '' };
        const title = id ? 'ویرایش مرکز مخابراتی' : 'افزودن مرکز مخابراتی';

        if (id) {
            const result = await fetchAPI(`api/telecom_centers.php?id=${id}`);
            if (result && result.status === 'success') {
                center = result.data;
            }
        }

        pageContent.innerHTML = `
            <h2>${title}</h2>
            <form id="center-form">
                <input type="hidden" name="id" value="${center.id || ''}">
                <div class="form-group">
                    <label for="center-name">نام مرکز</label>
                    <input type="text" id="center-name" name="name" value="${center.name}" required>
                </div>
                <button type="submit">ذخیره</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;

        document.getElementById('center-form').addEventListener('submit', saveCenter);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('telecom-centers-management'));
    }

    async function saveCenter(e) {
        e.preventDefault();
        const form = e.target;
        const id = form.id.value;
        const name = form.name.value;

        const endpoint = id ? `api/telecom_centers.php` : 'api/telecom_centers.php';
        const method = id ? 'PUT' : 'POST';

        const result = await fetchAPI(endpoint, {
            method: method,
            body: { id, name }
        });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('telecom-centers-management');
        }
    }

    async function deleteCenter(id) {
        if (confirm('آیا از حذف این مرکز و تمام FAT های مرتبط با آن مطمئن هستید؟')) {
            const result = await fetchAPI(`api/telecom_centers.php?id=${id}`, { method: 'DELETE' });
             if (result && result.status === 'success') {
                alert(result.message);
                navigateTo('telecom-centers-management');
            }
        }
    }

    // --- Support Ticket Creation ---

    function renderCreateTicketForm(subscription_id) {
        pageContent.innerHTML = `
            <h2>ایجاد تیکت پشتیبانی برای اشتراک #${subscription_id}</h2>
            <form id="create-ticket-form">
                <input type="hidden" name="subscription_id" value="${subscription_id}">
                <div class="form-group">
                    <label for="ticket-title">عنوان تیکت</label>
                    <input type="text" id="ticket-title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="ticket-description">شرح مشکل</label>
                    <textarea id="ticket-description" name="description" rows="5" required></textarea>
                </div>
                <button type="submit">ایجاد تیکت</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;
        document.getElementById('create-ticket-form').addEventListener('submit', saveTicket);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('subscriptions-management'));
    }

    async function saveTicket(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await fetchAPI('api/tickets.php', {
            method: 'POST',
            body: data
        });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('tickets-management');
        }
    }

    // --- Support UI ---

    async function renderSupportDashboard() {
        pageContent.innerHTML = `<h2>داشبورد پشتیبانی</h2><p>در حال بارگذاری تیکت‌های شما...</p>`;
        const result = await fetchAPI('api/assignments.php'); // GET request will be filtered by role on backend

        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری لیست کارها</h2>';
            return;
        }

        const tickets = result.data;
        if (tickets.length === 0) {
            pageContent.innerHTML = '<h2>داشبورد پشتیبانی</h2><p>در حال حاضر هیچ تیکتی به شما ارجاع داده نشده است.</p>';
            return;
        }

        let tableRows = tickets.map(t => {
            const is_resolved = t.status === 'resolved';
            const report_button = !is_resolved
                ? `<button class="btn-report" data-id="${t.id}">ثبت گزارش و تغییر وضعیت</button>`
                : 'گزارش ثبت شده';
            return `
                <tr>
                    <td>${t.id}</td>
                    <td>${t.title}</td>
                    <td>${t.subscriber_name}</td>
                    <td><span class="status-${t.status}">${t.status}</span></td>
                    <td>${report_button}</td>
                </tr>
            `;
        }).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>تیکت‌های ارجاع شده به شما</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>شناسه تیکت</th>
                            <th>عنوان</th>
                            <th>نام مشترک</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>
            </div>
        `;

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        pageContent.querySelectorAll('.btn-report').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const ticketId = e.currentTarget.dataset.id;
                renderTicketUpdateForm(ticketId);
            });
        });
    }

    function renderTicketUpdateForm(ticket_id) {
        pageContent.innerHTML = `
            <h2>ثبت گزارش برای تیکت #${ticket_id}</h2>
            <form id="support-report-form">
                <input type="hidden" name="type" value="support">
                <input type="hidden" name="target_id" value="${ticket_id}">

                <div class="form-group">
                    <label for="ticket-status">تغییر وضعیت به:</label>
                    <select id="ticket-status" name="status" required>
                        <option value="resolved">حل شده</option>
                        <option value="needs_investigation">نیاز به بررسی بیشتر</option>
                        <option value="needs_recabling">نیاز به کابل‌کشی مجدد</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">گزارش اقدامات و توضیحات</label>
                    <textarea id="notes" name="notes" rows="5" required></textarea>
                </div>

                <div class="form-group">
                    <label>لوازم مصرفی (اختیاری - فرمت JSON)</label>
                    <textarea name="materials_used" rows="3" placeholder='[{"item": "patch cord", "quantity": 1}]'></textarea>
                </div>

                <button type="submit">ثبت گزارش</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;
        document.getElementById('support-report-form').addEventListener('submit', saveSupportReport);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('support-dashboard'));
    }

    async function saveSupportReport(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // Validate and parse JSON
        try {
            if (data.materials_used.trim()) {
                data.materials_used = JSON.parse(data.materials_used);
            } else {
                delete data.materials_used; // Remove if empty
            }
        } catch (error) {
            alert('فرمت JSON برای لوازم مصرفی نامعتبر است.');
            return;
        }

        const result = await fetchAPI('api/workflow_reports.php', { method: 'POST', body: data });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('support-dashboard');
        }
    }

    // --- Ticket Management (Company Admin) ---

    async function renderTicketsManagement() {
        const result = await fetchAPI('api/tickets.php');
        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری تیکت‌ها</h2>';
            return;
        }

        const tickets = result.data;
        let tableRows = tickets.map(ticket => {
            let actions = '';
            if (ticket.status === 'open') {
                actions = `<button class="btn-refer" data-id="${ticket.id}">ارجاع به پشتیبان</button>`;
            } else {
                actions = `ارجاع شده به ${ticket.assigned_to || 'N/A'}`;
            }

            return `
                <tr>
                    <td>${ticket.id}</td>
                    <td>${ticket.title}</td>
                    <td>${ticket.subscriber_name}</td>
                    <td><span class="status-${ticket.status}">${ticket.status}</span></td>
                    <td>${new Date(ticket.created_at).toLocaleDateString('fa-IR')}</td>
                    <td>${actions}</td>
                </tr>
            `;
        }).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>مدیریت تیکت‌های پشتیبانی</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>عنوان</th>
                            <th>مشترک</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>
            </div>
        `;

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        pageContent.querySelectorAll('.btn-refer').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const ticketId = e.currentTarget.dataset.id;
                renderReferTicketForm(ticketId);
            });
        });
    }

    async function renderReferTicketForm(ticketId) {
        pageContent.innerHTML = `<h2>ارجاع تیکت #${ticketId}</h2><p>در حال بارگذاری فرم...</p>`;

        const usersResult = await fetchAPI('api/users.php?role=support');
        if (!usersResult || usersResult.status !== 'success' || usersResult.data.length === 0) {
            pageContent.innerHTML = '<h2>خطا: هیچ کاربر پشتیبانی در شرکت شما یافت نشد.</h2><button type="button" id="cancel-btn">بازگشت</button>';
            document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('tickets-management'));
            return;
        }

        // Filter out the current user to prevent self-assignment
        const currentUserId = appState.currentUser.id;
        const filteredUsers = usersResult.data.filter(user => user.id != currentUserId);

        if (filteredUsers.length === 0) {
            pageContent.innerHTML = '<h2>خطا: هیچ کاربر پشتیبانی دیگری برای ارجاع یافت نشد.</h2><button type="button" id="cancel-btn">بازگشت</button>';
            document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('tickets-management'));
            return;
        }

        const supportUsersOptions = filteredUsers.map(u => `<option value="${u.id}">${u.username}</option>`).join('');

        pageContent.innerHTML = `
            <h2>ارجاع تیکت #${ticketId}</h2>
            <form id="refer-ticket-form">
                <input type="hidden" name="target_id" value="${ticketId}">
                <input type="hidden" name="type" value="support">
                <div class="form-group">
                    <label for="support-user-id">کاربر پشتیبان</label>
                    <select id="support-user-id" name="user_id" required>
                        <option value="">انتخاب کنید...</option>
                        ${supportUsersOptions}
                    </select>
                </div>
                <button type="submit">ارجاع</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;

        document.getElementById('refer-ticket-form').addEventListener('submit', assignTicket);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('tickets-management'));
    }

    async function assignTicket(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const result = await fetchAPI('api/assignments.php', {
            method: 'POST',
            body: data
        });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('tickets-management');
        }
    }

    // --- CRUD for FATs ---

    async function renderFatsManagement() {
        const result = await fetchAPI('api/fats.php');
        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری FAT ها</h2>';
            return;
        }

        const fats = result.data;
        let tableRows = fats.map(fat => `
            <tr>
                <td>${fat.fat_number}</td>
                <td>${fat.telecom_center_name}</td>
                <td>${fat.splitter_type}</td>
                <td>${fat.occupied_ports} / ${fat.splitter_type.split(':')[1]}</td>
                <td>${fat.address || 'ثبت نشده'}</td>
                <td>
                    <button class="btn-edit" data-id="${fat.id}">ویرایش</button>
                    <button class="btn-delete danger" data-id="${fat.id}">حذف</button>
                </td>
            </tr>
        `).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>مدیریت FAT ها</h2>
            </div>
            <button id="add-new-fat-btn">افزودن FAT جدید</button>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                        <th>شماره FAT</th>
                        <th>مرکز مخابراتی</th>
                        <th>نوع اسپلیتر</th>
                        <th>ظرفیت اشغالی</th>
                        <th>آدرس</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        `;

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        document.getElementById('add-new-fat-btn').addEventListener('click', () => renderAddEditFatForm());
        pageContent.querySelectorAll('.btn-edit').forEach(btn => btn.addEventListener('click', (e) => renderAddEditFatForm(e.target.dataset.id)));
        pageContent.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', (e) => deleteFat(e.target.dataset.id)));
    }

    async function renderAddEditFatForm(id = null) {
        const title = id ? 'ویرایش FAT' : 'افزودن FAT';
        pageContent.innerHTML = `<h2>${title}</h2><p>در حال بارگذاری فرم...</p>`;

        // Fetch telecom centers for the dropdown
        const centersResult = await fetchAPI('api/telecom_centers.php');
        if (!centersResult || centersResult.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا: مراکز مخابراتی یافت نشدند.</h2>';
            return;
        }
        const centersOptions = centersResult.data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

        let fat = { id: null, fat_number: '', telecom_center_id: '', latitude: 35.7219, longitude: 51.3347, address: '', splitter_type: '1:8' }; // Default to Tehran

        if (id) {
            const fatResult = await fetchAPI(`api/fats.php?id=${id}`);
            if (fatResult && fatResult.status === 'success') {
                fat = fatResult.data;
            }
        }

        pageContent.innerHTML = `
            <h2>${title}</h2>
            <form id="fat-form">
                <input type="hidden" name="id" value="${fat.id || ''}">
                <div class="form-group">
                    <label for="fat-number">شماره FAT</label>
                    <input type="text" id="fat-number" name="fat_number" value="${fat.fat_number}" required>
                </div>
                <div class="form-group">
                    <label for="telecom-center">مرکز مخابراتی</label>
                    <select id="telecom-center" name="telecom_center_id" required>
                        <option value="">انتخاب کنید...</option>
                        ${centersOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label for="splitter-type">نوع اسپلیتر</label>
                    <select id="splitter-type" name="splitter_type" required>
                        <option value="1:2">1:2</option>
                        <option value="1:4">1:4</option>
                        <option value="1:8">1:8</option>
                        <option value="1:16">1:16</option>
                        <option value="1:32">1:32</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>موقعیت مکانی</label>
                    <button type="button" id="use-gps-btn" style="margin-bottom: 10px;">
                        <i class="fa-solid fa-location-crosshairs"></i> استفاده از موقعیت مکانی من
                    </button>
                    <div id="map"></div>
                </div>
                 <div class="form-group">
                    <label for="latitude">عرض جغرافیایی (Latitude)</label>
                    <input type="text" id="latitude" name="latitude" value="${fat.latitude}" required>
                </div>
                 <div class="form-group">
                    <label for="longitude">طول جغرافیایی (Longitude)</label>
                    <input type="text" id="longitude" name="longitude" value="${fat.longitude}" required>
                </div>
                <div class="form-group">
                    <label for="address">آدرس</label>
                    <textarea id="address" name="address" rows="3">${fat.address}</textarea>
                </div>
                <button type="submit">ذخیره</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;

        // Set selected values
        if (fat.telecom_center_id) document.getElementById('telecom-center').value = fat.telecom_center_id;
        if (fat.splitter_type) document.getElementById('splitter-type').value = fat.splitter_type;

        initializeMap(fat.latitude, fat.longitude);

        document.getElementById('fat-form').addEventListener('submit', saveFat);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('fats-management'));
    }

    function initializeMap(lat, lng) {
        const map = L.map('map').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        let marker = L.marker([lat, lng], { draggable: true }).addTo(map);

        const updateFields = (latlng) => {
            document.getElementById('latitude').value = latlng.lat;
            document.getElementById('longitude').value = latlng.lng;
        };

        marker.on('dragend', function(event) {
            const position = marker.getLatLng();
            updateFields(position);
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateFields(e.latlng);
        });

        document.getElementById('use-gps-btn').addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('مرورگر شما از موقعیت‌یابی جغرافیایی پشتیبانی نمی‌کند.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    const latlng = { lat: latitude, lng: longitude };
                    map.setView(latlng, 16);
                    marker.setLatLng(latlng);
                    updateFields(latlng);
                },
                () => {
                    alert('امکان دریافت موقعیت مکانی شما وجود ندارد. لطفاً دسترسی لازم را به مرورگر بدهید.');
                }
            );
        });
    }

    async function saveFat(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const endpoint = data.id ? `api/fats.php` : 'api/fats.php';
        const method = data.id ? 'PUT' : 'POST';

        const result = await fetchAPI(endpoint, {
            method: method,
            body: data
        });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('fats-management');
        }
    }

    async function deleteFat(id) {
        if (confirm('آیا از حذف این FAT و تمام اشتراک‌های مرتبط با آن مطمئن هستید؟')) {
            const result = await fetchAPI(`api/fats.php?id=${id}`, { method: 'DELETE' });
             if (result && result.status === 'success') {
                alert(result.message);
                navigateTo('fats-management');
            }
        }
    }

    // --- Reporting ---

    async function renderReportsManagement() {
        pageContent.innerHTML = `<h2>گزارش‌گیری پیشرفته</h2><p>در حال بارگذاری فیلترها...</p>`;

        const fatsResult = await fetchAPI('api/fats.php');
        if (!fatsResult || fatsResult.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا: FAT ها یافت نشدند.</h2>';
            return;
        }
        const fatsOptions = fatsResult.data.map(f => `<option value="${f.id}">${f.fat_number}</option>`).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>گزارش‌گیری پیشرفته</h2>
            </div>
            <form id="report-filters-form">
                <div class="form-group">
                    <label for="filter-fat">فیلتر بر اساس FAT</label>
                    <select id="filter-fat" name="fat_id">
                        <option value="">همه FAT ها</option>
                        ${fatsOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter-status">فیلتر بر اساس وضعیت اشتراک</label>
                    <select id="filter-status" name="is_active">
                        <option value="">همه وضعیت‌ها</option>
                        <option value="1">فعال</option>
                        <option value="0">غیرفعال</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filter-start-date">از تاریخ</label>
                    <input type="text" id="filter-start-date" name="start_date" data-jdp>
                </div>
                <div class="form-group">
                    <label for="filter-end-date">تا تاریخ</label>
                    <input type="text" id="filter-end-date" name="end_date" data-jdp>
                </div>
                <div class="form-group">
                    <button type="button" id="generate-json-btn">مشاهده گزارش (JSON)</button>
                    <button type="button" id="generate-csv-btn">دانلود CSV</button>
                    <button type="button" id="generate-pdf-btn">دانلود PDF</button>
                </div>
            </form>
            <hr>
            <h3>نتیجه گزارش</h3>
            <div id="report-results-container">
                <p>برای مشاهده نتیجه، فیلترها را تنظیم کرده و روی دکمه "مشاهده گزارش" کلیک کنید.</p>
            </div>
        `;

        // Initialize Jalali Datepickers
        jalaliDatepicker.startWatch({
            selector: '[data-jdp]',
            time: false,
        });

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        document.getElementById('generate-json-btn').addEventListener('click', () => generateReport('json'));
        document.getElementById('generate-csv-btn').addEventListener('click', () => generateReport('csv'));
        document.getElementById('generate-pdf-btn').addEventListener('click', () => generateReport('pdf'));
    }

    async function generateReport(format) {
        const form = document.getElementById('report-filters-form');
        const params = new URLSearchParams();
        if (form.fat_id.value) params.append('fat_id', form.fat_id.value);
        if (form.is_active.value) params.append('is_active', form.is_active.value);
        if (form.start_date.value) params.append('start_date', form.start_date.value);
        if (form.end_date.value) params.append('end_date', form.end_date.value);
        params.append('format', format);

        const reportUrl = `api/report.php?${params.toString()}`;

        if (format === 'json') {
            const resultsContainer = document.getElementById('report-results-container');
            resultsContainer.innerHTML = '<p>در حال دریافت اطلاعات...</p>';
            const result = await fetchAPI(reportUrl);
            if (result && result.status === 'success') {
                if (result.data.length === 0) {
                     resultsContainer.innerHTML = '<p>هیچ نتیجه‌ای برای این فیلترها یافت نشد.</p>';
                     return;
                }
                const headers = Object.keys(result.data[0]);
                const tableHeaders = headers.map(key => `<th>${key.replace(/_/g, ' ')}</th>`).join('');

                const tableRows = result.data.map(row => {
                    const cells = headers.map(header => `<td>${row[header] !== null ? row[header] : ''}</td>`).join('');
                    return `<tr>${cells}</tr>`;
                }).join('');

                resultsContainer.innerHTML = `
                    <div class="table-container">
                        <table class="report-table">
                            <thead><tr>${tableHeaders}</tr></thead>
                            <tbody>${tableRows}</tbody>
                        </table>
                    </div>
                `;
            } else {
                 resultsContainer.innerHTML = '<p>خطا در دریافت گزارش.</p>';
            }
        } else {
            // For CSV and PDF, trigger a download by opening the URL
            window.open(reportUrl, '_blank');
        }
    }

    // --- CRUD for Subscribers ---

    async function renderSubscribersManagement() {
        const result = await fetchAPI('api/subscribers.php');
        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری مشترکین</h2>';
            return;
        }

        const subscribers = result.data;
        let tableRows = subscribers.map(s => `
            <tr>
                <td>${s.full_name}</td>
                <td>${s.mobile_number}</td>
                <td>${s.national_id || 'ثبت نشده'}</td>
                <td>
                    <button class="btn-edit" data-id="${s.id}">ویرایش</button>
                    <button class="btn-delete danger" data-id="${s.id}">حذف</button>
                </td>
            </tr>
        `).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>مدیریت مشترکین</h2>
            </div>
            <button id="add-new-subscriber-btn">افزودن مشترک جدید</button>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                        <th>نام کامل</th>
                        <th>شماره موبایل</th>
                        <th>کد ملی</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        `;

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        document.getElementById('add-new-subscriber-btn').addEventListener('click', () => renderAddEditSubscriberForm());
        pageContent.querySelectorAll('.btn-edit').forEach(btn => btn.addEventListener('click', (e) => renderAddEditSubscriberForm(e.target.dataset.id)));
        pageContent.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', (e) => deleteSubscriber(e.target.dataset.id)));
    }

    async function renderAddEditSubscriberForm(id = null) {
        let subscriber = { id: null, full_name: '', mobile_number: '', national_id: '' };
        const title = id ? 'ویرایش مشترک' : 'افزودن مشترک';

        if (id) {
            const result = await fetchAPI(`api/subscribers.php?id=${id}`);
            if (result && result.status === 'success') {
                subscriber = result.data;
            }
        }

        pageContent.innerHTML = `
            <h2>${title}</h2>
            <form id="subscriber-form">
                <input type="hidden" name="id" value="${subscriber.id || ''}">
                <div class="form-group">
                    <label for="full-name">نام کامل</label>
                    <input type="text" id="full-name" name="full_name" value="${subscriber.full_name}" required>
                </div>
                <div class="form-group">
                    <label for="mobile-number">شماره موبایل</label>
                    <input type="text" id="mobile-number" name="mobile_number" value="${subscriber.mobile_number}" required pattern="^09[0-9]{9}$" title="شماره موبایل باید با 09 شروع شده و 11 رقم باشد">
                </div>
                <div class="form-group">
                    <label for="national-id">کد ملی (اختیاری)</label>
                    <input type="text" id="national-id" name="national_id" value="${subscriber.national_id || ''}" pattern="^[0-9]{10}$" title="کد ملی باید 10 رقم باشد">
                </div>
                <button type="submit">ذخیره</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;

        document.getElementById('subscriber-form').addEventListener('submit', saveSubscriber);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('subscribers-management'));
    }

    async function saveSubscriber(e) {
        e.preventDefault();
        const form = e.target;
        if (!form.checkValidity()) {
            alert('لطفاً اطلاعات فرم را به درستی وارد کنید.');
            return;
        }
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const endpoint = data.id ? `api/subscribers.php` : 'api/subscribers.php';
        const method = data.id ? 'PUT' : 'POST';

        const result = await fetchAPI(endpoint, {
            method: method,
            body: data
        });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('subscribers-management');
        }
    }

    async function deleteSubscriber(id) {
        if (confirm('آیا از حذف این مشترک و تمام اشتراک‌های مرتبط با آن مطمئن هستید؟')) {
            const result = await fetchAPI(`api/subscribers.php?id=${id}`, { method: 'DELETE' });
             if (result && result.status === 'success') {
                alert(result.message);
                navigateTo('subscribers-management');
            }
        }
    }

    // --- CRUD for Subscriptions ---

    async function renderSubscriptionsManagement() {
        const result = await fetchAPI('api/subscriptions.php');
        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری اشتراک‌ها</h2>';
            return;
        }

        const subscriptions = result.data;
        let tableRows = subscriptions.map(sub => `
            <tr>
                <td>${sub.subscriber_name}</td>
                <td>${sub.fat_number}</td>
                <td>${sub.port_number}</td>
                <td>${sub.virtual_subscriber_number}</td>
                <td>${sub.is_active ? '<span style="color:green;">فعال</span>' : '<span style="color:red;">غیرفعال</span>'}</td>
                <td>
                    <button class="btn-support" data-id="${sub.id}" title="ایجاد تیکت پشتیبانی"><i class="fa-solid fa-headset"></i></button>
                    <button class="btn-delete danger" data-id="${sub.id}" title="حذف اشتراک"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        `).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>مدیریت اشتراک‌ها</h2>
            </div>
            <button id="add-new-subscription-btn">افزودن اشتراک جدید</button>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                        <th>نام مشترک</th>
                        <th>شماره FAT</th>
                        <th>شماره پورت</th>
                        <th>شماره اشتراک مجازی</th>
                        <th>وضعیت</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        `;

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        document.getElementById('add-new-subscription-btn').addEventListener('click', () => renderAddEditSubscriptionForm());

        pageContent.querySelectorAll('.btn-support').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const subId = e.currentTarget.dataset.id;
                renderCreateTicketForm(subId);
            });
        });

        pageContent.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const subId = e.currentTarget.dataset.id;
                deleteSubscription(subId);
            });
        });
    }

    async function renderAddEditSubscriptionForm(id = null) {
        // Note: Editing subscriptions is complex (e.g., changing port). This form focuses on adding.
        const title = 'افزودن اشتراک جدید';
        pageContent.innerHTML = `<h2>${title}</h2><p>در حال بارگذاری فرم...</p>`;

        // Fetch subscribers and FATs in parallel
        const [subscribersResult, fatsResult] = await Promise.all([
            fetchAPI('api/subscribers.php'),
            fetchAPI('api/fats.php')
        ]);

        if (!subscribersResult || subscribersResult.status !== 'success' || !fatsResult || fatsResult.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا: مشترکین یا FAT ها یافت نشدند.</h2>';
            return;
        }

        const subscribersOptions = subscribersResult.data.map(s => `<option value="${s.id}">${s.full_name} (${s.mobile_number})</option>`).join('');
        const fatsOptions = fatsResult.data.map(f => `<option value="${f.id}" data-capacity="${f.splitter_type.split(':')[1]}">${f.fat_number}</option>`).join('');

        pageContent.innerHTML = `
            <h2>${title}</h2>
            <form id="subscription-form">
                <div class="form-group">
                    <label for="subscriber-id">مشترک</label>
                    <select id="subscriber-id" name="subscriber_id" required>
                        <option value="">انتخاب کنید...</option>
                        ${subscribersOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label for="fat-id">FAT</label>
                    <select id="fat-id" name="fat_id" required>
                        <option value="">انتخاب کنید...</option>
                        ${fatsOptions}
                    </select>
                    <small id="fat-capacity-info"></small>
                </div>
                <div class="form-group">
                    <label for="port-number">شماره پورت</label>
                    <input type="number" id="port-number" name="port_number" min="1" required>
                </div>
                <div class="form-group">
                    <label for="virtual-subscriber-number">شماره اشتراک مجازی</label>
                    <input type="text" id="virtual-subscriber-number" name="virtual_subscriber_number" required>
                </div>
                 <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" checked>
                        فعال باشد
                    </label>
                </div>
                <button type="submit">ذخیره</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;

        // Add event listener to show FAT capacity
        const fatSelect = document.getElementById('fat-id');
        fatSelect.addEventListener('change', async (e) => {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const capacity = selectedOption.dataset.capacity;
            const fatId = e.target.value;
            if (!fatId) {
                document.getElementById('fat-capacity-info').textContent = '';
                return;
            }
            const fatInfo = await fetchAPI(`api/fats.php?id=${fatId}`);
            const occupied = fatInfo.data.occupied_ports;
            document.getElementById('fat-capacity-info').textContent = `ظرفیت: ${occupied} / ${capacity}`;
        });

        document.getElementById('subscription-form').addEventListener('submit', saveSubscription);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('subscriptions-management'));
    }

    async function saveSubscription(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        data.is_active = form.is_active.checked;

        const result = await fetchAPI('api/subscriptions.php', {
            method: 'POST',
            body: data
        });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('subscriptions-management');
        }
    }

    async function deleteSubscription(id) {
        if (confirm('آیا از حذف این اشتراک مطمئن هستید؟')) {
            const result = await fetchAPI(`api/subscriptions.php?id=${id}`, { method: 'DELETE' });
             if (result && result.status === 'success') {
                alert(result.message);
                navigateTo('subscriptions-management');
            }
        }
    }

    // --- CRUD for Companies (Super Admin) ---

    async function renderCompaniesManagement() {
        const result = await fetchAPI('api/companies.php');
        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری شرکت‌ها</h2>';
            return;
        }

        const companies = result.data;
        let tableRows = companies.map(c => `
            <tr>
                <td>${c.name}</td>
                <td>${c.expires_at ? new Date(c.expires_at).toLocaleDateString('fa-IR') : 'نامحدود'}</td>
                <td>
                    <button class="btn-edit" data-id="${c.id}">ویرایش</button>
                    <button class="btn-delete danger" data-id="${c.id}">حذف</button>
                </td>
            </tr>
        `).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>مدیریت شرکت‌ها</h2>
            </div>
            <button id="add-new-company-btn">افزودن شرکت جدید</button>
            <table>
                <thead>
                    <tr>
                        <th>نام شرکت</th>
                        <th>تاریخ انقضا</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    ${tableRows}
                </tbody>
            </table>
        `;

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        document.getElementById('add-new-company-btn').addEventListener('click', () => renderAddEditCompanyForm());
        pageContent.querySelectorAll('.btn-edit').forEach(btn => btn.addEventListener('click', (e) => renderAddEditCompanyForm(e.target.dataset.id)));
        pageContent.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', (e) => deleteCompany(e.target.dataset.id)));
    }

    async function renderAddEditCompanyForm(id = null) {
        let company = { id: null, name: '', expires_at: '' };
        const title = id ? 'ویرایش شرکت' : 'افزودن شرکت';

        if (id) {
            const result = await fetchAPI(`api/companies.php?id=${id}`);
            if (result && result.status === 'success') {
                company = result.data;
            }
        }

        pageContent.innerHTML = `
            <h2>${title}</h2>
            <form id="company-form">
                <input type="hidden" name="id" value="${company.id || ''}">
                <div class="form-group">
                    <label for="company-name">نام شرکت</label>
                    <input type="text" id="company-name" name="name" value="${company.name}" required>
                </div>
                <div class="form-group">
                    <label for="expires-at">تاریخ انقضا (اختیاری)</label>
                    <input type="text" id="expires-at" name="expires_at" value="${company.expires_at ? company.expires_at.split(' ')[0] : ''}" data-jdp>
                </div>
                <button type="submit">ذخیره</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;

        jalaliDatepicker.startWatch({ selector: '[data-jdp]', time: false });
        document.getElementById('company-form').addEventListener('submit', saveCompany);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('companies-management'));
    }

    async function saveCompany(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        const endpoint = data.id ? `api/companies.php` : 'api/companies.php';
        const method = data.id ? 'PUT' : 'POST';

        const result = await fetchAPI(endpoint, { method: method, body: data });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('companies-management');
        }
    }

    async function deleteCompany(id) {
        if (confirm('آیا از حذف این شرکت مطمئن هستید؟ تمام کاربران و FAT های مرتبط حذف یا بدون شرکت خواهند شد.')) {
            const result = await fetchAPI(`api/companies.php?id=${id}`, { method: 'DELETE' });
             if (result && result.status === 'success') {
                alert(result.message);
                navigateTo('companies-management');
            }
        }
    }

    // --- CRUD for Users (Super Admin) ---

    async function renderUsersManagement() {
        loadView('users_management', async () => {
            const result = await fetchAPI('api/users.php');
            if (!result || result.status !== 'success') {
                pageContent.innerHTML = '<h2>خطا در بارگذاری کاربران</h2>';
                return;
            }

            const users = result.data;
            const tableBody = document.getElementById('users-table-body');
            const rowTemplate = document.getElementById('user-row-template');
            tableBody.innerHTML = ''; // Clear previous content

            users.forEach(user => {
                const row = rowTemplate.content.cloneNode(true);
                row.querySelector('[data-field="username"]').textContent = user.username;
                row.querySelector('[data-field="role"]').textContent = user.role;
                row.querySelector('[data-field="company_name"]').textContent = user.company_name || '---';

                const actionsCell = row.querySelector('[data-field="actions"]');
                if (user.id != appState.currentUser.id) {
                    const editBtn = document.createElement('button');
                    editBtn.className = 'btn-edit';
                    editBtn.textContent = 'ویرایش';
                    editBtn.addEventListener('click', () => renderEditUserForm(user.id));

                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'btn-delete danger';
                    deleteBtn.textContent = 'حذف';
                    deleteBtn.addEventListener('click', () => deleteUser(user.id));

                    actionsCell.appendChild(editBtn);
                    actionsCell.appendChild(deleteBtn);
                }
                tableBody.appendChild(row);
            });

            pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
            document.getElementById('add-new-user-btn').addEventListener('click', () => renderAddUserForm());
        });
    }

    async function renderEditUserForm(id) {
        loadView('edit_user_form', async () => {
            const userRole = appState.currentUser.role;

            const userResult = await fetchAPI(`api/users.php?id=${id}`);
            if (!userResult || !userResult.data) {
                pageContent.innerHTML = '<h2>خطا: کاربر یافت نشد.</h2>';
                return;
            }
            const user = userResult.data;

            document.getElementById('edit-username').textContent = user.username;
            document.getElementById('edit-user-id').value = user.id;

            let roleOptions = '';
            if (userRole === 'super_admin') {
                roleOptions = `<option value="${user.role}" selected>${user.role}</option>`;
            } else if (userRole === 'company_admin') {
                roleOptions = `
                    <option value="installer" ${user.role === 'installer' ? 'selected' : ''}>نصاب</option>
                    <option value="support" ${user.role === 'support' ? 'selected' : ''}>پشتیبان</option>
                `;
            }
            const roleSelect = document.getElementById('user-role');
            roleSelect.innerHTML = roleOptions;

            document.getElementById('user-form').addEventListener('submit', saveUser);
            document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('users-management'));
        });
    }

    async function renderAddUserForm() {
        loadView('add_user_form', async () => {
            const userRole = appState.currentUser.role;
            let roleOptions = '';

            if (userRole === 'super_admin') {
                roleOptions = '<option value="company_admin">ادمین شرکت</option>';

                const companiesResult = await fetchAPI('api/companies.php');
                if (companiesResult && companiesResult.data) {
                    const companiesOptions = companiesResult.data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
                    const companySelector = `
                        <div class="form-group" id="company-select-group">
                           <label for="user-company-id">شرکت</label>
                           <select id="user-company-id" name="company_id" required>
                               <option value="">انتخاب کنید...</option>
                               ${companiesOptions}
                           </select>
                       </div>
                    `;
                    document.getElementById('company-select-container').innerHTML = companySelector;
                }
            } else if (userRole === 'company_admin') {
                roleOptions = `
                    <option value="installer">نصاب</option>
                    <option value="support">پشتیبان</option>
                `;
            }
            document.getElementById('user-role').innerHTML = roleOptions;

            document.getElementById('user-form').addEventListener('submit', saveUser);
            document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('users-management'));
        });
    }

    async function saveUser(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // If password is not provided on edit, don't send it
        if (data.id && !data.password) {
            delete data.password;
        }

        const method = data.id ? 'PUT' : 'POST';

        const result = await fetchAPI('api/users.php', { method: method, body: data });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('users-management');
        }
    }

    async function deleteUser(id) {
        if (confirm('آیا از حذف این کاربر مطمئن هستید؟')) {
            const result = await fetchAPI(`api/users.php?id=${id}`, { method: 'DELETE' });
             if (result && result.status === 'success') {
                alert(result.message);
                navigateTo('users-management');
            }
        }
    }


    // --- Installer UI ---

    async function renderInstallerDashboard() {
        pageContent.innerHTML = `<h2>داشبورد نصاب</h2><p>در حال بارگذاری کارهای شما...</p>`;
        const result = await fetchAPI('api/assignments.php'); // GET request by default

        if (!result || result.status !== 'success') {
            pageContent.innerHTML = '<h2>خطا در بارگذاری لیست کارها</h2>';
            return;
        }

        const assignments = result.data;
        if (assignments.length === 0) {
            pageContent.innerHTML = '<h2>داشبورد نصاب</h2><p>در حال حاضر هیچ کاری به شما ارجاع داده نشده است.</p>';
            return;
        }

        let tableRows = assignments.map(a => {
            const status_text = a.installation_status === 'completed'
                ? '<span style="color:green;">تکمیل شده</span>'
                : '<span style="color:orange;">در انتظار انجام</span>';
            const report_button = a.installation_status !== 'completed'
                ? `<button class="btn-report" data-id="${a.id}">ثبت گزارش</button>`
                : 'گزارش ثبت شده';
            return `
                <tr>
                    <td>${a.subscriber_name}</td>
                    <td>${a.fat_number}</td>
                    <td>${a.address || 'ثبت نشده'}</td>
                    <td>${status_text}</td>
                    <td>${report_button}</td>
                </tr>
            `;
        }).join('');

        pageContent.innerHTML = `
            <div class="page-header">
                <button class="btn-back" title="بازگشت به داشبورد"><i class="fa-solid fa-arrow-right"></i></button>
                <h2>کارهای ارجاع شده به شما</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>نام مشترک</th>
                            <th>شماره FAT</th>
                            <th>آدرس</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${tableRows}
                    </tbody>
                </table>
            </div>
        `;

        pageContent.querySelector('.btn-back').addEventListener('click', () => navigateTo('dashboard'));
        pageContent.querySelectorAll('.btn-report').forEach(btn => btn.addEventListener('click', (e) => renderInstallationReportForm(e.target.dataset.id)));
    }

    function renderInstallationReportForm(subscription_id) {
        pageContent.innerHTML = `
            <h2>ثبت گزارش نصب برای اشتراک #${subscription_id}</h2>
            <form id="installation-report-form">
                <input type="hidden" name="type" value="installation">
                <input type="hidden" name="target_id" value="${subscription_id}">
                <div class="form-group">
                    <label for="cable-length">متراژ کابل مصرفی (متر)</label>
                    <input type="number" id="cable-length" name="cable_length" step="0.1">
                </div>
                <div class="form-group">
                    <label for="cable-type">نوع کابل مصرفی</label>
                    <input type="text" id="cable-type" name="cable_type" placeholder="مثال: 2-core indoor">
                </div>
                <div class="form-group">
                    <label>لوازم مصرفی دیگر (JSON)</label>
                    <textarea name="materials_used" rows="4" placeholder='[{"item": "fast connector", "quantity": 2}, {"item": "pigtail", "quantity": 1}]'></textarea>
                </div>
                <div class="form-group">
                    <label for="notes">توضیحات</label>
                    <textarea id="notes" name="notes" rows="4"></textarea>
                </div>
                <button type="submit">ثبت گزارش</button>
                <button type="button" id="cancel-btn">انصراف</button>
            </form>
        `;
        document.getElementById('installation-report-form').addEventListener('submit', saveInstallationReport);
        document.getElementById('cancel-btn').addEventListener('click', () => navigateTo('installer-dashboard'));
    }

    async function saveInstallationReport(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // Validate and parse JSON
        try {
            if (data.materials_used) {
                data.materials_used = JSON.parse(data.materials_used);
            }
        } catch (error) {
            alert('فرمت JSON برای لوازم مصرفی نامعتبر است.');
            return;
        }

        const result = await fetchAPI('api/workflow_reports.php', { method: 'POST', body: data });

        if (result && result.status === 'success') {
            alert(result.message);
            navigateTo('installer-dashboard');
        }
    }

    // --- Initial Check ---
    // A simple check to see if a session is active.
    // In a real app, you might have a `check_session.php` endpoint.
    // For now, we assume if the page is reloaded, the user is logged out.
    showLogin();

    // --- PWA Service Worker Registration ---
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                })
                .catch(err => {
                    console.log('ServiceWorker registration failed: ', err);
                });
        });
    }

});
