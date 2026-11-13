
<?php
session_start();

require_once __DIR__ . '/config/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT id, name, email, password_hash, role
         FROM users
         WHERE email = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmartDesk</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            min-height: 100%;
        }

        body.login-page {
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

        /* MAIN CONTAINER */
        .login-container {
            width: 100%;
            max-width: 1500px;
            margin: auto;
        }

        /* MAIN CARD - SAME AS REGISTRATION */
        .login-card {
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

        /* LEFT BRANDING PANEL */
        .login-brand {
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

        /* RIGHT LOGIN PANEL - SAME AS REGISTRATION */
        .login-form-panel {
            padding: 55px 85px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FFFFFF;
        }

        .login-form-content {
            width: 100%;
            max-width: 500px;
        }

        .login-heading {
            margin-bottom: 25px;
        }

        .login-heading h2 {
            font-size: 38px;
            margin-bottom: 13px;
            color: #302B3D;
        }

        .login-heading p {
            font-size: 16px;
            line-height: 1.7;
            color: #777087;
        }

        /* ERROR MESSAGE */
        .login-error {
            padding: 14px 16px;
            margin-bottom: 20px;
            background: #FFF0F1;
            color: #B42336;
            border: 1px solid #F5C6CC;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }

        /* FORM INPUTS */
        .login-form-group {
            margin-bottom: 17px;
        }

        .login-form-group label {
            display: block;
            margin-bottom: 9px;
            color: #302B3D;
            font-size: 16px;
            font-weight: 600;
        }

        .login-form-group input {
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

        .login-form-group input:focus {
            background: #FFFFFF;
            border-color: #9575CD;
            box-shadow: 0 0 0 3px rgba(149, 117, 205, 0.13);
        }

        /* SIGN IN BUTTON */
        .login-button {
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

        .login-button:hover {
            background: #7E57C2;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(126, 87, 194, 0.20);
        }

        /* CREATE ACCOUNT LINK */
        .login-footer {
            margin-top: 25px;
            text-align: center;
        }

        .login-footer p {
            color: #777087;
            font-size: 15px;
        }

        .login-footer a,
        .login-footer a:link,
        .login-footer a:visited {
            color: #7E57C2 !important;
            font-weight: 700;
            text-decoration: underline !important;
            text-decoration-color: #9562E8 !important;
            text-decoration-style: solid !important;
            text-decoration-thickness: 2px !important;
            text-underline-offset: 4px;
        }

        .login-footer a:hover {
            color: #9562E8 !important;
        }

        /* COPYRIGHT */
        .login-copyright {
            margin-top: 18px;
            text-align: center;
            color: #9186A3;
            font-size: 13px;
        }

        /* TABLET */
        @media (max-width: 900px) {
            body.login-page {
                padding: 20px;
            }

            .login-card {
                min-height: 600px;
            }

            .login-brand {
                padding: 45px 35px;
            }

            .login-form-panel {
                padding: 35px;
            }

            .brand-top h1 {
                font-size: 32px;
            }

            .brand-message h2 {
                font-size: 26px;
            }

            .login-heading h2 {
                font-size: 30px;
            }

            .login-form-group input {
                height: 52px;
            }

            .login-button {
                height: 56px;
            }
        }

        /* MOBILE */
        @media (max-width: 650px) {
            body.login-page {
                padding: 12px;
                padding-top: 65px;
            }

            .login-card {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .login-brand {
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

            .login-form-panel {
                padding: 40px 28px;
            }

            .login-heading h2 {
                font-size: 28px;
            }

            .login-copyright {
                font-size: 11px;
            }
        }

        /* APPEARANCE SELECTOR */
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

        /* DARK MODE */
        body.dark-mode {
            background: #171421 !important;
            color: #F4EDFF;
        }

        body.dark-mode .login-card {
            background: #211C2D;
            border-color: #413450;
            box-shadow: 0 15px 45px rgba(0,0,0,.25);
        }

        body.dark-mode .login-form-panel {
            background: #211C2D;
        }

        body.dark-mode .login-heading h2,
        body.dark-mode .login-form-group label {
            color: #F4EDFF;
        }

        body.dark-mode .login-heading p,
        body.dark-mode .login-footer p {
            color: #C4B7D5;
        }

        body.dark-mode .login-form-group input {
            background: #2B2438;
            border-color: #514362;
            color: #F4EDFF;
        }

        body.dark-mode .login-form-group input::placeholder {
            color: #A99BB9;
        }

        body.dark-mode .login-form-group input:focus {
            background: #30283D;
            border-color: #B58AEF;
        }

        body.dark-mode .login-footer a,
        body.dark-mode .login-footer a:link,
        body.dark-mode .login-footer a:visited {
            color: #D3B7FF !important;
            font-weight: 800;
            text-decoration: underline !important;
            text-decoration-color: #9562E8 !important;
            text-decoration-style: solid !important;
            text-decoration-thickness: 2px !important;
            text-underline-offset: 4px;
        }

        body.dark-mode .login-footer a:hover {
            color: #FFFFFF !important;
        }

        body.dark-mode .login-copyright {
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

        body.dark-mode .login-error {
            background: #45252D;
            color: #FFB2BC;
            border-color: #70404B;
        }

        /* RESPONSIVE APPEARANCE */
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

<body class="login-page">

    <!-- APPEARANCE SELECTOR -->
    <div class="appearance-control">
        <label for="themeSelect">Appearance</label>

        <select id="themeSelect">
            <option value="light">☀ Light</option>
            <option value="dark">☾ Dark</option>
            <option value="system">⚙ System</option>
        </select>
    </div>

    <!-- MAIN LOGIN CONTAINER -->
    <main class="login-container">

        <section class="login-card">

            <!-- LEFT BRANDING PANEL -->
            <div class="login-brand">

                <div class="brand-top">
                    <div class="login-logo">✦</div>

                    <h1>SmartDesk</h1>

                    <p class="brand-tagline">
                        IT Service Desk and Ticket Management System
                    </p>
                </div>

                <div class="brand-message">
                    <h2>Welcome to SmartDesk</h2>

                    <p>
                        Your centralized platform for submitting IT support
                        requests, managing service tickets, and monitoring
                        technical assistance from submission to resolution.
                    </p>
                </div>

                <div class="brand-bottom">
                    Organized Support. Efficient Service. Better Management.
                </div>

            </div>

            <!-- RIGHT LOGIN PANEL -->
            <div class="login-form-panel">

                <div class="login-form-content">

                    <div class="login-heading">
                        <h2>Welcome to SmartDesk!</h2>

                        <p>
                            Sign in to access your SmartDesk account.
                        </p>
                    </div>

                    <!-- ERROR MESSAGE -->
                    <?php if ($error !== ''): ?>
                        <div class="login-error">
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <!-- LOGIN FORM -->
                    <form method="POST" action="">

                        <div class="login-form-group">
                            <label for="email">Email Address</label>

                            <input
                                id="email"
                                type="email"
                                name="email"
                                placeholder="Enter your email address"
                                autocomplete="username"
                                required
                            >
                        </div>

                        <div class="login-form-group">
                            <label for="password">Password</label>

                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                            >
                        </div>

                        <button type="submit" class="login-button">
                            Sign In
                        </button>

                    </form>

                    <!-- CREATE ACCOUNT LINK -->
                    <div class="login-footer">
                        <p>
                            New to SmartDesk?
                            <a href="register.php">Create an account</a>
                        </p>
                    </div>

                </div>
            </div>

        </section>

        <footer class="login-copyright">
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
            const isDark =
                preference === 'dark' ||
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
