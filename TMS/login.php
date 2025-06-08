<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-modal">
    <div class="modal-header">
        <h5 class="modal-title w-100 text-center">Login</h5>
    </div>
    <form action="login_handler.php" method="POST">
        <label for="role" class="form-label"><i class="bi bi-person-fill"></i> Role</label>
        <select name="role" id="role" class="form-select" required>
            <option value="Admin">Admin</option>
            <option value="User">User</option>
            <option value="Agent">Agent</option>
        </select>

        <label for="email" class="form-label"><i class="bi bi-envelope-fill"></i> Email</label>
        <input type="email" name="email" id="email" class="form-control" placeholder="Email" required>

        <label for="password" class="form-label"><i class="bi bi-key-fill"></i> Password</label>
        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-login">LOGIN</button>
            <a href="register.php" class="btn btn-outline-primary">New User?</a>
        </div>
    </form>
</div>

</body>
</html>
