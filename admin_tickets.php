
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['admin']);

// CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// HANDLE TICKET ASSIGNMENT
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
    $technicianId = filter_input(INPUT_POST, 'technician_id', FILTER_VALIDATE_INT);

    if (!$ticketId || !$technicianId) {

        $error = 'Please select a valid ticket and technician.';

    } else {

        try {
            $pdo->beginTransaction();

            // LOCK TICKET
            $stmt = $pdo->prepare(
                "SELECT id, status
                 FROM tickets
                 WHERE id = ?
                 FOR UPDATE"
            );

            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch();

            // VERIFY TECHNICIAN
            $stmt = $pdo->prepare(
                "SELECT id
                 FROM users
                 WHERE id = ? AND role = 'technician'"
            );

            $stmt->execute([$technicianId]);
            $technician = $stmt->fetch();

            if (!$ticket || !$technician) {
                throw new RuntimeException(
                    'Ticket or technician was not found.'
                );
            }

            // PREVENT REASSIGNMENT OF COMPLETED TICKETS
            if (in_array($ticket['status'], ['resolved', 'closed'], true)) {
                throw new RuntimeException(
                    'Resolved or closed tickets cannot be reassigned.'
                );
            }

            // UPDATE TICKET
            $stmt = $pdo->prepare(
                "UPDATE tickets
                 SET technician_id = ?, status = 'assigned'
                 WHERE id = ?"
            );

            $stmt->execute([$technicianId, $ticketId]);

            // RECORD TICKET HISTORY
            $stmt = $pdo->prepare(
                "INSERT INTO ticket_history
                 (ticket_id, changed_by, old_status, new_status, remarks)
                 VALUES (?, ?, ?, 'assigned', ?)"
            );

            $stmt->execute([
                $ticketId,
                $_SESSION['user_id'],
                $ticket['status'],
                'Ticket assigned to technician ID ' . $technicianId
            ]);

            $pdo->commit();

            // REGENERATE CSRF TOKEN
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: admin_tickets.php?assigned=1');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $error = 'Assignment failed. Please try again.';
            }
        }
    }
}

// GET TECHNICIANS
$technicians = $pdo->query(
    "SELECT id, name
     FROM users
     WHERE role = 'technician'
     ORDER BY name"
)->fetchAll();

// GET LATEST TICKETS
$tickets = $pdo->query(
    "SELECT
        t.id,
        t.ticket_number,
        t.title,
        t.priority,
        t.status,
        u.name AS requester_name,
        tech.name AS technician_name
     FROM tickets t
     JOIN users u ON u.id = t.requester_id
     LEFT JOIN users tech ON tech.id = t.technician_id
     ORDER BY t.created_at DESC
     LIMIT 200"
)->fetchAll();

// TICKET STATISTICS
$ticketStats = $pdo->query(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status IN ('open', 'pending')), 0) AS pending,
        COALESCE(SUM(status = 'in_progress'), 0) AS in_progress,
        COALESCE(SUM(status = 'resolved'), 0) AS resolved
     FROM tickets"
)->fetch();

// HTML ESCAPE FUNCTION
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Tickets - SmartDesk</title>

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

            --shadow: 0 7px 22px rgba(110,75,160,.07);
            --shadow-hover: 0 14px 30px rgba(137,89,214,.15);

            --table-head: #F7F2FF;
            --row-hover: #FCFAFF;

            --success-bg: #E8F7EF;
            --success-text: #18794E;

            --danger-bg: #FFF0F0;
            --danger-text: #B42335;
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

            --shadow: 0 7px 22px rgba(0,0,0,.18);
            --shadow-hover: 0 14px 30px rgba(0,0,0,.28);

            --table-head: #2B2438;
            --row-hover: #292235;

            --success-bg: #19382B;
            --success-text: #8BE0B2;

            --danger-bg: #45252D;
            --danger-text: #FFB2BC;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--page-bg);
            color: var(--text-primary);
            transition: background .25s ease, color .25s ease;
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
            color: white;
            border-radius: 15px;
            font-size: 31px;
            font-weight: 800;
            box-shadow: 0 5px 14px rgba(144,97,232,.25);
        }

        .brand-text {
            font-family: "Trebuchet MS", Arial, sans-serif;
            font-size: 32px;
            font-weight: 900;
            letter-spacing: -1.3px;
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
            transition: .2s ease;
        }

        .page-links a:hover,
        .page-links a.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
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
    padding: 50px 5% 65px;
}

        /* HERO BANNER */

        
