
<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$role = $_SESSION['user_role'];
$name = $_SESSION['user_name'];

$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeRole = htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8');

$welcomeMessages = [
    'admin' => 'Manage your service desk, monitor support activities, and keep your team organized.',
    'technician' => 'Stay updated with your assigned support requests and keep every task moving forward.',
    'requester' => 'Get the technical assistance you need and stay informed about your support requests.'
];

$welcomeMessage = $welcomeMessages[$role] ?? 'Welcome to your SmartDesk workspace.';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SmartDesk</title>

    <style>
        :root {
            color-scheme: light;
            --page-bg: #FCFAFF;
            --surface: #FFFFFF;
            --surface-soft: #F7F2FF;
            --surface-hover: #F0E7FF;
            --text-primary: #302344;
            --text-secondary: #5E536D;
            --text-muted: #756B82;
            --primary: #7546C2;
            --primary-light: #9562E8;
            --border: #E9DDF9;
            --shadow: 0 7px 22px rgba(110, 75, 160, 0.07);
            --shadow-hover: 0 14px 30px rgba(137, 89, 214, 0.15);
        }

        [data-theme="dark"] {
            color-scheme: dark;
            --page-bg: #171421;
            --surface: #211C2D;
            --surface-soft: #2B2438;
            --surface-hover: #382B4C;
            --text-primary: #F4EDFF;
            --text-secondary: #D0C4E0;
            --text-muted: #B4A6C8;
            --primary: #C4A0FF;
            --primary-light: #D3B7FF;
            --border: #413450;
            --shadow: 0 7px 22px rgba(0, 0, 0, 0.18);
            --shadow-hover: 0 14px 30px rgba(0, 0, 0, 0.28);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            transition: background 0.25s ease, color 0.25s ease;
        }

        /* NAVIGATION */

        .dashboard-nav {
            width: 100%;
            min-height: 78px;
            padding: 0 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .brand-navigation {
            display: flex;
            align-items: center;
            gap: 28px;
            min-width: 0;
        }

        .dashboard-brand {
            display: inline-flex;
            align-items: center;
            gap: 13px;
            color: var(--text-primary);
            text-decoration: none;
            flex-shrink: 0;
        }

        .brand-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #C7A6FF, #8959D6);
            color: #FFFFFF;
            border-radius: 15px;
            font-size: 31px;
            font-weight: 800;
            box-shadow: 0 5px 14px rgba(144, 97, 232, 0.25);
        }

        .brand-text {
            font-family: "Trebuchet MS", Arial, sans-serif;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: -1.3px;
            color: var(--text-primary);
        }

        .brand-text span {
            color: var(--primary-light);
        }

        .page-links {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .page-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-soft);
            color: var(--primary);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            transition: 0.2s ease;
        }

        .page-links a:hover,
        .page-links a.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #FFFFFF;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .theme-control {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 7px 11px;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .theme-control label {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 700;
        }

        .theme-control select {
            padding: 5px 7px;
            border: none;
            outline: none;
            background: transparent;
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .theme-control select option {
            background: var(--surface);
            color: var(--text-primary);
        }

        .logout-link {
            display: inline-block;
            padding: 12px 22px;
            background: var(--surface-soft);
            color: var(--primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .logout-link:hover {
            background: #7546C2;
            color: #FFFFFF;
        }

        /* MAIN LAYOUT */

        .dashboard-main {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 55px 5% 65px;
        }

        /* WELCOME BANNER */

        .welcome-card {
            position: relative;
            overflow: hidden;
            min-height: 290px;
            padding: 48px 55px;
            display: flex;
            align-items: center;
            background: linear-gradient(
                120deg,
                #C9AEF5 0%,
                #A77BEA 55%,
                #8959D6 100%
            );
            border-radius: 20px;
            color: #FFFFFF;
            box-shadow: 0 14px 35px rgba(137, 89, 214, 0.20);
        }

        .welcome-card::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            right: -65px;
            top: -110px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 750px;
        }

        .welcome-label {
            display: inline-block;
            margin-bottom: 18px;
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .welcome-card h1 {
            margin-bottom: 15px;
            color: #FFFFFF;
            font-size: clamp(32px, 4vw, 46px);
            line-height: 1.2;
            font-weight: 800;
        }

        .welcome-card p {
            max-width: 680px;
            color: #FFFFFF;
            font-size: 17px;
            line-height: 1.8;
        }

        .role-label {
            display: inline-block;
            margin-top: 24px;
            padding: 11px 19px;
            background: rgba(255, 255, 255, 0.20);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 30px;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
        }

        /* SECTION HEADINGS */

        .dashboard-section {
            margin-top: 48px;
        }

        .section-heading {
            margin-bottom: 22px;
        }

        .section-heading h2 {
            margin-bottom: 8px;
            color: var(--text-primary);
            font-size: 27px;
            font-weight: 800;
        }

        .section-heading p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
        }

        /* WORKSPACE CARDS */

        .dashboard-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }

        .dashboard-link-card {
            min-height: 205px;
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            color: var(--text-primary);
            text-decoration: none;
            box-shadow: var(--shadow);
            transition: transform 0.25s ease, box-shadow 0.25s ease,
                        border-color 0.25s ease, background 0.25s ease;
        }

        .dashboard-link-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-light);
            box-shadow: var(--shadow-hover);
        }

        .card-icon {
            width: 52px;
            height: 52px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--surface-hover);
            border-radius: 12px;
            color: var(--primary);
            font-size: 25px;
        }

        .dashboard-link-card h3 {
            margin-bottom: 11px;
            color: var(--primary);
            font-size: 21px;
            font-weight: 800;
        }

        .dashboard-link-card p {
            max-width: 500px;
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.8;
        }

        .card-action {
            display: inline-block;
            margin-top: 22px;
            color: var(--primary);
            font-size: 14px;
            font-weight: 800;
        }

        /* INFORMATION SECTION */

        .information-panel {
            margin-top: 35px;
            padding: 32px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .information-panel h2 {
            margin-bottom: 12px;
            color: var(--text-primary);
            font-size: 22px;
            font-weight: 800;
        }

        .information-panel > p {
            max-width: 1000px;
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.9;
        }

        .information-features {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-top: 25px;
        }

        .information-item {
            padding: 19px;
            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .information-item strong {
            display: block;
            margin-bottom: 8px;
            color: var(--primary);
            font-size: 15px;
        }

        .information-item span {
            display: block;
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.7;
        }

        /* FOOTER */

        .dashboard-footer {
            margin-top: 45px;
            padding: 22px 10px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* RESPONSIVE */

        @media (max-width: 1100px) {
            .dashboard-nav {
                align-items: flex-start;
                padding-top: 14px;
                padding-bottom: 14px;
            }

            .brand-navigation {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }
        }

        @media (max-width: 850px) {
            .dashboard-main {
                padding: 35px 4% 50px;
            }

            .welcome-card {
                padding: 38px;
            }

            .information-features {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .dashboard-nav {
                min-height: 70px;
                padding: 12px 16px;
                flex-wrap: wrap;
                gap: 12px;
            }

            .brand-navigation {
                width: 100%;
                gap: 10px;
            }

            .page-links {
                width: 100%;
                gap: 6px;
            }

            .page-links a {
                min-height: 34px;
                padding: 0 9px;
                font-size: 10px;
            }

            .brand-icon {
                width: 43px;
                height: 43px;
                font-size: 27px;
            }

            .brand-text {
                font-size: 26px;
            }

            .nav-actions {
                gap: 7px;
            }

            .theme-control {
                gap: 4px;
                padding: 6px;
            }

            .theme-control label {
                display: none;
            }

            .theme-control select {
                max-width: 105px;
                font-size: 12px;
            }

            .logout-link {
                padding: 10px 13px;
                font-size: 12px;
            }

            .dashboard-main {
                padding: 25px 16px 40px;
            }

            .welcome-card {
                min-height: 260px;
                padding: 30px 25px;
                border-radius: 15px;
            }

            .welcome-label {
                font-size: 11px;
                line-height: 1.6;
            }

            .welcome-card h1 {
                font-size: 30px;
            }

            .welcome-card p {
                font-size: 14px;
            }

            .section-heading h2 {
                font-size: 23px;
            }

            .dashboard-links {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .dashboard-link-card {
                min-height: 180px;
                padding: 24px;
            }

            .information-panel {
                padding: 23px;
            }

            .dashboard-footer {
                font-size: 12px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>

    <!-- Apply saved theme before the page is displayed -->
    <script>
        (() => {
            const savedTheme =
                localStorage.getItem('smartdesk-theme') || 'system';

            const systemDark =
                window.matchMedia('(prefers-color-scheme: dark)').matches;

            const activeTheme = savedTheme === 'system'
                ? (systemDark ? 'dark' : 'light')
                : savedTheme;

            document.documentElement.setAttribute(
                'data-theme',
                activeTheme
            );
        })();
    </script>
</head>

<body>

    <nav class="dashboard-nav">

        <div class="brand-navigation">

            <a class="dashboard-brand" href="dashboard.php">
                <span class="brand-icon">✦</span>
                <span class="brand-text">Smart<span>Desk</span></span>
            </a>

            
<?php if ($role === 'admin'): ?>
    <div class="page-links" aria-label="Admin navigation">
        <a href="dashboard.php" class="active" aria-current="page">
            Dashboard
        </a>

        <a href="admin_dashboard.php">
            Analytics Dashboard
        </a>

        <a href="admin_tickets.php">
            Manage Tickets
        </a>

        <a href="admin_users.php">
            User Management
        </a>
    </div>


<?php elseif ($role === 'technician'): ?>
    <div class="page-links" aria-label="Technician navigation">
        <a href="dashboard.php" class="active" aria-current="page">
            Dashboard
        </a>

        <a href="technician_tickets.php">
            Assigned Tickets
        </a>
    </div>

<?php elseif ($role === 'requester'): ?>
    <div class="page-links" aria-label="Requester navigation">
        <a href="dashboard.php" class="active" aria-current="page">
            Dashboard
        </a>

        <a href="create_ticket.php">
            Submit Ticket
        </a>

        <a href="my_tickets.php">
            My Tickets
        </a>
    </div>
<?php endif; ?>



        </div>

        <div class="nav-actions">

            <div class="theme-control">
                <label for="themeSelect">Appearance</label>

                <select id="themeSelect" aria-label="Choose appearance">
                    <option value="light">☀ Light</option>
                    <option value="dark">☾ Dark</option>
                    <option value="system">⚙ System</option>
                </select>
            </div>

            <a class="logout-link" href="logout.php">Logout</a>

        </div>

    </nav>

    <main class="dashboard-main">

        <section class="welcome-card">

            <div class="welcome-content">

                <span class="welcome-label">
                    IT Service Desk and Ticket Management System
                </span>

                <h1>
                    Welcome to SmartDesk,<br>
                    <?= $safeName ?>!
                </h1>

                <p>
                    <?= htmlspecialchars($welcomeMessage, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <span class="role-label">
                    <?= $safeRole ?> Account
                </span>

            </div>

        </section>

        <section class="dashboard-section">

            <div class="section-heading">
                <h2>Your Workspace</h2>
                <p>Access the tools and features available for your account.</p>
            </div>

            <div class="dashboard-links">

                <?php if ($role === 'admin'): ?>

                    <a class="dashboard-link-card" href="admin_dashboard.php">
                        <div>
                            <div class="card-icon">▥</div>
                            <h3>Analytics Dashboard</h3>
                            <p>
                                Monitor ticket statistics, review priorities,
                                and check technician workloads through
                                organized reports and visual charts.
                            </p>
                        </div>
                        <span class="card-action">View Analytics →</span>
                    </a>

                    <a class="dashboard-link-card" href="admin_tickets.php">
                        <div>
                            <div class="card-icon">▤</div>
                            <h3>Manage Tickets</h3>
                            <p>
                                Review submitted support requests, assign
                                technicians, and monitor the progress
                                of service tickets.
                            </p>
                        </div>
                        <span class="card-action">Manage Tickets →</span>
                    </a>

                    <a class="dashboard-link-card" href="admin_users.php">
                        <div>
                            <div class="card-icon">♙</div>
                            <h3>User Management</h3>
                            <p>
                                View user accounts, manage technician
                                and requester accounts, and organize
                                system access.
                            </p>
                        </div>
                        <span class="card-action">Manage Users →</span>
                    </a>

                <?php elseif ($role === 'technician'): ?>

                    <a class="dashboard-link-card" href="technician_tickets.php">
                        <div>
                            <div class="card-icon">✓</div>
                            <h3>Assigned Tickets</h3>
                            <p>
                                View your assigned support requests,
                                review reported issues, and update
                                ticket progress.
                            </p>
                        </div>
                        <span class="card-action">View Assigned Tickets →</span>
                    </a>

                <?php elseif ($role === 'requester'): ?>

                    <a class="dashboard-link-card" href="create_ticket.php">
                        <div>
                            <div class="card-icon">＋</div>
                            <h3>Submit a Ticket</h3>
                            <p>
                                Report hardware, software, network,
                                or other technical problems and
                                request IT assistance.
                            </p>
                        </div>
                        <span class="card-action">Create a Ticket →</span>
                    </a>

                    <a class="dashboard-link-card" href="my_tickets.php">
                        <div>
                            <div class="card-icon">▣</div>
                            <h3>My Tickets</h3>
                            <p>
                                Review your submitted support requests,
                                check their assigned technicians,
                                and monitor their current statuses.
                            </p>
                        </div>
                        <span class="card-action">View My Tickets →</span>
                    </a>

                <?php endif; ?>

            </div>

        </section>

        <section class="information-panel">

            <h2>About SmartDesk</h2>

            <p>
                SmartDesk provides a centralized workspace for managing
                IT support requests and service activities. It helps
                organize ticket submissions, technician assignments,
                and service progress in one accessible platform.
            </p>

            <div class="information-features">

                <div class="information-item">
                    <strong>Organized Support</strong>
                    <span>
                        Keep technical concerns organized through
                        a structured ticket management process.
                    </span>
                </div>

                <div class="information-item">
                    <strong>Efficient Management</strong>
                    <span>
                        Support service coordination through
                        accessible management tools.
                    </span>
                </div>

                <div class="information-item">
                    <strong>Progress Monitoring</strong>
                    <span>
                        Follow ticket assignments and service
                        status updates through SmartDesk.
                    </span>
                </div>

            </div>

        </section>

        <footer class="dashboard-footer">
            SmartDesk © 2026. All rights reserved.
            <br>
            IT Service Desk and Ticket Management System
        </footer>

    </main>

    <script>
        const themeSelect = document.getElementById('themeSelect');

        const systemTheme =
            window.matchMedia('(prefers-color-scheme: dark)');

        function applyTheme(preference) {

            const activeTheme = preference === 'system'
                ? (systemTheme.matches ? 'dark' : 'light')
                : preference;

            document.documentElement.setAttribute(
                'data-theme',
                activeTheme
            );
        }

        const savedPreference =
            localStorage.getItem('smartdesk-theme') || 'system';

        themeSelect.value = savedPreference;
        applyTheme(savedPreference);

        themeSelect.addEventListener('change', function () {

            const preference = this.value;

            localStorage.setItem(
                'smartdesk-theme',
                preference
            );

            applyTheme(preference);
        });

        systemTheme.addEventListener('change', function () {

            if (
                (localStorage.getItem('smartdesk-theme') || 'system')
                === 'system'
            ) {
                applyTheme('system');
            }

        });
    </script>

</body>
</html>
