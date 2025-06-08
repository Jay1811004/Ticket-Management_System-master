<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = $_POST['email'];
    $password = $_POST['password'];
    $role     = $_POST['role'];

    // Query to find user with matching email and role
    $query = "SELECT * FROM users WHERE email = '$email' AND role = '$role'";
    $result = mysqli_query($conn, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            // Set session variables
            $_SESSION['userid'] = $row['id'];
            $_SESSION['username'] = $row['name'];
            $_SESSION['role'] = $row['role'];

            // Redirect based on role
            header("Location: dashboard.php");
            exit();
        } else {
            echo "<script>alert('Invalid password'); window.location.href='login.php';</script>";
        }
    } else {
        echo "<script>alert('No user found with this role and email.'); window.location.href='login.php';</script>";
    }
}
?>
