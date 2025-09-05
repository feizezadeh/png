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
            appState.currentUser = result.data;
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
            default:
                pageContent.innerHTML = '<h2>صفحه مورد نظر یافت نشد</h2>';
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
            <h2>مدیریت مراکز مخابراتی</h2>
            <button id="add-new-center-btn">افزودن مرکز جدید</button>
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
            <h2>مدیریت FAT ها</h2>
            <button id="add-new-fat-btn">افزودن FAT جدید</button>
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
                    <label>موقعیت مکانی (روی نقشه کلیک کنید یا نشانگر را جابجا کنید)</label>
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

        marker.on('dragend', function(event) {
            const position = marker.getLatLng();
            document.getElementById('latitude').value = position.lat;
            document.getElementById('longitude').value = position.lng;
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('latitude').value = e.latlng.lat;
            document.getElementById('longitude').value = e.latlng.lng;
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
            <h2>گزارش‌گیری پیشرفته</h2>
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
                    <table class="report-table">
                        <thead><tr>${tableHeaders}</tr></thead>
                        <tbody>${tableRows}</tbody>
                    </table>
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
            <h2>مدیریت مشترکین</h2>
            <button id="add-new-subscriber-btn">افزودن مشترک جدید</button>
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
                    <button class="btn-delete danger" data-id="${sub.id}">حذف</button>
                </td>
            </tr>
        `).join('');

        pageContent.innerHTML = `
            <h2>مدیریت اشتراک‌ها</h2>
            <button id="add-new-subscription-btn">افزودن اشتراک جدید</button>
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

        document.getElementById('add-new-subscription-btn').addEventListener('click', () => renderAddEditSubscriptionForm());
        pageContent.querySelectorAll('.btn-delete').forEach(btn => btn.addEventListener('click', (e) => deleteSubscription(e.target.dataset.id)));
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
