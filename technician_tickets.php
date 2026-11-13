
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['technician']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

/* =====================================
   UPDATE TICKET STATUS
===================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['status'] ?? '';

    if (
        !$ticketId ||
        !in_array($newStatus, ['in_progress', 'resolved'], true)
    ) {
        $error = 'Invalid ticket or status.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT id, status
                 FROM tickets
                 WHERE id = ? AND technician_id = ?
                 FOR UPDATE"
            );

            $stmt->execute([$ticketId, $_SESSION['user_id']]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                throw new RuntimeException(
                    'Ticket not found or not assigned to you.'
                );
            }

            $allowedTransitions = [
                'assigned' => ['in_progress'],
                'in_progress' => ['resolved']
            ];

            if (!in_array(
                $newStatus,
                $allowedTransitions[$ticket['status']] ?? [],
                true
            )) {
                throw new RuntimeException(
                    'That status transition is not allowed.'
                );
            }

            $stmt = $pdo->prepare(
                "UPDATE tickets
                 SET status = ?
                 WHERE id = ? AND technician_id = ?"
            );

            $stmt->execute([
                $newStatus,
                $ticketId,
                $_SESSION['user_id']
            ]);

            $stmt = $pdo->prepare(
                "INSERT INTO ticket_history
                 (ticket_id, changed_by, old_status, new_status, remarks)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $ticketId,
                $_SESSION['user_id'],
                $ticket['status'],
                $newStatus,
                'Technician updated ticket status'
            ]);

            $pdo->commit();

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: technician_tickets.php?updated=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $error = 'Status update failed. Please try again.';
            }
        }
    }
}

/* =====================================
   GET ASSIGNED TICKETS
===================================== */

$stmt = $pdo->prepare(
    "SELECT id, ticket_number, title, description,
            priority, status, created_at
     FROM tickets
     WHERE technician_id = ?
     ORDER BY created_at DESC
     LIMIT 200"
);

$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll();

/* =====================================
   TICKET OVERVIEW
===================================== */

$totalTickets = count($tickets);
$pendingTickets = 0;
$inProgressTickets = 0;
$resolvedTickets = 0;

