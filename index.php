<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت اشتراک FTTH</title>

    <!-- Meta tags for PWA -->
    <meta name="theme-color" content="#333"/>
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="manifest" href="manifest.json">

    <!-- External Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/jalalidatepicker/dist/jalalidatepicker.min.css" />

    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <header>
        <h1>سیستم مدیریت اشتراک FTTH</h1>
        <div class="header-actions">
            <button id="logout-btn" style="display: none;"><i class="fa-solid fa-right-from-bracket"></i> خروج</button>
            <button id="theme-toggle-btn"><i class="fa-solid fa-moon"></i></button>
        </div>
    </header>

    <main id="main-content">
        <!-- Login Form Container -->
        <div id="login-container" class="container">
            <form id="login-form" method="POST">
                <h2>ورود به سیستم</h2>
                <div class="form-group">
                    <label for="username">نام کاربری</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">رمز عبور</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">ورود</button>
                <p id="login-error" class="error-message"></p>
            </form>
        </div>

        <!-- Dashboard Container (hidden by default) -->
        <div id="dashboard-container" class="container" style="display: none;">
            <!-- Tile Menu -->
            <div id="tile-menu">
                <div class="tile" data-target="fats-management">
                    <i class="fa-solid fa-network-wired fa-3x"></i>
                    <span>مدیریت FAT ها</span>
                </div>
                <div class="tile" data-target="subscribers-management">
                    <i class="fa-solid fa-users fa-3x"></i>
                    <span>مدیریت مشترکین</span>
                </div>
                <div class="tile" data-target="subscriptions-management">
                    <i class="fa-solid fa-file-signature fa-3x"></i>
                    <span>مدیریت اشتراک‌ها</span>
                </div>
                <div class="tile" data-target="reports-management">
                    <i class="fa-solid fa-chart-line fa-3x"></i>
                    <span>گزارش‌گیری</span>
                </div>
                 <div class="tile" data-target="telecom-centers-management">
                    <i class="fa-solid fa-building-columns fa-3x"></i>
                    <span>مراکز مخابراتی</span>
                </div>
                <div class="tile" data-target="companies-management" data-role="super_admin">
                    <i class="fa-solid fa-building fa-3x"></i>
                    <span>مدیریت شرکت‌ها</span>
                </div>
                <div class="tile" data-target="users-management" data-role="super_admin company_admin">
                    <i class="fa-solid fa-users-cog fa-3x"></i>
                    <span>مدیریت کاربران</span>
                </div>
                <div class="tile" data-target="tickets-management" data-role="super_admin company_admin">
                    <i class="fa-solid fa-life-ring fa-3x"></i>
                    <span>تیکت‌های پشتیبانی</span>
                </div>
                <div class="tile" data-target="installer-dashboard" data-role="installer">
                    <i class="fa-solid fa-screwdriver-wrench fa-3x"></i>
                    <span>داشبورد نصاب</span>
                </div>
                <div class="tile" data-target="support-dashboard" data-role="support">
                    <i class="fa-solid fa-headset fa-3x"></i>
                    <span>داشبورد پشتیبانی</span>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div id="page-content">
                <!-- Content will be loaded here by JavaScript -->
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2023 - کلیه حقوق برای سیستم مدیریت FTTH محفوظ است.</p>
    </footer>

    <!-- External JS Libraries -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/jalalidatepicker/dist/jalalidatepicker.min.js"></script>
    <!-- map.ir JS (if needed, or handle in script.js) -->

    <!-- Main Script -->
    <script src="script.js"></script>
</body>
</html>
