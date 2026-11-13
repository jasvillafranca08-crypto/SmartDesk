
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['admin']);

// TICKET SUMMARY
$stats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status IN ('open', 'assigned')), 0) AS pending,
        COALESCE(SUM(status = 'in_progress'), 0) AS in_progress,
        COALESCE(SUM(status = 'resolved'), 0) AS resolved
     FROM tickets"
)->fetch();

// TICKETS BY STATUS
$statusRows = $pdo->query(
    "SELECT status, COUNT(*) AS total
     FROM tickets
     GROUP BY status"
)->fetchAll();

$statusLabels = [];
$statusValues = [];

foreach ($statusRows as $row) {
    $statusLabels[] = ucwords(str_replace('_', ' ', $row['status']));
    $statusValues[] = (int) $row['total'];
}

// TICKETS BY PRIORITY
$priorityRows = $pdo->query(
    "SELECT priority, COUNT(*) AS total
     FROM tickets
     GROUP BY priority"
)->fetchAll();

$priorityCounts = [
    'low' => 0,
    'medium' => 0,
    'high' => 0,
    'critical' => 0
];

foreach ($priorityRows as $row) {
    if (array_key_exists($row['priority'], $priorityCounts)) {
        $priorityCounts[$row['priority']] = (int) $row['total'];
    }
}

// TECHNICIAN WORKLOAD
$technicians = $pdo->query(
    "SELECT
        u.id,
        u.name,
        COUNT(t.id) AS assigned_total,
        COALESCE(SUM(t.status IN ('assigned', 'in_progress')), 0)
            AS active_total,
        COALESCE(SUM(t.status = 'resolved'), 0) AS resolved_total
     FROM users u
     LEFT JOIN tickets t ON t.technician_id = u.id
     WHERE u.role = 'technician'
     GROUP BY u.id, u.name
     ORDER BY active_total DESC, u.name"
)->fetchAll();

// SAFE CHART DATA
function chartJson($data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_THROW_ON_ERROR
    );
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Analytics Dashboard - SmartDesk</title>

    
<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
    onerror="this.onerror=null; this.src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';">
