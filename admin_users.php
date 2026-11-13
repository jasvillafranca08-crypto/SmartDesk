
<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();
requireRole(['admin']);

require_once __DIR__ . '/config/db.php';

// CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$message = '';
$messageType = '';

// HANDLE FORM SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        $message = 'Invalid request. Please refresh the page and try again.';
        $messageType = 'error';

    } else {

        $action = $_POST['action'] ?? '';

        // CREATE ADMIN OR TECHNICIAN
        if ($action === 'create_user') {

            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? '';

            if ($name === '' || $email === '' || $password === '') {

                $message = 'Please complete all required fields.';
                $messageType = 'error';

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $message = 'Please enter a valid email address.';
                $messageType = 'error';

            } elseif (strlen($password) < 8) {

                $message = 'Password must contain at least 8 characters.';
                $messageType = 'error';

            } elseif (!in_array($role, ['admin', 'technician'], true)) {

                $message = 'Invalid account role.';
                $messageType = 'error';

            } else {

                try {

                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $pdo->prepare("
                        INSERT INTO users (name, email, password_hash, role)
                        VALUES (:name, :email, :password_hash, :role)
                    ");

                    $stmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':password_hash' => $passwordHash,
                        ':role' => $role
                    ]);

                    $message = 'User account created successfully.';
                    $messageType = 'success';

                } catch (PDOException $e) {

                    if ($e->getCode() === '23000') {
                        $message = 'That email address is already registered.';
                    } else {
                        error_log($e->getMessage());
                        $message = 'Unable to create the account. Please try again.';
                    }

                    $messageType = 'error';
                }
            }
        }
    }
}

// SEARCH AND FILTER
$search = trim($_GET['search'] ?? '');
$filterRole = $_GET['role'] ?? 'all';

$allowedFilters = [
    'all',
    'admin',
    'technician',
    'requester'
];

if (!in_array($filterRole, $allowedFilters, true)) {
    $filterRole = 'all';
}

// GET USERS
$sql = "
    SELECT id, name, email, role, created_at
    FROM users
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {

    $sql .= "
        AND (
            name LIKE :search_name
            OR email LIKE :search_email
        )
    ";

    $params[':search_name'] = "%{$search}%";
    $params[':search_email'] = "%{$search}%";
}

if ($filterRole !== 'all') {
    $sql .= " AND role = :role";
    $params[':role'] = $filterRole;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ACCOUNT STATISTICS
$countStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_users,
        COALESCE(SUM(role = 'admin'), 0) AS total_admins,
        COALESCE(SUM(role = 'technician'), 0) AS total_technicians,
        COALESCE(SUM(role = 'requester'), 0) AS total_requesters
    FROM users
");

$counts = $countStmt->fetch(PDO::FETCH_ASSOC);

$totalUsers = (int) ($counts['total_users'] ?? 0);
$totalAdmins = (int) ($counts['total_admins'] ?? 0);
$totalTechnicians = (int) ($counts['total_technicians'] ?? 0);
$totalRequesters = (int) ($counts['total_requesters'] ?? 0);

// SAFE OUTPUT
function escapeOutput($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Management - SmartDesk</title>

    <style>
        /* THEME VARIABLES */

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

            --table-head: #F7F2FF;
            --row-hover: #FCFAFF;
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

            --table-head: #2B2438;
            --row-hover: #292235;
        }

        /* GLOBAL STYLES */

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
            -webkit-font-smoothing: antialiased;
            transition: background .25s ease, color .25s ease;
        }

        /* NAVIGATION - SAME AS DASHBOARD */

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

