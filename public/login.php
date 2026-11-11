<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/site.php';

if (current_user_id() !== null) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = valid_email($_POST['email'] ?? '') ?? '';
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || login_is_throttled($email)) {
        $error = $email === '' ? 'Enter a valid email.' : 'Too many attempts. Try again in a few minutes.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        // Verify against a dummy hash when the user is unknown so timing doesn't reveal which emails exist.
        $hash = $user['password_hash'] ?? password_hash('timing-equaliser', PASSWORD_DEFAULT);
        if (password_verify($password, $hash) && $user) {
            clear_login_failures($email);
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $new = password_hash($password, PASSWORD_DEFAULT);
                $up = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $up->bind_param('si', $new, $user['id']);
                $up->execute();
            }
            login_user((int)$user['id']);
            redirect('index.php');
        }
        record_login_failure($email);
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - Toastmasters Timer</title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/site.css')); ?>">
</head>
<body class="site">
    <?php site_nav(); ?>
    <div class="container login-wrap">
        <header>
            <h1>Toastmasters Timer</h1>
            <p>Sign in to your meetings</p>
            <a class="by-theekmind" href="<?php echo h(SITE_URL); ?>" rel="noopener">
                <img src="<?php echo h(asset('img/theekmind.png')); ?>" alt="" width="24" height="24">
                <span>by <strong>TheekMind</strong></span>
            </a>
        </header>
        <section class="card">
            <?php if ($error): ?><p class="form-error" role="alert"><?php echo h($error); ?></p><?php endif; ?>
            <form method="POST" autocomplete="on">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary">Sign in</button>
                <p style="margin-top:1rem;">No account? <a href="register.php">Create one</a></p>
            </form>
        </section>
    </div>
    <?php site_footer(); ?>
</body>
</html>
