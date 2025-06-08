<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$name = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?php echo $role; ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">

    <div class="container py-5">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">Welcome, <?php echo $name; ?> 👋</h4>
                    <small>Role: <?php echo $role; ?></small>
                </div>
                <a href="logout.php" class="btn btn-light">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>

            <div class="card-body">
                <?php if ($role === 'admin'): ?>
                    <h5 class="mb-3 text-primary">Admin Panel</h5>
                    <div class="list-group">
                        <a href="list_tickets.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-list-check me-2"></i> View All Tickets
                        </a>
                        <a href="assign_ticket.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-check me-2"></i> Assign Tickets
                        </a>
                        <a href="report_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-graph-up-arrow me-2"></i> View Reports
                        </a>
                        <a href="manage_users.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people me-2"></i> Manage Users
                        </a>
                    </div>

                <?php elseif ($role === 'agent'): ?>
                    <h5 class="mb-3 text-success">Agent Panel</h5>
                    <div class="list-group">
                        <a href="list_tickets.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-ticket-perforated me-2"></i> View Assigned Tickets
                        </a>
                        <a href="update_status.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-pencil-square me-2"></i> Update Ticket Status
                        </a>
                    </div>

                <?php else: ?>
                    <h5 class="mb-3 text-info">User Panel</h5>
                    <div class="list-group">
                        <a href="add_ticket.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-plus-circle me-2"></i> Create Ticket
                        </a>
                        <a href="list_tickets.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-list-task me-2"></i> View My Tickets
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