.welcome-card {
    position: relative;
    overflow: hidden;
    min-height: 340px;
    padding: 50px 48px;
    display: flex;
    align-items: center;
    margin-bottom: 42px;
    background: linear-gradient(
        120deg,
        #C9AEF5 0%,
        #A77BEA 55%,
        #8959D6 100%
    );
    border-radius: 20px;
    color: white;
    box-shadow: 0 14px 35px rgba(137,89,214,.20);
}


        .welcome-card::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            right: -65px;
            top: -110px;
            border-radius: 50%;
            background: rgba(255,255,255,.12);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
        }

        .welcome-label {
            display: inline-block;
            margin-bottom: 16px;
            color: white;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .welcome-card h1 {
            margin-bottom: 13px;
            color: white;
            font-size: clamp(30px, 3.5vw, 42px);
            line-height: 1.2;
            font-weight: 800;
        }

        .welcome-card p {
            max-width: 700px;
            color: white;
            font-size: 15px;
            line-height: 1.8;
        }

        .role-label {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 18px;
            background: rgba(255,255,255,.20);
            border: 1px solid rgba(255,255,255,.35);
            border-radius: 30px;
            color: white;
            font-size: 13px;
            font-weight: 700;
        }

        /* SUMMARY CARDS */

       
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 38px;
}


        
.summary-card {
    min-height: 128px;
    padding: 25px 23px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 15px;
    box-shadow: var(--shadow);
    transition: .2s ease;
}

        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .summary-card p {
            margin-bottom: 12px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 700;
        }

        .summary-card h2 {
            color: var(--primary);
            font-size: 30px;
            font-weight: 800;
        }

        /* SECTION HEADINGS */

        .section-heading {
            margin-bottom: 22px;
        }

        .section-heading h2 {
            margin-bottom: 8px;
            font-size: 25px;
            font-weight: 800;
        }

        .section-heading p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        /* CONTENT PANEL */

        .content-panel {
            padding: 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }

        .panel-heading h2 {
            margin-bottom: 8px;
            font-size: 23px;
            font-weight: 800;
        }

        .panel-heading p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }

        .record-count {
            padding: 9px 13px;
            border-radius: 30px;
            background: var(--surface-soft);
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        /* ALERT MESSAGES */

        .message {
            margin-bottom: 18px;
            padding: 14px 17px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
        }

        .message-success {
            color: var(--success-text);
            background: var(--success-bg);
            border: 1px solid var(--border);
        }

        .message-error {
            color: var(--danger-text);
            background: var(--danger-bg);
            border: 1px solid var(--border);
        }

        /* TICKET TABLE */

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 11px;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--table-head);
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .45px;
            white-space: nowrap;
        }

        td {
            color: var(--text-secondary);
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr {
            background: var(--surface);
        }

        tbody tr:hover {
            background: var(--row-hover);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .ticket-number {
            color: var(--primary);
            font-weight: 800;
            white-space: nowrap;
        }

        .issue-title {
            min-width: 170px;
            color: var(--text-primary);
            font-weight: 700;
        }

        /* BADGES */

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 11px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .priority-low {
            color: #18794E;
            background: #E4F5EB;
        }

        .priority-medium {
            color: #6941A5;
            background: #EEE4FB;
        }

        .priority-high {
            color: #A34B13;
            background: #FFF0DF;
        }

        .priority-critical {
            color: #B42335;
            background: #FDE7EB;
        }

        .status-open,
        .status-pending {
            color: #6941A5;
            background: #EEE4FB;
        }

        .status-assigned {
            color: #245DA8;
            background: #E5EFFF;
        }

        .status-in-progress {
            color: #9A5B0A;
            background: #FFF1D8;
        }

        .status-resolved,
        .status-closed {
            color: #18794E;
            background: #E4F5EB;
        }

        [data-theme="dark"] .priority-low,
        [data-theme="dark"] .status-resolved,
        [data-theme="dark"] .status-closed {
            color: #8BE0B2;
            background: #19382B;
        }

        [data-theme="dark"] .priority-medium,
        [data-theme="dark"] .status-open,
        [data-theme="dark"] .status-pending {
            color: #D3B7FF;
            background: #382B4C;
        }

        [data-theme="dark"] .priority-high,
        [data-theme="dark"] .status-in-progress {
            color: #FFD18A;
            background: #49351F;
        }

        [data-theme="dark"] .priority-critical {
            color: #FFB2BC;
            background: #45252D;
        }

        [data-theme="dark"] .status-assigned {
            color: #A9C9FF;
            background: #243752;
        }

        /* ASSIGNMENT FORM */

        .assignment-form {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 230px;
        }

        .assignment-form select {
            width: 100%;
            min-width: 135px;
            padding: 10px 11px;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            background: var(--surface);
            color: var(--text-primary);
            font-size: 12px;
        }

        .assignment-form select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(149,98,232,.13);
        }

        .assign-button {
            padding: 10px 15px;
            border: 1px solid var(--primary);
            border-radius: 8px;
            background: linear-gradient(135deg, #A77BEA, #7546C2);
            color: white;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s ease;
        }

        .assign-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 13px rgba(117,70,194,.2);
        }

        .unavailable {
            color: var(--text-muted);
            font-size: 12px;
            font-style: italic;
        }

        .empty-state {
            padding: 35px 15px;
            color: var(--text-muted);
            text-align: center;
        }

        /* FOOTER */

        .dashboard-footer {
            margin-top: 42px;
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

        @media (max-width: 900px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-main {
                padding: 35px 4% 50px;
            }

            .content-panel {
                padding: 22px;
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
                padding: 10px 12px;
                font-size: 12px;
            }

            .dashboard-main {
                padding: 27px 16px 40px;
            }

            .welcome-card {
                min-height: 230px;
                padding: 30px 25px;
                border-radius: 15px;
            }

            .welcome-label {
                font-size: 11px;
                line-height: 1.6;
            }

            .welcome-card h1 {
                font-size: 29px;
            }

            .welcome-card p {
                font-size: 14px;
            }

            .summary-grid {
                gap: 11px;
            }

            .summary-card {
                padding: 17px;
            }

            .summary-card h2 {
                font-size: 25px;
            }

            .content-panel {
                padding: 17px;
            }

            .panel-heading h2 {
                font-size: 20px;
            }
        }

        @media (max-width: 380px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }
    </style>

    <!-- LOAD SAVED THEME BEFORE DISPLAY -->
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

                <a href="admin_dashboard.php">
                    Analytics Dashboard
                </a>

                <a
                    href="admin_tickets.php"
                    class="active"
                    aria-current="page"
                >
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

        <!-- HERO BANNER -->

        <section class="welcome-card">

            <div class="welcome-content">

                <span class="welcome-label">
                    IT Service Desk and Ticket Management System
                </span>

                <h1>Manage Support Tickets</h1>

                <p>
                    Review submitted support requests, assign technicians,
                    and monitor the progress of service tickets in one
                    organized workspace.
                </p>

                <span class="role-label">
                    Ticket Management
                </span>

            </div>

        </section>

        <!-- TICKET SUMMARY -->

        <section>

            <div class="section-heading">
                <h2>Ticket Overview</h2>
                <p>
                    View the current status of all submitted support tickets.
                </p>
            </div>

            <div class="summary-grid">

                <article class="summary-card">
                    <p>Total Tickets</p>
                    <h2><?= (int) ($ticketStats['total'] ?? 0) ?></h2>
                </article>

                <article class="summary-card">
                    <p>Pending Tickets</p>
                    <h2><?= (int) ($ticketStats['pending'] ?? 0) ?></h2>
                </article>

                <article class="summary-card">
                    <p>In Progress</p>
                    <h2><?= (int) ($ticketStats['in_progress'] ?? 0) ?></h2>
                </article>

                <article class="summary-card">
                    <p>Resolved</p>
                    <h2><?= (int) ($ticketStats['resolved'] ?? 0) ?></h2>
                </article>

            </div>

        </section>

        <!-- MESSAGES -->

        <?php if ($error !== ''): ?>

            <div class="message message-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>

        <?php if (isset($_GET['assigned'])): ?>

            <div class="message message-success">
                Ticket assignment saved successfully.
            </div>

        <?php endif; ?>

        <?php if (!$technicians): ?>

            <div class="message message-error">
                Create a technician account before assigning tickets.
            </div>

        <?php endif; ?>

        <!-- TICKET TABLE -->

        <section class="content-panel">

            <div class="panel-heading">

                <div>
                    <h2>All Support Tickets</h2>

                    <p>
                        Manage ticket assignments and review the latest
                        submitted requests.
                    </p>
                </div>

                <span class="record-count">
                    <?= count($tickets) ?> tickets displayed
                </span>

            </div>

            <div class="table-wrap">

                <table>

                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Issue</th>
                            <th>Requester</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Technician</th>
                            <th>Assignment</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (!$tickets): ?>

                        <tr>
                            <td colspan="7" class="empty-state">
                                No tickets found.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($tickets as $ticket): ?>

                            <?php
                                $statusClass = str_replace(
                                    '_',
                                    '-',
                                    $ticket['status']
                                );

                                $priorityClass = strtolower(
                                    $ticket['priority']
                                );
                            ?>

                            <tr>

                                <td class="ticket-number">
                                    <?= e($ticket['ticket_number']) ?>
                                </td>

                                <td class="issue-title">
                                    <?= e($ticket['title']) ?>
                                </td>

                                <td>
                                    <?= e($ticket['requester_name']) ?>
                                </td>

                                <td>
                                    <span class="badge priority-<?= e($priorityClass) ?>">
                                        <?= e(ucfirst($ticket['priority'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge status-<?= e($statusClass) ?>">
                                        <?= e(
                                            ucwords(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $ticket['status']
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(
                                        $ticket['technician_name'] ?? 'Unassigned'
                                    ) ?>
                                </td>

                                <td>

                                    <?php if (
                                        !in_array(
                                            $ticket['status'],
                                            ['resolved', 'closed'],
                                            true
                                        ) && $technicians
                                    ): ?>

                                        <form
                                            method="POST"
                                            class="assignment-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="ticket_id"
                                                value="<?= (int) $ticket['id'] ?>"
                                            >

                                            <select
                                                name="technician_id"
                                                required
                                                aria-label="Choose technician for ticket <?= e($ticket['ticket_number']) ?>"
                                            >

                                                <option value="">
                                                    Choose technician
                                                </option>

                                                <?php foreach ($technicians as $tech): ?>

                                                    <option
                                                        value="<?= (int) $tech['id'] ?>"
                                                    >
                                                        <?= e($tech['name']) ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>

                                            <button
                                                class="assign-button"
                                                type="submit"
                                            >
                                                Assign
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span class="unavailable">
                                            Not available
                                        </span>

                                    <?php endif; ?>

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

    <!-- APPEARANCE CONTROL -->

    <script>
        const themeSelect =
            document.getElementById('themeSelect');

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