.theme-control .theme-select {
    padding: 5px 7px;
    border: none;
    outline: none;
    background: transparent;
    color: var(--text-primary);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.theme-control .theme-select option {
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

        .main-container {
            width: 100%;
            max-width: 1500px;
            margin: 0 auto;
            padding: 50px 5% 65px;
        }

        /* HERO BANNER - MATCHES OTHER ADMIN PAGES */

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
            max-width: 700px;
            color: #FFFFFF;
            font-size: 16px;
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
            margin-top: 40px;
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

        /* ALERTS */

        .alert {
            margin-bottom: 24px;
            padding: 16px 19px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.6;
            font-weight: 600;
        }

        .alert.success {
            background: #E7F7ED;
            color: #176B38;
            border: 1px solid #B9E6C8;
        }

        .alert.error {
            background: #FDECEC;
            color: #A52D35;
            border: 1px solid #F3C5C8;
        }

        [data-theme="dark"] .alert.success {
            background: #19382B;
            color: #8BE0B2;
            border-color: #315C45;
        }

        [data-theme="dark"] .alert.error {
            background: #45252D;
            color: #FFB2BC;
            border-color: #663641;
        }

        /* ACCOUNT STATISTICS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 38px;
        }

        .stat-card {
            min-height: 128px;
            padding: 25px 23px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .stat-card span {
            display: block;
            margin-bottom: 12px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 700;
        }

        .stat-card strong {
            color: var(--primary);
            font-size: 34px;
            font-weight: 800;
        }

        /* CONTENT GRID */

        .content-grid {
            display: grid;
            grid-template-columns: minmax(300px, 350px) minmax(0, 1fr);
            align-items: start;
            gap: 25px;
        }

        .panel {
            min-width: 0;
            padding: 28px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .panel-heading {
            margin-bottom: 24px;
        }

        .panel-heading h2 {
            margin-bottom: 8px;
            font-size: 23px;
            font-weight: 800;
            color: var(--text-primary);
        }

        .panel-heading p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.7;
        }

        /* CREATE USER FORM */

        .form-group {
            margin-bottom: 19px;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 700;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            min-height: 47px;
            padding: 12px 14px;
            background: var(--surface);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(149, 98, 232, 0.12);
        }

        .form-group select option {
            background: var(--surface);
            color: var(--text-primary);
        }

        .form-note {
            margin-top: 8px;
            color: var(--text-muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .primary-button {
            width: 100%;
            min-height: 48px;
            padding: 13px;
            background: linear-gradient(120deg, #A77BEA, #7546C2);
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s ease;
        }

        .primary-button:hover {
            opacity: .92;
            transform: translateY(-1px);
        }

        /* SEARCH AND FILTER */

        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
        }

        .toolbar input,
        .toolbar select {
            min-height: 44px;
            padding: 11px 13px;
            background: var(--surface);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
        }

        .toolbar input {
            flex: 1;
            min-width: 100px;
        }

        .toolbar select option {
            background: var(--surface);
            color: var(--text-primary);
        }

        .toolbar button {
            min-height: 44px;
            padding: 0 20px;
            background: var(--primary);
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
        }

        .toolbar button:hover {
            opacity: .9;
        }

        /* USERS TABLE */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
        }

        th {
            padding: 16px 14px;
            background: var(--table-head);
            color: var(--primary);
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        td {
            padding: 17px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
            font-size: 14px;
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

        .user-name {
            margin-bottom: 5px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 800;
        }

        .user-email {
            color: var(--text-secondary);
            font-size: 13px;
        }

        .role-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .role-admin {
            background: #EDE3FF;
            color: #6941A5;
        }

        .role-technician {
            background: #E1F0FF;
            color: #205A9B;
        }

        .role-requester {
            background: #E4F7EC;
            color: #237348;
        }

        [data-theme="dark"] .role-admin {
            background: #382B4C;
            color: #D3B7FF;
        }

        [data-theme="dark"] .role-technician {
            background: #243752;
            color: #A9C9FF;
        }

        [data-theme="dark"] .role-requester {
            background: #19382B;
            color: #8BE0B2;
        }

        .empty-state {
            padding: 35px 15px;
            text-align: center;
            color: var(--text-muted);
        }

        /* FOOTER */

        .page-footer {
            margin-top: 48px;
            padding: 24px 10px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            line-height: 1.8;
        }

        /* TABLET */

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

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        /* MOBILE */

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
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

.theme-control .theme-select {
    max-width: 105px;
    font-size: 12px;
}

.logout-link {
    padding: 10px 13px;
    font-size: 12px;
}


            .main-container {
                padding: 28px 16px 40px;
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

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                min-height: 115px;
                padding: 17px;
            }

            .stat-card span {
                font-size: 12px;
            }

            .stat-card strong {
                font-size: 26px;
            }

            .panel {
                padding: 20px;
            }

            .panel-heading h2 {
                font-size: 21px;
            }

            .toolbar {
                flex-wrap: wrap;
            }

            .toolbar input {
                flex-basis: 100%;
            }

            .page-footer {
                font-size: 12px;
            }
        }

        @media (max-width: 380px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
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

            <a href="dashboard.php" class="dashboard-brand">
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

                <a href="admin_tickets.php">
                    Manage Tickets
                </a>

                <a
                    href="admin_users.php"
                    class="active"
                    aria-current="page"
                >
                    User Management
                </a>

            </div>

        </div>

        <div class="nav-actions">

            <div class="theme-control">

                <label for="themeSelect">Appearance</label>

                <select
                    id="themeSelect"
                    class="theme-select"
                    aria-label="Choose appearance"
                >
                    <option value="light">☀ Light</option>
                    <option value="dark">☾ Dark</option>
                    <option value="system">⚙ System</option>
                </select>

            </div>

            <a href="logout.php" class="logout-link">
                Logout
            </a>

        </div>

    </nav>

    <!-- MAIN CONTENT -->

    <main class="main-container">

        <!-- HERO BANNER -->

        <section class="welcome-card">

            <div class="welcome-content">

                <span class="welcome-label">
                    IT Service Desk and Ticket Management System
                </span>

                <h1>User Management</h1>

                <p>
                    Manage SmartDesk accounts, create administrators
                    and technicians, and organize user roles and
                    system access in one workspace.
                </p>

                <span class="role-label">
                    Account Management
                </span>

            </div>

        </section>

        <!-- NOTIFICATIONS -->

        <?php if ($message !== ''): ?>

            <div class="alert <?= escapeOutput($messageType) ?>">
                <?= escapeOutput($message) ?>
            </div>

        <?php endif; ?>

        <!-- ACCOUNT STATISTICS -->

        <section>

            <div class="section-heading">
                <h2>Account Overview</h2>
                <p>
                    View the number of registered users across the system.
                </p>
            </div>

            <div class="stats-grid">

                <div class="stat-card">
                    <span>Total Users</span>
                    <strong><?= $totalUsers ?></strong>
                </div>

                <div class="stat-card">
                    <span>Administrators</span>
                    <strong><?= $totalAdmins ?></strong>
                </div>

                <div class="stat-card">
                    <span>Technicians</span>
                    <strong><?= $totalTechnicians ?></strong>
                </div>

                <div class="stat-card">
                    <span>Requesters</span>
                    <strong><?= $totalRequesters ?></strong>
                </div>

            </div>

        </section>

        <!-- USER MANAGEMENT CONTENT -->

        <section class="dashboard-section">

            <div class="section-heading">
                <h2>Manage User Accounts</h2>
                <p>
                    Create new accounts and review registered SmartDesk users.
                </p>
            </div>

            <div class="content-grid">

                <!-- CREATE USER -->

                <div class="panel">

                    <div class="panel-heading">

                        <h2>Create User Account</h2>

                        <p>
                            Add a new administrator or technician to SmartDesk.
                        </p>

                    </div>

                    <form method="POST" action="admin_users.php">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= escapeOutput($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="create_user"
                        >

                        <div class="form-group">

                            <label for="name">Full Name</label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                maxlength="150"
                                required
                                placeholder="Enter full name"
                            >

                        </div>

                        <div class="form-group">

                            <label for="email">Email Address</label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="255"
                                required
                                placeholder="example@email.com"
                            >

                        </div>

                        <div class="form-group">

                            <label for="password">Initial Password</label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="8"
                                required
                                placeholder="Enter initial password"
                            >

                            <p class="form-note">
                                Use at least 8 characters. Share initial
                                passwords securely.
                            </p>

                        </div>

                        <div class="form-group">

                            <label for="role">Account Role</label>

                            <select id="role" name="role" required>

                                <option value="">
                                    Select account role
                                </option>

                                <option value="admin">
                                    Administrator
                                </option>

                                <option value="technician">
                                    Technician
                                </option>

                            </select>

                        </div>

                        <button type="submit" class="primary-button">
                            + Create Account
                        </button>

                    </form>

                </div>

                <!-- REGISTERED USERS -->

                <div class="panel">

                    <div class="panel-heading">

                        <h2>Registered Users</h2>

                        <p>
                            View registered accounts and their assigned
                            system roles.
                        </p>

                    </div>

                    <!-- SEARCH AND FILTER -->

                    <form
                        method="GET"
                        action="admin_users.php"
                        class="toolbar"
                    >

                        <input
                            type="search"
                            name="search"
                            placeholder="Search name or email..."
                            value="<?= escapeOutput($search) ?>"
                        >

                        <select name="role">

                            <option
                                value="all"
                                <?= $filterRole === 'all' ? 'selected' : '' ?>
                            >
                                All Roles
                            </option>

                            <option
                                value="admin"
                                <?= $filterRole === 'admin' ? 'selected' : '' ?>
                            >
                                Admin
                            </option>

                            <option
                                value="technician"
                                <?= $filterRole === 'technician' ? 'selected' : '' ?>
                            >
                                Technician
                            </option>

                            <option
                                value="requester"
                                <?= $filterRole === 'requester' ? 'selected' : '' ?>
                            >
                                Requester
                            </option>

                        </select>

                        <button type="submit">
                            Search
                        </button>

                    </form>

                    <!-- USER TABLE -->

                    <div class="table-wrapper">

                        <table>

                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Registration Date</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php if (count($users) > 0): ?>

                                <?php foreach ($users as $user): ?>

                                    <?php
                                        $roleClass = 'role-' . $user['role'];
                                    ?>

                                    <tr>

                                        <td>

                                            <div class="user-name">
                                                <?= escapeOutput($user['name']) ?>
                                            </div>

                                            <div class="user-email">
                                                <?= escapeOutput($user['email']) ?>
                                            </div>

                                        </td>

                                        <td>

                                            <span class="role-badge <?= escapeOutput($roleClass) ?>">
                                                <?= escapeOutput(ucfirst($user['role'])) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <?= !empty($user['created_at'])
                                                ? escapeOutput(
                                                    date(
                                                        'M d, Y',
                                                        strtotime($user['created_at'])
                                                    )
                                                )
                                                : '—'
                                            ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="3" class="empty-state">
                                        No user accounts found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </section>

        <!-- FOOTER -->

        <footer class="page-footer">
            SmartDesk © 2026. All rights reserved.
            <br>
            IT Service Desk and Ticket Management System
        </footer>

    </main>

    <!-- THEME SWITCHER -->

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