foreach ($tickets as $ticket) {
    if ($ticket['status'] === 'assigned') {
        $pendingTickets++;
    } elseif ($ticket['status'] === 'in_progress') {
        $inProgressTickets++;
    } elseif ($ticket['status'] === 'resolved') {
        $resolvedTickets++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Assigned Tickets - SmartDesk</title>

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

        /* =====================================
           NAVIGATION - MATCH DASHBOARD
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
           MAIN LAYOUT - MATCH DASHBOARD
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

        /* =====================================
           SECTION HEADINGS
        ===================================== */

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

        /* =====================================
           OVERVIEW CARDS
        ===================================== */

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .overview-card {
            min-height: 150px;
            padding: 30px;

            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;

            color: var(--text-primary);
            box-shadow: var(--shadow);

            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .overview-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .overview-card p {
            margin-bottom: 15px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
        }

        .overview-card h3 {
            color: var(--primary);
            font-size: 36px;
            font-weight: 800;
        }

        /* =====================================
           TICKET TABLE
        ===================================== */

        .table-card {
            padding: 32px;

            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;

            box-shadow: var(--shadow);
        }

        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;

            margin-bottom: 22px;
        }

        .table-card-header h2 {
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 23px;
            font-weight: 800;
        }

        .table-card-header p {
            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.6;
        }

        .ticket-count {
            padding: 9px 14px;

            background: var(--surface-soft);
            border-radius: 20px;

            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        .ticket-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            color: var(--text-primary);
        }

        .ticket-table thead th {
            padding: 17px 14px;

            background: var(--surface-soft);
            color: var(--primary);

            border-bottom: 1px solid var(--border);

            text-align: left;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .ticket-table tbody td {
            padding: 20px 14px;

            border-bottom: 1px solid var(--border);

            color: var(--text-primary);
            font-size: 14px;
            vertical-align: middle;
        }

        .ticket-table tbody tr:last-child td {
            border-bottom: none;
        }

        .ticket-table tbody tr:hover {
            background: var(--surface-soft);
        }

        .ticket-number {
            color: var(--primary) !important;
            font-weight: 800;
            white-space: nowrap;
        }

        .issue-title {
            display: block;
            margin-bottom: 7px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 700;
        }

        .description {
            max-width: 300px;
            color: var(--text-secondary);
            font-size: 12px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        /* =====================================
           PRIORITY AND STATUS BADGES
        ===================================== */

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 7px 12px;
            border-radius: 20px;

            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .priority-low {
            background: #D9F5E8;
            color: #176B48;
        }

        .priority-medium {
            background: #E8D9FF;
            color: #6736A5;
        }

        .priority-high {
            background: #FFE4C2;
            color: #A45A12;
        }

        .priority-critical {
            background: #FFD8DD;
            color: #A52A3A;
        }

        .status-assigned {
            background: #E8D9FF;
            color: #6736A5;
        }

        .status-in_progress {
            background: #D8E9FF;
            color: #245EAA;
        }

        .status-resolved {
            background: #D9F5E8;
            color: #176B48;
        }

        .status-open {
            background: #FFF0CE;
            color: #93630A;
        }

        .badge-default {
            background: #E5E1EA;
            color: #51465E;
        }

        /* =====================================
           UPDATE FORM
        ===================================== */

        .update-form {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 200px;
        }

        .update-form select {
            min-width: 125px;
            padding: 11px 12px;

            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 8px;

            color: var(--text-primary);
            font-family: inherit;
            font-size: 12px;
            outline: none;
        }

        .update-form select:focus {
            border-color: var(--primary-light);
        }

        .update-form button {
            padding: 11px 15px;

            background: #9575CD;
            border: 1px solid #9575CD;
            border-radius: 8px;

            color: #FFFFFF;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;

            cursor: pointer;
            transition: 0.2s ease;
        }

        .update-form button:hover {
            background: #7546C2;
            border-color: #7546C2;
        }

        .no-action {
            color: var(--text-muted);
            font-size: 12px;
            font-style: italic;
        }

        /* =====================================
           ALERTS AND EMPTY STATE
        ===================================== */

        .alert {
            padding: 15px 18px;
            margin-bottom: 20px;
            border-radius: 9px;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert-error {
            background: #FFE8EC;
            border: 1px solid #F4BCC7;
            color: #9E3045;
        }

        .alert-success {
            background: #E2F7EB;
            border: 1px solid #B6E7C9;
            color: #176B48;
        }

        [data-theme="dark"] .alert-error {
            background: #45252D;
            border-color: #70404B;
            color: #FFB2BC;
        }

        [data-theme="dark"] .alert-success {
            background: #173B30;
            border-color: #286B50;
            color: #A7E8C5;
        }

        .empty-state {
            padding: 55px 20px;
            text-align: center;

            color: var(--text-secondary);
            font-size: 15px;
            line-height: 1.8;
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
           RESPONSIVE
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

            .overview-grid {
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

            .overview-grid {
                gap: 15px;
            }

            .overview-card {
                min-height: 125px;
                padding: 20px;
            }

            .overview-card h3 {
                font-size: 30px;
            }

            .table-card {
                padding: 23px 18px;
            }

            .table-card-header h2 {
                font-size: 20px;
            }

            .dashboard-footer {
                font-size: 12px;
            }
        }

        @media (max-width: 380px) {
            .overview-grid {
                grid-template-columns: 1fr;
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

    <!-- Apply saved appearance before rendering -->
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
         NAVIGATION
    ===================================== -->

    <nav class="dashboard-nav">

        <div class="brand-navigation">

            <a class="dashboard-brand" href="dashboard.php">
                <span class="brand-icon">✦</span>
                <span class="brand-text">Smart<span>Desk</span></span>
            </a>

            <div class="page-links" aria-label="Technician navigation">

                <a href="dashboard.php">
                    Dashboard
                </a>

                <a href="technician_tickets.php"
                   class="active"
                   aria-current="page">
                    Assigned Tickets
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
                    IT Service Desk and Ticket Management System
                </span>

                <h1>My Assigned Tickets</h1>

                <p>
                    Review your assigned support requests, monitor ticket
                    progress, and keep every task moving forward.
                </p>

                <span class="role-label">
                    Technician Workspace
                </span>

            </div>

        </section>

        <!-- TICKET OVERVIEW -->

        <section class="dashboard-section">

            <div class="section-heading">

                <h2>Ticket Overview</h2>

                <p>
                    View the current status of your assigned support tickets.
                </p>

            </div>

            <div class="overview-grid">

                <div class="overview-card">
                    <p>Total Assigned Tickets</p>
                    <h3><?= $totalTickets ?></h3>
                </div>

                <div class="overview-card">
                    <p>Pending Tickets</p>
                    <h3><?= $pendingTickets ?></h3>
                </div>

                <div class="overview-card">
                    <p>In Progress</p>
                    <h3><?= $inProgressTickets ?></h3>
                </div>

                <div class="overview-card">
                    <p>Resolved Tickets</p>
                    <h3><?= $resolvedTickets ?></h3>
                </div>

            </div>

        </section>

        <!-- ASSIGNED TICKET TABLE -->

        <section class="dashboard-section">

            <div class="table-card">

                <div class="table-card-header">

                    <div>
                        <h2>All Assigned Tickets</h2>

                        <p>
                            Review your assigned requests and update their progress.
                        </p>
                    </div>

                    <span class="ticket-count">
                        <?= $totalTickets ?> tickets displayed
                    </span>

                </div>

                <?php if ($error !== ''): ?>

                    <div class="alert alert-error">
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                <?php endif; ?>

                <?php if (isset($_GET['updated'])): ?>

                    <div class="alert alert-success">
                        Ticket status updated successfully.
                    </div>

                <?php endif; ?>

                <?php if (!$tickets): ?>

                    <div class="empty-state">
                        No tickets are currently assigned to you.
                    </div>

                <?php else: ?>

                    <div class="table-scroll">

                        <table class="ticket-table">

                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Issue</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Update Status</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($tickets as $ticket): ?>

                                <?php
                                    $nextStatuses = [
                                        'assigned' => ['in_progress'],
                                        'in_progress' => ['resolved']
                                    ];

                                    $options = $nextStatuses[$ticket['status']] ?? [];

                                    $priority = strtolower($ticket['priority']);
                                    $status = strtolower($ticket['status']);

                                    $priorityClass = in_array(
                                        $priority,
                                        ['low', 'medium', 'high', 'critical'],
                                        true
                                    ) ? $priority : 'default';

                                    $statusClass = in_array(
                                        $status,
                                        ['open', 'assigned', 'in_progress', 'resolved'],
                                        true
                                    ) ? $status : 'default';
                                ?>

                                <tr>

                                    <td class="ticket-number">
                                        <?= htmlspecialchars(
                                            $ticket['ticket_number'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>

                                        <strong class="issue-title">
                                            <?= htmlspecialchars(
                                                $ticket['title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <?php if (!empty($ticket['description'])): ?>

                                            <div class="description">
                                                <?= nl2br(htmlspecialchars(
                                                    $ticket['description'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )) ?>
                                            </div>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <span class="badge priority-<?= $priorityClass ?>">
                                            <?= htmlspecialchars(
                                                ucfirst($priority),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge status-<?= $statusClass ?>">
                                            <?= htmlspecialchars(
                                                ucwords(str_replace('_', ' ', $status)),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>
                                    </td>

                                    <td>

                                        <?php if ($options): ?>

                                            <form method="POST" class="update-form">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= htmlspecialchars(
                                                        $_SESSION['csrf_token'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="ticket_id"
                                                    value="<?= (int) $ticket['id'] ?>"
                                                >

                                                <select name="status" required>

                                                    <?php foreach ($options as $option): ?>

                                                        <option value="<?= htmlspecialchars(
                                                            $option,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>">
                                                            <?= htmlspecialchars(
                                                                ucwords(str_replace('_', ' ', $option)),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>

                                                <button type="submit">
                                                    Update
                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span class="no-action">
                                                No further action
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

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
