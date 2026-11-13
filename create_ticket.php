
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireRole(['requester']);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

$title = '';
$description = '';
$category = '';
$priority = '';

// PROCESS FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $priority = $_POST['priority'] ?? '';

    $categories = [
        'Hardware',
        'Software',
        'Network',
        'Account',
        'Other'
    ];

    $priorities = [
        'low',
        'medium',
        'high',
        'critical'
    ];

    // VALIDATION
    if (
        $title === '' ||
        strlen($title) > 150 ||
        $description === '' ||
        strlen($description) > 5000
    ) {
        $error = 'Enter a title and description within the allowed limits.';

    } elseif (
        !in_array($category, $categories, true) ||
        !in_array($priority, $priorities, true)
    ) {
        $error = 'Select a valid category and priority.';

    } else {

        try {
            $pdo->beginTransaction();

            // GENERATE UNIQUE TICKET NUMBER
            $ticketNumber = 'SD-' . strtoupper(
                bin2hex(random_bytes(6))
            );

            // INSERT NEW TICKET
            $stmt = $pdo->prepare(
                "INSERT INTO tickets
                 (ticket_number, requester_id, title, description,
                  category, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'open')"
            );

            $stmt->execute([
                $ticketNumber,
                $_SESSION['user_id'],
                $title,
                $description,
                $category,
                $priority
            ]);

            $ticketId = (int) $pdo->lastInsertId();

            // RECORD INITIAL TICKET HISTORY
            $history = $pdo->prepare(
                "INSERT INTO ticket_history
                 (ticket_id, changed_by, old_status, new_status, remarks)
                 VALUES (?, ?, NULL, 'open', ?)"
            );

            $history->execute([
                $ticketId,
                $_SESSION['user_id'],
                'Ticket created'
            ]);

            // SAVE DATABASE CHANGES
            $pdo->commit();

            // REGENERATE CSRF TOKEN
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $success = "Ticket $ticketNumber created successfully.";

            // CLEAR FORM VALUES
            $title = '';
            $description = '';
            $category = '';
            $priority = '';

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $error = 'Could not create the ticket. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Submit Ticket - SmartDesk</title>

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
           NAVIGATION - SAME AS DASHBOARD
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
           FORM SECTION
        ===================================== */

        .form-section {
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

        .form-card {
            width: 100%;
            padding: 35px;

            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;

            box-shadow: var(--shadow);
        }

        .form-card-heading {
            margin-bottom: 28px;
            padding-bottom: 22px;

            border-bottom: 1px solid var(--border);
        }

        .form-card-heading h2 {
            margin-bottom: 9px;
            color: var(--text-primary);
            font-size: 23px;
            font-weight: 800;
        }

        .form-card-heading p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.7;
        }

        .requester-name {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            margin-top: 15px;
            padding: 10px 15px;

            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 8px;

            color: var(--text-secondary);
            font-size: 13px;
        }

        .requester-name strong {
            color: var(--primary);
        }

        /* =====================================
           ALERT MESSAGES
        ===================================== */

        .alert {
            padding: 15px 18px;
            margin-bottom: 22px;

            border-radius: 9px;
            font-size: 14px;
            line-height: 1.7;
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

        .alert-success a {
            display: inline-block;
            margin-left: 8px;

            color: #176B48;
            font-weight: 800;
            text-decoration: underline;
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

        [data-theme="dark"] .alert-success a {
            color: #A7E8C5;
        }

        /* =====================================
           FORM INPUTS
        ===================================== */

        .ticket-form {
            width: 100%;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;

            margin-bottom: 25px;
        }

        .form-group label {
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 700;
        }

        .form-group label span {
            color: #D94A70;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 15px 16px;

            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 9px;

            color: var(--text-primary);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;

            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group input,
        .form-group select {
            min-height: 50px;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
            line-height: 1.7;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: var(--text-muted);
            opacity: 0.8;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(149, 98, 232, 0.13);
        }

        .form-group select option {
            background: var(--surface);
            color: var(--text-primary);
        }

        .form-group small {
            color: var(--text-muted);
            font-size: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        /* =====================================
           FORM BUTTONS
        ===================================== */

        .form-actions {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-top: 10px;
            padding-top: 24px;

            border-top: 1px solid var(--border);
        }

        .submit-button {
            min-height: 48px;
            padding: 0 25px;

            background: linear-gradient(135deg, #9562E8, #7546C2);
            border: 1px solid #7546C2;
            border-radius: 9px;

            color: #FFFFFF;
            font-size: 14px;
            font-weight: 800;

            cursor: pointer;
            transition: 0.2s ease;
        }

        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 18px rgba(117, 70, 194, 0.22);
        }

        .cancel-button {
            min-height: 48px;
            padding: 0 25px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            background: var(--surface-soft);
            border: 1px solid var(--border);
            border-radius: 9px;

            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;

            transition: 0.2s ease;
        }

        .cancel-button:hover {
            background: var(--surface-hover);
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

            .form-card {
                padding: 28px;
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

            .form-card {
                padding: 22px 18px;
            }

            .form-card-heading h2 {
                font-size: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .submit-button,
            .cancel-button {
                width: 100%;
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

                <a href="create_ticket.php"
                   class="active"
                   aria-current="page">
                    Submit Ticket
                </a>

                <a href="my_tickets.php">
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

                <h1>Submit an IT Support Ticket</h1>

                <p>
                    Tell us about the technical issue you're experiencing.
                    Provide the necessary details so our support team can
                    review your concern and assist you.
                </p>

                <span class="role-label">
                    Requester Workspace
                </span>

            </div>

        </section>

        <!-- TICKET FORM SECTION -->

        <section class="form-section">

            <div class="section-heading">

                <h2>Create a Support Request</h2>

                <p>
                    Complete the form below to submit your technical concern.
                </p>

            </div>

            <section class="form-card">

                <div class="form-card-heading">

                    <h2>Ticket Information</h2>

                    <p>
                        Please complete all required fields.
                    </p>

                    <div class="requester-name">
                        Requester:

                        <strong>
                            <?= htmlspecialchars(
                                $_SESSION['user_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>
                    </div>

                </div>

                <!-- ERROR MESSAGE -->

                <?php if ($error !== ''): ?>

                    <div class="alert alert-error">
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                <?php endif; ?>

                <!-- SUCCESS MESSAGE -->

                <?php if ($success !== ''): ?>

                    <div class="alert alert-success">

                        <?= htmlspecialchars(
                            $success,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        <a href="my_tickets.php">
                            View My Tickets
                        </a>

                    </div>

                <?php endif; ?>

                <!-- TICKET FORM -->

                <form method="POST" class="ticket-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <!-- ISSUE TITLE -->

                    <div class="form-group">

                        <label for="title">
                            Issue Title <span>*</span>
                        </label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            maxlength="150"
                            placeholder="Enter a short title describing your issue"
                            value="<?= htmlspecialchars(
                                $title,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            required
                        >

                        <small>
                            Maximum 150 characters.
                        </small>

                    </div>

                    <!-- DESCRIPTION -->

                    <div class="form-group">

                        <label for="description">
                            Description <span>*</span>
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            maxlength="5000"
                            rows="6"
                            placeholder="Describe the problem in detail. Include any error messages or relevant information."
                            required
                        ><?= htmlspecialchars(
                            $description,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>

                        <small>
                            Maximum 5,000 characters.
                        </small>

                    </div>

                    <!-- CATEGORY AND PRIORITY -->

                    <div class="form-row">

                        <!-- CATEGORY -->

                        <div class="form-group">

                            <label for="category">
                                Category <span>*</span>
                            </label>

                            <select
                                id="category"
                                name="category"
                                required
                            >

                                <option value="">
                                    Select category
                                </option>

                                <?php foreach (
                                    ['Hardware', 'Software', 'Network', 'Account', 'Other']
                                    as $option
                                ): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $option,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        <?= $category === $option ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            $option,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <!-- PRIORITY -->

                        <div class="form-group">

                            <label for="priority">
                                Priority <span>*</span>
                            </label>

                            <select
                                id="priority"
                                name="priority"
                                required
                            >

                                <option value="">
                                    Select priority
                                </option>

                                <?php foreach (
                                    ['low', 'medium', 'high', 'critical']
                                    as $option
                                ): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $option,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        <?= $priority === $option ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            ucfirst($option),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                    <!-- FORM ACTIONS -->

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="submit-button"
                        >
                            Submit Ticket
                        </button>

                        <a
                            href="my_tickets.php"
                            class="cancel-button"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

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
