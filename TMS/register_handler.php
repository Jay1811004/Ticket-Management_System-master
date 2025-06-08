<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = $_POST['name'];
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $role     = $_POST['role'];

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $checkQuery = "SELECT * FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $checkQuery);

    if (mysqli_num_rows($result) > 0) {
        echo "<script>alert('Email already exists!'); window.location.href='register.php';</script>";
    } else {
        $insertQuery = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$hashedPassword', '$role')";
        if (mysqli_query($conn, $insertQuery)) {
            echo "<script>alert('Registered successfully! Please login.'); window.location.href='login.php';</script>";
        } else {
            echo "<script>alert('Error during registration.'); window.location.href='register.php';</script>";
        }
    }
}
?>