</script>


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
            --chart-text: #756B82;
            --chart-grid: #E9DDF9;
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
            --chart-text: #D0C4E0;
            --chart-grid: #413450;
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

        /* MAIN LAYOUT - MATCHES DASHBOARD.PHP */

        .dashboard-main {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 55px 5% 65px;
        }

        /* HERO BANNER - MATCHES DASHBOARD.PHP */

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

        /* TICKET SUMMARY CARDS */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .summary-card {
            min-height: 145px;
            padding: 25px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            color: var(--text-primary);
            box-shadow: var(--shadow);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .summary-card p {
            margin-bottom: 17px;
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 600;
        }

        .summary-card h3 {
            color: var(--primary);
            font-size: 35px;
            font-weight: 800;
        }

        /* ANALYTICS CHARTS */

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .analytics-panel {
            min-width: 0;
            padding: 30px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: background 0.25s ease;
        }

        .analytics-panel h3 {
            margin-bottom: 22px;
            color: var(--text-primary);
            font-size: 21px;
            font-weight: 800;
        }

        .chart-area {
            position: relative;
            width: 100%;
            height: 330px;
        }

        /* TECHNICIAN WORKLOAD */

        .workload-panel {
            margin-top: 25px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .workload-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .workload-table th,
        .workload-table td {
            padding: 16px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .workload-table th {
            background: var(--surface-soft);
            color: var(--primary);
            font-weight: 700;
        }

        .workload-table td {
            color: var(--text-secondary);
        }

        .workload-table tbody tr:last-child td {
            border-bottom: none;
        }

        .workload-table tbody tr:hover {
            background: var(--surface-soft);
        }

        .empty-state {
            padding: 25px;
            text-align: center !important;
            color: var(--text-muted) !important;
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

            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 850px) {
            .dashboard-main {
                padding: 35px 4% 50px;
            }

            .welcome-card {
                padding: 38px;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .chart-area {
                height: 320px;
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

            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .summary-card {
                min-height: 125px;
                padding: 18px;
            }

            .summary-card p {
                font-size: 13px;
            }

            .summary-card h3 {
                font-size: 29px;
            }

            .analytics-panel {
                padding: 22px;
            }

            .analytics-panel h3 {
                font-size: 19px;
            }

            .chart-area {
                height: 280px;
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

    <!-- APPLY SAVED THEME BEFORE PAGE DISPLAY -->
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

    <!-- NAVIGATION -->

    <nav class="dashboard-nav">

        <div class="brand-navigation">

            <a class="dashboard-brand" href="dashboard.php">
                <span class="brand-icon">✦</span>
                <span class="brand-text">Smart<span>Desk</span></span>
            </a>

            <div class="page-links" aria-label="Admin navigation">

                <a href="dashboard.php">
                    Dashboard
                </a>

                <a
                    href="admin_dashboard.php"
                    class="active"
                    aria-current="page"
                >
                    Analytics Dashboard
                </a>

                <a href="admin_tickets.php">
                    Manage Tickets
                </a>

                <a href="admin_users.php">
                    User Management
                </a>

            </div>

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

            <a class="logout-link" href="logout.php">
                Logout
            </a>

        </div>

    </nav>

    <!-- MAIN CONTENT -->

    <main class="dashboard-main">

        <!-- ANALYTICS HERO -->

        <section class="welcome-card">

            <div class="welcome-content">

                <span class="welcome-label">
                    IT Service Desk and Ticket Management System
                </span>

                <h1>SmartDesk Analytics Dashboard</h1>

                <p>
                    Monitor ticket statistics, review priorities,
                    and check technician workloads through organized
                    reports and visual charts.
                </p>

                <span class="role-label">
                    Admin Analytics
                </span>

            </div>

        </section>

        <!-- TICKET OVERVIEW -->

        <section class="dashboard-section">

            <div class="section-heading">
                <h2>Ticket Overview</h2>
                <p>
                    View the current performance of your service desk.
                </p>
            </div>

            <div class="summary-grid">

                <div class="summary-card">
                    <p>Total Tickets</p>
                    <h3><?= (int) $stats['total'] ?></h3>
                </div>

                <div class="summary-card">
                    <p>Pending Tickets</p>
                    <h3><?= (int) $stats['pending'] ?></h3>
                </div>

                <div class="summary-card">
                    <p>In Progress</p>
                    <h3><?= (int) $stats['in_progress'] ?></h3>
                </div>

                <div class="summary-card">
                    <p>Resolved</p>
                    <h3><?= (int) $stats['resolved'] ?></h3>
                </div>

            </div>

        </section>

        <!-- ANALYTICS AND REPORTS -->

        <section class="dashboard-section">

            <div class="section-heading">
                <h2>Analytics and Reports</h2>
                <p>
                    Visual breakdown of tickets by status and priority.
                </p>
            </div>

            <div class="charts-grid">

                <div class="analytics-panel">

                    <h3>Tickets by Status</h3>

                    <div class="chart-area">
                        <canvas id="statusChart"></canvas>
                    </div>

                </div>

                <div class="analytics-panel">

                    <h3>Tickets by Priority</h3>

                    <div class="chart-area">
                        <canvas id="priorityChart"></canvas>
                    </div>

                </div>

            </div>

        </section>

        <!-- TECHNICIAN WORKLOAD -->

        <section class="analytics-panel workload-panel">

            <h3>Technician Workload</h3>

            <div class="table-wrap">

                <table class="workload-table">

                    <thead>
                        <tr>
                            <th>Technician</th>
                            <th>Total Assigned</th>
                            <th>Active Tickets</th>
                            <th>Resolved Tickets</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$technicians): ?>

                        <tr>
                            <td colspan="4" class="empty-state">
                                No technician accounts found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($technicians as $tech): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        $tech['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int) $tech['assigned_total'] ?>
                                </td>

                                <td>
                                    <?= (int) $tech['active_total'] ?>
                                </td>

                                <td>
                                    <?= (int) $tech['resolved_total'] ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

        <!-- FOOTER -->

        <footer class="dashboard-footer">
            SmartDesk © 2026. All rights reserved.
            <br>
            IT Service Desk and Ticket Management System
        </footer>

    </main>

    <script>
        // THEME SELECTOR - SAME AS DASHBOARD.PHP

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

            return activeTheme;
        }

        const savedPreference =
            localStorage.getItem('smartdesk-theme') || 'system';

        themeSelect.value = savedPreference;

        let activeTheme = applyTheme(savedPreference);

        themeSelect.addEventListener('change', function () {

            const preference = this.value;

            localStorage.setItem(
                'smartdesk-theme',
                preference
            );

            activeTheme = applyTheme(preference);

            updateChartTheme();
        });

        systemTheme.addEventListener('change', function () {

            if (
                (localStorage.getItem('smartdesk-theme') || 'system')
                === 'system'
            ) {
                activeTheme = applyTheme('system');
                updateChartTheme();
            }

        });

        // CHART DATA

        const statusLabels = <?= chartJson($statusLabels) ?>;
        const statusValues = <?= chartJson($statusValues) ?>;

        const priorityLabels = [
            'Low',
            'Medium',
            'High',
            'Critical'
        ];

        const priorityValues =
            <?= chartJson(array_values($priorityCounts)) ?>;

        // GRAPH COLORS

        const graphColors = {
            blue: '#4A90E2',
            teal: '#36B5A0',
            orange: '#F4A340',
            coral: '#E97878',
            green: '#65B889'
        };

        // CHART THEME COLORS

        function getChartTextColor() {
            return activeTheme === 'dark'
                ? '#D0C4E0'
                : '#756B82';
        }

        function getChartGridColor() {
            return activeTheme === 'dark'
                ? '#413450'
                : '#E9DDF9';
        }

        function getChartBorderColor() {
            return activeTheme === 'dark'
                ? '#211C2D'
                : '#FFFFFF';
        }

        // TICKETS BY STATUS

        const statusChart = new Chart(
            document.getElementById('statusChart'),
            {
                type: 'doughnut',

                data: {
                    labels: statusLabels,

                    datasets: [{
                        label: 'Tickets',
                        data: statusValues,

                        backgroundColor: [
                            graphColors.blue,
                            graphColors.teal,
                            graphColors.orange,
                            graphColors.coral,
                            graphColors.green
                        ],

                        borderWidth: 3,
                        borderColor: getChartBorderColor(),
                        hoverOffset: 8
                    }]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    cutout: '55%',

                    plugins: {
                        legend: {
                            position: 'top',

                            labels: {
                                color: getChartTextColor(),
                                boxWidth: 15,
                                boxHeight: 9,
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            }
        );

        // TICKETS BY PRIORITY

        const priorityChart = new Chart(
            document.getElementById('priorityChart'),
            {
                type: 'bar',

                data: {
                    labels: priorityLabels,

                    datasets: [{
                        label: 'Number of Tickets',
                        data: priorityValues,

                        backgroundColor: [
                            graphColors.teal,
                            graphColors.blue,
                            graphColors.orange,
                            graphColors.coral
                        ],

                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 65
                    }]
                },

                options: {
                    responsive: true,
                    maintainAspectRatio: false,

                    plugins: {
                        legend: {
                            position: 'top',

                            labels: {
                                color: getChartTextColor(),
                                boxWidth: 15,
                                boxHeight: 9,
                                padding: 15,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    },

                    scales: {
                        x: {
                            ticks: {
                                color: getChartTextColor(),
                                font: {
                                    size: 12
                                }
                            },

                            grid: {
                                color: getChartGridColor()
                            }
                        },

                        y: {
                            beginAtZero: true,

                            ticks: {
                                precision: 0,
                                color: getChartTextColor()
                            },

                            grid: {
                                color: getChartGridColor()
                            }
                        }
                    }
                }
            }
        );

        // UPDATE CHARTS WHEN APPEARANCE CHANGES

        function updateChartTheme() {

            const textColor = getChartTextColor();
            const gridColor = getChartGridColor();
            const borderColor = getChartBorderColor();

            statusChart.data.datasets[0].borderColor = borderColor;

            statusChart.options.plugins.legend.labels.color =
                textColor;

            priorityChart.options.plugins.legend.labels.color =
                textColor;

            priorityChart.options.scales.x.ticks.color =
                textColor;

            priorityChart.options.scales.y.ticks.color =
                textColor;

            priorityChart.options.scales.x.grid.color =
                gridColor;

            priorityChart.options.scales.y.grid.color =
                gridColor;

            statusChart.update();
            priorityChart.update();
        }
    </script>

</body>
</html>
