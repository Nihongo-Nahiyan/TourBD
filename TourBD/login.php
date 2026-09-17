<?php

require_once 'config/db.php';

$pageTitle = 'Login';
require_once 'includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: index.php');
        }
        exit;
    }
    $message = 'Invalid email or password.';
}
?>

<div class="container">
    <div class="form-card">
        <h2>Login</h2>
        <p class="section-subtitle">Customer or admin login.</p>
        <?php if (isset($_GET['registered'])): ?><div class="alert alert-success">Registration successful. Please login.</div><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button class="btn-primary" type="submit">Login</button>
        </form>
        
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>