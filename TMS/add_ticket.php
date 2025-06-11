<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    
    // Validation
    if (empty($title) || empty($description)) {
        $message = "Title and description are required.";
        $message_type = "danger";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO tickets (title, description, priority, created_by) VALUES (?, ?, ?, ?)");
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssi", $title, $description, $priority, $user_id);
            
            // Execute the statement
            $result = mysqli_stmt_execute($stmt);
            
            if ($result) {
                $message = "Ticket created successfully!";
                $message_type = "success";
                $title = $description = '';
            } else {
                $message = "Failed to create ticket. Please try again.";
                $message_type = "danger";
            }

            mysqli_stmt_close($stmt);
        } else {
            $message = "Database error: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="bi bi-plus-circle me-2"></i>Create New Ticket
                            </h4>
                            <a href="dashboard.php" class="btn btn-light btn-sm">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="title" class="form-label">
                                    <i class="bi bi-card-heading"></i> Ticket Title *
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="title" 
                                       name="title" 
                                       placeholder="Enter a clear, descriptive title"
                                       value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>"
                                       required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="bi bi-card-text"></i> Description *
                                </label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="6" 
                                          placeholder="Describe your issue in detail..."
                                          required><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="priority" class="form-label">
                                    <i class="bi bi-exclamation-triangle"></i> Priority Level
                                </label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>
                                        🟢 Low - Non-urgent issue
                                    </option>
                                    <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>
                                        🟡 Medium - Normal priority
                                    </option>
                                    <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>
                                        🟠 High - Important issue
                                    </option>
                                    <option value="urgent" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'urgent') ? 'selected' : ''; ?>>
                                        🔴 Urgent - Critical issue
                                    </option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn-login">
                                    <i class="bi bi-check-circle me-2"></i>Create Ticket
                                </button>
                                <a href="list_tickets.php" class="btn-cancel">
                                    <i class="bi bi-list-task me-2"></i>View My Tickets
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
