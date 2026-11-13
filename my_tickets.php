
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['requester']);

// FETCH REQUESTER'S OWN TICKETS
$stmt = $pdo->prepare(
    "SELECT t.ticket_number, t.title, t.category,
            t.priority, t.status, t.created_at,
            u.name AS technician_name
     FROM tickets t
     LEFT JOIN users u ON t.technician_id = u.id
     WHERE t.requester_id = ?
     ORDER BY t.created_at DESC
     LIMIT 100"
);

$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll();

$totalTickets = count($tickets);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Tickets - SmartDesk</title>

    <style>
        /* =====================================
           SMARTDESK THEME
        ===================================== */

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

        /* =====================================
           NAVIGATION - SAME AS REFERENCE
        ===================================== */

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

        /* =====================================
           MAIN CONTENT
        ===================================== */

        .dashboard-main {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 55px 5% 65px;
        }

        /* =====================================
           HERO BANNER
        ===================================== */

        .welcome-card {
            position: relative;
            overflow: hidden;

            min-height: 260px;
            padding: 45px 55px;

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
            max-width: 800px;
        }

        .welcome-label {
            display: inline-block;
            margin-bottom: 17px;

            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .welcome-card h1 {
            margin-bottom: 14px;

            color: #FFFFFF;
            font-size: clamp(32px, 4vw, 44px);
            line-height: 1.2;
            font-weight: 800;
        }

        .welcome-card p {
            max-width: 700px;

            color: #FFFFFF;
            font-size: 16px;
            line-height: 1.8;
        }

        .role-label {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 18px;

            background: rgba(255, 255, 255, 0.20);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 30px;

            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
        }

        /* =====================================
           TICKET SECTION
        ===================================== */

        .tickets-section {
            margin-top: 38px;
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
            line-height: 1.7;
        }

        /* =====================================
           TICKET CARD
        ===================================== */

        .tickets-card {
            width: 100%;
            padding: 30px;

            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;

            box-shadow: var(--shadow);
        }

        .tickets-card-heading {
            margin-bottom: 24px;
            padding-bottom: 20px;

            border-bottom: 1px solid var(--border);
        }

        .tickets-card-heading h3 {
            margin-bottom: 9px;
            color: var(--text-primary);
            font-size: 22px;
            font-weight: 800;
        }

        .tickets-card-heading p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.7;
        }

        .ticket-count {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            margin-top: 14px;
            padding: 10px 15px;

            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 8px;

            color: var(--text-secondary);
            font-size: 13px;
        }

        .ticket-count strong {
            color: var(--primary);
        }

        /* =====================================
           TICKET TABLE
        ===================================== */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .tickets-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .tickets-table thead {
            background: var(--surface-soft);
        }

        .tickets-table th {
            padding: 16px 15px;

            color: var(--text-secondary);
            text-align: left;

            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;

            border-bottom: 1px solid var(--border);
        }

        .tickets-table td {
            padding: 17px 15px;

            color: var(--text-primary);
            font-size: 13px;
            vertical-align: middle;

            border-bottom: 1px solid var(--border);
        }

        .tickets-table tbody tr:last-child td {
            border-bottom: none;
        }

        .tickets-table tbody tr {
            transition: background 0.2s ease;
        }

        .tickets-table tbody tr:hover {
            background: var(--surface-soft);
        }

        .ticket-number {
            color: var(--primary) !important;
            font-weight: 800;
            white-space: nowrap;
        }

        .ticket-title {
            min-width: 160px;
            font-weight: 700;
        }

        .created-date {
            min-width: 150px;
            color: var(--text-muted) !important;
            white-space: nowrap;
        }

        /* =====================================
           PRIORITY AND STATUS BADGES
        ===================================== */

        .ticket-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 7px 11px;
            border-radius: 30px;

            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .priority-low {
            background: #E2F7EB;
            color: #176B48;
        }

        .priority-medium {
            background: #FFF2D8;
            color: #98600B;
        }

        .priority-high {
            background: #FFE8EC;
            color: #A3334A;
        }

        .priority-critical {
            background: #F9DCE2;
            color: #8C1E39;
        }

        .status-open {
            background: #EEE7FF;
            color: #7546C2;
        }

        .status-assigned {
            background: #FFF2D8;
            color: #98600B;
        }

        .status-in-progress {
            background: #E8E9FF;
            color: #5148A4;
        }

        .status-resolved {
            background: #E2F7EB;
            color: #176B48;
        }

        .priority-default,
        .status-default {
            background: var(--surface-soft);
            color: var(--text-muted);
        }

        [data-theme="dark"] .priority-low,
        [data-theme="dark"] .status-resolved {
            background: #173B30;
            color: #A7E8C5;
        }

        [data-theme="dark"] .priority-medium,
        [data-theme="dark"] .status-assigned {
            background: #493920;
            color: #F6D18B;
        }

        [data-theme="dark"] .priority-high,
        [data-theme="dark"] .priority-critical {
            background: #45252D;
            color: #FFB2BC;
        }

        [data-theme="dark"] .status-open {
            background: #382A50;
            color: #D3B7FF;
        }

        [data-theme="dark"] .status-in-progress {
            background: #303052;
            color: #C2C0FF;
        }

        /* =====================================
           EMPTY STATE
        ===================================== */

        .empty-state {
            padding: 55px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 75px;
            height: 75px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 20px;

            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 20px;

            color: var(--primary);
            font-size: 34px;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 21px;
            font-weight: 800;
        }

        .empty-state p {
            margin-bottom: 23px;
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.7;
        }

        /* =====================================
           BUTTONS
        ===================================== */

        .submit-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 46px;
            padding: 0 23px;

            background: linear-gradient(135deg, #9562E8, #7546C2);
            border: 1px solid #7546C2;
            border-radius: 9px;

            color: #FFFFFF;
            text-decoration: none;

            font-size: 14px;
            font-weight: 800;

            transition: 0.2s ease;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(117, 70, 194, 0.22);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 46px;
            padding: 0 23px;

            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 9px;

            color: var(--primary);
            text-decoration: none;

            font-size: 14px;
            font-weight: 700;

            transition: 0.2s ease;
        }

        .back-button:hover {
            background: var(--surface-hover);
        }

        .page-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        /* =====================================
           FOOTER
        ===================================== */

        .dashboard-footer {
            margin-top: 45px;
            padding: 22px 10px;

            border-top: 1px solid var(--border);

            text-align: center;
            color: var(--text-muted);

            font-size: 13px;
            line-height: 1.8;
        }

        /* =====================================
           RESPONSIVE DESIGN
        ===================================== */

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

            .tickets-card {
                padding: 25px;
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
                min-height: 240px;
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

            .tickets-card {
                padding: 20px 16px;
            }

            .tickets-card-heading h3 {
                font-size: 20px;
            }

            .page-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .page-actions a {
                width: 100%;
            }

            .empty-state {
                padding: 40px 10px;
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

    <!-- =====================================
         REQUESTER NAVIGATION
    ===================================== -->

    <nav class="dashboard-nav">

        <div class="brand-navigation">

            <a class="dashboard-brand" href="dashboard.php">
                <span class="brand-icon">✦</span>
                <span class="brand-text">Smart<span>Desk</span></span>
            </a>

            <div class="page-links" aria-label="Requester navigation">

                <a href="dashboard.php">
                    Dashboard
                </a>

                <a href="create_ticket.php">
                    Submit Ticket
                </a>

                <a href="my_tickets.php"
                   class="active"
                   aria-current="page">
                    My Tickets
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


    <!-- =====================================
         MAIN CONTENT
    ===================================== -->

    <main class="dashboard-main">

        <!-- HERO BANNER -->

        <section class="welcome-card">

            <div class="welcome-content">

                <span class="welcome-label">
                    IT SERVICE DESK AND TICKET MANAGEMENT SYSTEM
                </span>

                <h1>My Support Tickets</h1>

                <p>
                    Keep track of your submitted IT support requests.
                    Review ticket details, check assigned technicians,
                    and monitor the progress of your concerns.
                </p>

                <span class="role-label">
                    Requester Workspace
                </span>

            </div>

        </section>


        <!-- TICKETS SECTION -->

        <section class="tickets-section">

            <div class="section-heading">

                <h2>My Submitted Tickets</h2>

                <p>
                    View and monitor all your submitted technical support requests.
                </p>

            </div>


            <section class="tickets-card">

                <div class="tickets-card-heading">

                    <h3>Ticket Records</h3>

                    <p>
                        Review your ticket information, priority, current status,
                        and assigned technician.
                    </p>

                    <div class="ticket-count">
                        Total Submitted Tickets:
                        <strong><?= $totalTickets ?></strong>
                    </div>

                </div>


                <?php if (!$tickets): ?>

                    <!-- EMPTY STATE -->

                    <div class="empty-state">

                        <div class="empty-icon">▣</div>

                        <h3>No Support Tickets Yet</h3>

                        <p>
                            You haven't submitted any support tickets yet.
                            Create your first ticket to request IT assistance.
                        </p>

                        <a href="create_ticket.php" class="submit-button">
                            + Submit Your First Ticket
                        </a>

                    </div>

                <?php else: ?>

                    <!-- TICKET TABLE -->

                    <div class="table-wrapper">

                        <table class="tickets-table">

                            <thead>
                                <tr>
                                    <th>Ticket Number</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Technician</th>
                                    <th>Created</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($tickets as $ticket): ?>

                                <?php

                                // PRIORITY BADGE
                                $priority = strtolower($ticket['priority']);

                                $priorityClass = in_array(
                                    $priority,
                                    ['low', 'medium', 'high', 'critical'],
                                    true
                                ) ? $priority : 'default';

                                // STATUS BADGE
                                $status = strtolower($ticket['status']);

                                $statusClass = in_array(
                                    $status,
                                    ['open', 'assigned', 'in_progress', 'resolved'],
                                    true
                                ) ? str_replace('_', '-', $status) : 'default';

                                ?>

                                <tr>

                                    <!-- TICKET NUMBER -->
                                    <td class="ticket-number">
                                        <?= htmlspecialchars(
                                            $ticket['ticket_number'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- TITLE -->
                                    <td class="ticket-title">
                                        <?= htmlspecialchars(
                                            $ticket['title'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- CATEGORY -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $ticket['category'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- PRIORITY -->
                                    <td>
                                        <span class="ticket-badge priority-<?= $priorityClass ?>">
                                            <?= htmlspecialchars(
                                                ucfirst($priority),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <!-- STATUS -->
                                    <td>
                                        <span class="ticket-badge status-<?= $statusClass ?>">
                                            <?= htmlspecialchars(
                                                ucwords(str_replace('_', ' ', $status)),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <!-- TECHNICIAN -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $ticket['technician_name'] ?? 'Unassigned',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <!-- CREATED DATE -->
                                    <td class="created-date">
                                        <?= htmlspecialchars(
                                            $ticket['created_at'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>


                <!-- PAGE ACTIONS -->

                
            </section>

        </section>


        <!-- FOOTER -->

        <footer class="dashboard-footer">

            SmartDesk © 2026. All rights reserved.

            <br>

            IT Service Desk and Ticket Management System

        </footer>

    </main>


    <!-- =====================================
         APPEARANCE FUNCTIONALITY
    ===================================== -->

    <script>
        const themeSelect = document.getElementById('themeSelect');

        const systemTheme = window.matchMedia(
            '(prefers-color-scheme: dark)'
        );

        const savedPreference =
            localStorage.getItem('smartdesk-theme') || 'system';

        themeSelect.value = savedPreference;

        function applyTheme(preference) {

            const activeTheme = preference === 'system'
                ? (systemTheme.matches ? 'dark' : 'light')
                : preference;

            document.documentElement.setAttribute(
                'data-theme',
                activeTheme
            );
        }

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
