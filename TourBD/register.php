<?php

require_once 'config/db.php';

$pageTitle = 'Register';
require_once 'includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];

    if ($name === '' || $email === '' || $password === '') {
        $message = 'Please fill in all required fields.';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        $exists = $check->get_result();

        if ($exists->num_rows > 0) {
            $message = 'Email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user';
            $stmt = $conn->prepare("INSERT INTO users(name,email,phone,password_hash,role) VALUES(?,?,?,?,?)");
            $stmt->bind_param('sssss', $name, $email, $phone, $hash, $role);
            $stmt->execute();
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?>

<div class="container">
    <div class="form-card">
        <h2>Create Account</h2>
        <p class="section-subtitle">Register as a customer.</p>
        <?php if ($message): ?><div class="alert alert-error"><?= e($message) ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group"><label>Name</label><input name="name" required></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Phone</label><input name="phone"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button class="btn-primary" type="submit">Register</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
