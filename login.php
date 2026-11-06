<?php
/**
 * Login Screen
 * Task Assignment and Monitoring System (TAMS)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errorMessage = '';
$usernameVal = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $usernameVal = $username;

    if (empty($username) || empty($password)) {
        $errorMessage = 'Please enter both your username/email and password.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if (!$user) {
                $errorMessage = 'Invalid username or password. Please verify your credentials.';
            } elseif ($user['status'] !== 'active') {
                $errorMessage = 'Your account has been deactivated. Please contact an Administrator.';
            } elseif (!password_verify($password, $user['password'])) {
                $errorMessage = 'Invalid username or password. Please verify your credentials.';
            } else {
                // Success: record user in session
                unset($user['password']);
                $_SESSION['user'] = $user;
                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            $errorMessage = 'Database connection error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - Task Assignment & Monitoring System</title>
    <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.5rem;
            position: relative;
        }

        .login-card {
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8);
            width: 100%;
            max-width: 480px;
            padding: 2.5rem 2.25rem;
            position: relative;
            z-index: 2;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand-icon {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, #4f46e5, #8b5cf6);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: #fff;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px var(--primary-glow);
        }

        .login-title {
            font-size: 1.6rem;
            margin-bottom: 0.35rem;
        }

        .login-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .error-banner {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger-border);
            color: #fca5a5;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        /* Demo Accounts Box */
        .demo-accounts {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .demo-accounts-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-dim);
            text-align: center;
            margin-bottom: 0.85rem;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.6rem;
        }

        .demo-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.65rem 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .demo-card:hover {
            background: var(--bg-card-hover);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .demo-card-icon {
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .demo-card-role {
            font-size: 0.725rem;
            font-weight: 700;
            color: #fff;
            display: block;
        }

        .demo-card-user {
            font-size: 0.675rem;
            color: var(--text-dim);
            display: block;
        }
    </style>
</head>
<body class="login-page">

    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-icon">✓</div>
            <h1 class="login-title">Welcome to TAMS</h1>
            <p class="login-subtitle">Task Assignment & Monitoring System</p>
        </div>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-banner" id="login-error-banner">
                <span>⚠️</span>
                <span><?= h($errorMessage) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid var(--success-border); color: #6ee7b7; padding: 0.85rem 1rem; border-radius: var(--radius-md); font-size: 0.85rem; margin-bottom: 1.5rem;">
                ✓ You have been logged out successfully.
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="login-form">
            <div class="form-group">
                <label class="form-label" for="username">Username or Email</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-input" 
                    placeholder="e.g. admin, leader, or member1" 
                    value="<?= h($usernameVal) ?>" 
                    required 
                    autofocus
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    placeholder="••••••••" 
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; margin-top: 0.5rem;">
                Log In to Dashboard →
            </button>
        </form>

        <!-- Quick 1-Click Demo Accounts for instant role testing -->
        <div class="demo-accounts">
            <div class="demo-accounts-title">Quick Demo Login (1-Click)</div>
            <div class="demo-grid">
                <div class="demo-card" onclick="fillAndSubmit('admin', 'admin123')">
                    <div class="demo-card-icon">👑</div>
                    <span class="demo-card-role">Admin</span>
                    <span class="demo-card-user">admin / admin123</span>
                </div>
                <div class="demo-card" onclick="fillAndSubmit('leader', 'leader123')">
                    <div class="demo-card-icon">🎯</div>
                    <span class="demo-card-role">Team Leader</span>
                    <span class="demo-card-user">leader / leader123</span>
                </div>
                <div class="demo-card" onclick="fillAndSubmit('member1', 'member123')">
                    <div class="demo-card-icon">💻</div>
                    <span class="demo-card-role">Member</span>
                    <span class="demo-card-user">member1 / member123</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fillAndSubmit(user, pass) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = pass;
            document.getElementById('login-form').submit();
        }
    </script>
</body>
</html>
