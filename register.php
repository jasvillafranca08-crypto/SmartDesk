
<?php
session_start();

require_once __DIR__ . '/config/db.php';

$error = '';
$success = '';

$name = '';
$email = '';

// Generate CSRF token
if (empty($_SESSION['register_csrf_token'])) {
    $_SESSION['register_csrf_token'] = bin2hex(random_bytes(32));
}

// PROCESS REGISTRATION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['register_csrf_token'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request token. Please refresh the page.');
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // VALIDATION
    if (
        $name === '' ||
        strlen($name) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        $error = 'Enter a valid name and email address.';

    } elseif (!is_string($password) || strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';

    } else {

        try {

            // HASH PASSWORD
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // PUBLIC REGISTRATION ALWAYS CREATES REQUESTER
            $stmt = $pdo->prepare(
                "INSERT INTO users
                 (name, email, password_hash, role)
                 VALUES (?, ?, ?, 'requester')"
            );

            $stmt->execute([
                $name,
                $email,
                $hash
            ]);

            // REGENERATE CSRF TOKEN
            $_SESSION['register_csrf_token'] = bin2hex(random_bytes(32));

            $success = 'Registration successful! You can now log in.';

            // CLEAR FORM VALUES
            $name = '';
            $email = '';

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {
                $error = 'That email address is already registered.';
            } else {
                error_log($e->getMessage());
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register - SmartDesk</title>

    <style>

        /* =====================================
           SMARTDESK FULL-SCREEN REGISTER DESIGN
           MATCHES LOGIN PAGE
        ===================================== */

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            min-height: 100%;
        }

        body.register-page {
            min-height: 100vh;
            width: 100%;
            padding: 30px;

            font-family: Arial, Helvetica, sans-serif;

            background: #F8F6FC;
            color: #302B3D;

            display: flex;
            align-items: center;
            justify-content: center;

            transition: background .2s ease, color .2s ease;
        }

        /* =====================================
           MAIN CONTAINER
        ===================================== */

        .register-container {
            width: 100%;
            max-width: 1500px;
            margin: auto;
        }

        /* =====================================
           MAIN REGISTER CARD
        ===================================== */

        .register-card {
            width: 100%;
            min-height: 650px;

            display: grid;
            grid-template-columns: 1fr 1fr;

            background: #FFFFFF;
            border: 1px solid #E4D9F2;
            border-radius: 20px;
            overflow: hidden;

            box-shadow: 0 15px 45px rgba(70, 45, 110, 0.10);
        }

        /* =====================================
           LEFT BRANDING PANEL
        ===================================== */

        .register-brand {
            padding: 75px 70px;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            background: linear-gradient(
                145deg,
                #B99BDD 0%,
                #9575CD 55%,
                #7E57C2 100%
            );

            color: #FFFFFF;
        }

       .login-logo {
    width: 65px;
    height: 65px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: linear-gradient(
        135deg,
        #B98AF4,
        #8B50D6
    );

    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 15px;

    color: #FFFFFF;
    font-size: 38px;
    font-weight: 700;

    box-shadow: 0 4px 12px rgba(80, 40, 130, 0.18);
}

        .brand-top h1 {
            font-size: 40px;
            margin-top: 22px;
            margin-bottom: 10px;
            color: #FFFFFF;
        }

        .brand-tagline {
            font-size: 15px;
            line-height: 1.7;
            color: #FFFFFF;
        }

        .brand-message {
            margin: 35px 0;
        }

        .brand-message h2 {
            font-size: 32px;
            margin-bottom: 20px;
            color: #FFFFFF;
        }

        .brand-message p {
            max-width: 500px;
            font-size: 17px;
            line-height: 1.9;
            color: #FFFFFF;
        }

        .brand-bottom {
            max-width: 400px;
            font-size: 14px;
            line-height: 1.8;
            color: #FFFFFF;
        }

        /* =====================================
           RIGHT REGISTRATION PANEL
        ===================================== */

        .register-form-panel {
            padding: 55px 85px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #FFFFFF;
        }

        .register-form-content {
            width: 100%;
            max-width: 500px;
        }

        .register-heading {
            margin-bottom: 25px;
        }

        .register-heading h2 {
            font-size: 38px;
            margin-bottom: 13px;
            color: #302B3D;
        }

        .register-heading p {
            font-size: 16px;
            line-height: 1.7;
            color: #777087;
        }

        /* =====================================
           ERROR AND SUCCESS MESSAGES
        ===================================== */

        .register-error,
        .register-success {
            padding: 14px 16px;
            margin-bottom: 20px;

            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }

        .register-error {
            background: #FFF0F1;
            color: #B42336;
            border: 1px solid #F5C6CC;
        }

        .register-success {
            background: #E8F7EF;
            color: #18794E;
            border: 1px solid #B7E4C7;
        }

        /* =====================================
           FORM INPUTS
        ===================================== */

        .register-form-group {
            margin-bottom: 17px;
        }

        .register-form-group label {
            display: block;
            margin-bottom: 9px;

            color: #302B3D;
            font-size: 16px;
            font-weight: 600;
        }

        .register-form-group input {
            display: block;
            width: 100%;
            height: 58px;

            padding: 14px 17px;

            background: #F8F6FC;
            border: 1px solid #D9C9EE;
            border-radius: 9px;

            color: #302B3D;
            font-family: inherit;
            font-size: 16px;

            outline: none;
            transition: 0.2s ease;
        }

        .register-form-group input:focus {
            background: #FFFFFF;
            border-color: #9575CD;
            box-shadow: 0 0 0 3px rgba(149, 117, 205, 0.13);
        }

        .register-form-group small {
            display: block;
            margin-top: 7px;
            color: #777087;
            font-size: 12px;
        }

        /* =====================================
           REGISTER BUTTON
        ===================================== */

        .register-button {
            display: block;
            width: 100%;
            height: 62px;

            margin-top: 10px;

            background: #9575CD;
            border: none;
            border-radius: 9px;

            color: #FFFFFF;
            font-family: inherit;
            font-size: 17px;
            font-weight: 700;

            cursor: pointer;
            transition: 0.2s ease;
        }

        .register-button:hover {
            background: #7E57C2;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(126, 87, 194, 0.20);
        }

        /* =====================================
           LOGIN LINK
        ===================================== */

        .register-footer {
            margin-top: 25px;
            text-align: center;
        }

        .register-footer p {
            color: #777087;
            font-size: 15px;
        }

        .register-footer a {
            color: #7E57C2;
            font-weight: 700;

            text-decoration: underline;
            text-decoration-color: #9562E8;
            text-decoration-thickness: 1.5px;
            text-underline-offset: 4px;
        }

        .register-footer a:hover {
            color: #9562E8;
        }

        .register-login-button {
            display: block;
            width: 100%;
            height: 62px;

            margin-top: 15px;
            padding: 20px;

            background: #9575CD;
            border-radius: 9px;

            color: #FFFFFF;
            text-align: center;
            text-decoration: none;
            font-size: 17px;
            font-weight: 700;
        }

        .register-login-button:hover {
            background: #7E57C2;
        }

        /* =====================================
           COPYRIGHT
        ===================================== */

        .register-copyright {
            margin-top: 18px;
            text-align: center;

            color: #9186A3;
            font-size: 13px;
        }

        /* =====================================
           TABLET RESPONSIVENESS
        ===================================== */

        @media (max-width: 900px) {

            body.register-page {
                padding: 20px;
            }

            .register-card {
                min-height: 600px;
            }

            .register-brand {
                padding: 45px 35px;
            }

            .register-form-panel {
                padding: 35px;
            }

            .brand-top h1 {
                font-size: 32px;
            }

            .brand-message h2 {
                font-size: 26px;
            }

            .register-heading h2 {
                font-size: 30px;
            }

            .register-form-group input {
                height: 52px;
            }

            .register-button {
                height: 56px;
            }
        }

        /* =====================================
           MOBILE RESPONSIVENESS
        ===================================== */

        @media (max-width: 650px) {

            body.register-page {
                padding: 12px;
                padding-top: 65px;
            }

            .register-card {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .register-brand {
                padding: 35px 28px;
                gap: 25px;
            }

            .brand-message {
                margin: 10px 0;
            }

            .brand-message p {
                font-size: 15px;
            }

            .brand-bottom {
                font-size: 13px;
            }

            .register-form-panel {
                padding: 40px 28px;
            }

            .register-heading h2 {
                font-size: 28px;
            }

            .register-copyright {
                font-size: 11px;
            }
        }

        /* =====================================
           APPEARANCE SELECTOR
        ===================================== */

        .appearance-control {
            position: fixed;
            top: 24px;
            right: 28px;
            z-index: 1000;

            display: flex;
            align-items: center;
            gap: 9px;

            padding: 7px 11px;

            background: #F7F2FF;
            border: 1px solid #E9DDF9;
            border-radius: 9px;
        }

        .appearance-control label {
            color: #5E536D;
            font-size: 13px;
            font-weight: 700;
        }

        .appearance-control select {
            padding: 5px 7px;
            border: none;
            outline: none;
            background: transparent;
            color: #7546C2;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        .appearance-control select option {
            background: #FFFFFF;
            color: #302344;
        }

        /* =====================================
           DARK MODE
        ===================================== */

        body.dark-mode {
            background: #171421 !important;
            color: #F4EDFF;
        }

        body.dark-mode .register-card {
            background: #211C2D;
            border-color: #413450;
            box-shadow: 0 15px 45px rgba(0,0,0,.25);
        }

        body.dark-mode .register-form-panel {
            background: #211C2D;
        }

        body.dark-mode .register-heading h2,
        body.dark-mode .register-form-group label {
            color: #F4EDFF;
        }

        body.dark-mode .register-heading p,
        body.dark-mode .register-footer p {
            color: #C4B7D5;
        }

        body.dark-mode .register-form-group input {
            background: #2B2438;
            border-color: #514362;
            color: #F4EDFF;
        }

        body.dark-mode .register-form-group input::placeholder {
            color: #A99BB9;
        }

        body.dark-mode .register-form-group input:focus {
            background: #30283D;
            border-color: #B58AEF;
        }

        body.dark-mode .register-form-group small {
            color: #B4A6C8;
        }

        body.dark-mode .register-footer a {
            color: #D3B7FF;
            text-decoration: underline;
            text-decoration-color: #9562E8;
            text-decoration-thickness: 2px;
            text-underline-offset: 4px;
        }

        body.dark-mode .register-footer a:hover {
            color: #FFFFFF;
        }

        body.dark-mode .register-copyright {
            color: #A99BB9;
        }

        body.dark-mode .appearance-control {
            background: #2B2438;
            border-color: #413450;
        }

        body.dark-mode .appearance-control label {
            color: #D0C4E0;
        }

        body.dark-mode .appearance-control select {
            color: #D3B7FF;
        }

        body.dark-mode .appearance-control select option {
            background: #211C2D;
            color: #F4EDFF;
        }

        body.dark-mode .register-success {
            background: #19382B;
            color: #8BE0B2;
            border-color: #315B43;
        }

        body.dark-mode .register-error {
            background: #45252D;
            color: #FFB2BC;
            border-color: #70404B;
        }

        /* =====================================
           RESPONSIVE APPEARANCE CONTROL
        ===================================== */

        @media (max-width: 650px) {

            .appearance-control {
                top: 12px;
                right: 12px;
                padding: 6px 9px;
            }

            .appearance-control label,
            .appearance-control select {
                font-size: 12px;
            }
        }

    </style>
</head>

<body class="register-page">

    <!-- APPEARANCE SELECTOR -->

    <div class="appearance-control">

        <label for="themeSelect">Appearance</label>

        <select id="themeSelect">
            <option value="light">☀ Light</option>
            <option value="dark">☾ Dark</option>
            <option value="system">⚙ System</option>
        </select>

    </div>

    <!-- MAIN REGISTRATION CONTAINER -->

    <main class="register-container">

        <section class="register-card">

            <!-- LEFT BRANDING PANEL -->

            <div class="register-brand">

                <div class="brand-top">

                    <div class="login-logo">✦</div>

                    <h1>SmartDesk</h1>

                    <p class="brand-tagline">
                        IT Service Desk and Ticket Management System
                    </p>

                </div>

                <div class="brand-message">

                    <h2>Join SmartDesk Today!</h2>

                    <p>
                        Create your account and become part of a
                        centralized platform for submitting IT support
                        requests, managing service tickets, and monitoring
                        technical assistance from submission to resolution.
                    </p>

                </div>

                <div class="brand-bottom">
                    Organized Support. Efficient Service. Better Management.
                </div>

            </div>

            <!-- RIGHT REGISTRATION PANEL -->

            <div class="register-form-panel">

                <div class="register-form-content">

                    <div class="register-heading">

                        <h2>Create Your Account</h2>

                        <p>
                            Register to access your SmartDesk account
                            and manage your IT support tickets.
                        </p>

                    </div>

                    <!-- ERROR MESSAGE -->

                    <?php if ($error !== ''): ?>

                        <div class="register-error">
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                    <?php endif; ?>

                    <!-- SUCCESS MESSAGE -->

                    <?php if ($success !== ''): ?>

                        <div class="register-success">
                            <?= htmlspecialchars(
                                $success,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>

                        <a href="login.php" class="register-login-button">
                            Go to Login
                        </a>

                    <?php else: ?>

                        <!-- REGISTRATION FORM -->

                        <form method="POST" action="">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars(
                                    $_SESSION['register_csrf_token'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                            <div class="register-form-group">

                                <label for="name">Full Name</label>

                                <input
                                    id="name"
                                    type="text"
                                    name="name"
                                    maxlength="100"
                                    value="<?= htmlspecialchars(
                                        $name,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    placeholder="Enter your full name"
                                    autocomplete="name"
                                    required
                                >

                            </div>

                            <div class="register-form-group">

                                <label for="email">Email Address</label>

                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    maxlength="150"
                                    value="<?= htmlspecialchars(
                                        $email,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    placeholder="Enter your email address"
                                    autocomplete="email"
                                    required
                                >

                            </div>

                            <div class="register-form-group">

                                <label for="password">Password</label>

                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    minlength="8"
                                    placeholder="Create a password"
                                    autocomplete="new-password"
                                    required
                                >

                                <small>
                                    Password must contain at least
                                    8 characters.
                                </small>

                            </div>

                            <button
                                type="submit"
                                class="register-button"
                            >
                                Register
                            </button>

                        </form>

                    <?php endif; ?>

                    <!-- LOGIN LINK -->

                    <div class="register-footer">

                        <p>
                            Already have an account?
                            <a href="login.php">Sign In</a>
                        </p>

                    </div>

                </div>

            </div>

        </section>

        <footer class="register-copyright">
            SmartDesk © 2026. All rights reserved.
        </footer>

    </main>

    <!-- APPEARANCE FUNCTIONALITY -->

    <script>

        const themeSelect = document.getElementById('themeSelect');

        const systemTheme = window.matchMedia(
            '(prefers-color-scheme: dark)'
        );

        function applyTheme(preference) {

            const isDark = preference === 'dark' ||
                (preference === 'system' && systemTheme.matches);

            document.body.classList.toggle('dark-mode', isDark);
        }

        const savedPreference =
            localStorage.getItem('smartdesk-theme') || 'system';

        themeSelect.value = savedPreference;

        applyTheme(savedPreference);

        themeSelect.addEventListener('change', function () {

            const preference = this.value;

            localStorage.setItem('smartdesk-theme', preference);

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
