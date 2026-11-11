<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (current_user_id() !== null) {
    redirect('index.php');
}

const MIN_PASSWORD_LENGTH = 10;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = valid_email($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === null) {
        $error = 'Enter a valid email address.';
    } elseif (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.';
    } elseif (strlen($password) > 1000 || $password !== (string)($_POST['password2'] ?? '')) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = db()->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?)');
            $stmt->bind_param('ss', $email, $hash);
            $stmt->execute();
            login_user((int)db()->insert_id);
            redirect('index.php');
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $error = 'That email is already registered.';
            } else {
                fail_500('Register failed: ' . $e->getMessage());
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
    <title>Create account - Toastmasters Timer</title>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
</head>
<body>
    <div class="container">
        <a href="./" style="color:var(--text-muted);text-decoration:none;font-weight:600;">← Back to overview</a>
        <header>
            <h1>Toastmasters Timer</h1>
            <p>Create your account</p>
        </header>
        <section class="card">
            <?php if ($error): ?><p class="form-error" role="alert"><?php echo h($error); ?></p><?php endif; ?>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="username" value="<?php echo h($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password (min <?php echo MIN_PASSWORD_LENGTH; ?> characters)</label>
                    <input type="password" id="password" name="password" required minlength="<?php echo MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="password2">Repeat password</label>
                    <input type="password" id="password2" name="password2" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary">Create account</button>
                <p class="usage-note">To keep this free service fast, meetings are removed <?php echo retention_days(); ?> days after their date. Your account stays.</p>
                <p style="margin-top:1rem;">Already registered? <a href="login.php">Sign in</a></p>
            </form>
        </section>
    </div>
</body>
</html>
