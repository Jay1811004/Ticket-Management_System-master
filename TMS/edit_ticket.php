<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$role = $_SESSION['role'];
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$ticket_id) {
    header("Location: list_tickets.php");
    exit();
}

$message = '';
$message_type = '';
$ticket = null;

// Fetch ticket details
$query = "SELECT t.*, creator.name as creator_name, assigned.name as assigned_name 
          FROM tickets t 
          LEFT JOIN users creator ON t.created_by = creator.id 
          LEFT JOIN users assigned ON t.assigned_to = assigned.id 
          WHERE t.id = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $ticket_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ticket = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$ticket) {
    header("Location: list_tickets.php");
    exit();
}

// Check permissions
$can_edit = false;
if ($role === 'admin') {
    $can_edit = true;
} elseif ($role === 'agent' && $ticket['assigned_to'] == $user_id) {
    $can_edit = true;
} elseif ($role === 'user' && $ticket['created_by'] == $user_id && in_array($ticket['status'], ['open', 'in-progress'])) {
    $can_edit = true;
}

if (!$can_edit) {
    header("Location: view_ticket.php?id=" . $ticket_id);
    exit();
}

// Get list of agents for assignment (admin only)
$agents = [];
if ($role === 'admin') {
    $agent_query = "SELECT id, name FROM users WHERE role IN ('admin', 'agent') ORDER BY name";
    $agent_result = mysqli_query($conn, $agent_query);
    $agents = mysqli_fetch_all($agent_result, MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    $status = isset($_POST['status']) ? $_POST['status'] : $ticket['status'];
    $assigned_to = isset($_POST['assigned_to']) ? ($_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null) : $ticket['assigned_to'];
    
    // Validation
    if (empty($title) || empty($description)) {
        $message = "Title and description are required.";
        $message_type = "danger";
    } elseif (!in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
        $message = "Invalid priority selected.";
        $message_type = "danger";
    } elseif (!in_array($status, ['open', 'in-progress', 'resolved', 'closed'])) {
        $message = "Invalid status selected.";
        $message_type = "danger";
    } else {
        // Build update query based on role permissions
        if ($role === 'admin') {
            // Admin can update everything
            $update_query = "UPDATE tickets SET title = ?, description = ?, priority = ?, status = ?, assigned_to = ?, updated_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "ssssis", $title, $description, $priority, $status, $assigned_to, $ticket_id);
        } elseif ($role === 'agent') {
            // Agent can update status, priority, and description
            $update_query = "UPDATE tickets SET description = ?, priority = ?, status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sssi", $description, $priority, $status, $ticket_id);
        } else {
            // User can only update title, description, and priority (and only if ticket is open/in-progress)
            $update_query = "UPDATE tickets SET title = ?, description = ?, priority = ?, updated_at = NOW() WHERE id = ? AND created_by = ? AND status IN ('open', 'in-progress')";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "sssii", $title, $description, $priority, $ticket_id, $user_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            if ($affected_rows > 0) {
                $message = "Ticket updated successfully!";
                $message_type = "success";
                
                // Refresh ticket data
                $refresh_query = "SELECT t.*, creator.name as creator_name, assigned.name as assigned_name 
                                 FROM tickets t 
                                 LEFT JOIN users creator ON t.created_by = creator.id 
                                 LEFT JOIN users assigned ON t.assigned_to = assigned.id 
                                 WHERE t.id = ?";
                $refresh_stmt = mysqli_prepare($conn, $refresh_query);
                mysqli_stmt_bind_param($refresh_stmt, "i", $ticket_id);
                mysqli_stmt_execute($refresh_stmt);
                $refresh_result = mysqli_stmt_get_result($refresh_stmt);
                $ticket = mysqli_fetch_assoc($refresh_result);
                mysqli_stmt_close($refresh_stmt);
            } else {
                $message = "No changes were made or you don't have permission to edit this ticket.";
                $message_type = "warning";
            }
        } else {
            $message = "Failed to update ticket. Please try again.";
            $message_type = "danger";
        }
        mysqli_stmt_close($stmt);
    }
}

// Status options based on role
function getStatusOptions($current_status, $role) {
    $all_statuses = [
        'open' => '🟦 Open',
        'in-progress' => '🟨 In Progress', 
        'resolved' => '🟩 Resolved',
        'closed' => '⬜ Closed'
    ];
    
    if ($role === 'user') {
        // Users can only see current status (read-only)
        return [$current_status => $all_statuses[$current_status]];
    }
    
    return $all_statuses;
}

// Priority icons
function getPriorityIcon($priority) {
    $icons = [
        'low' => '🟢',
        'medium' => '🟡',
        'high' => '🟠',
        'urgent' => '🔴'
    ];
    return $icons[$priority] ?? '⚪';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Ticket #<?php echo $ticket['id']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        .ticket-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #007bff;
        }
        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
        }
        .priority-badge {
            font-size: 0.75rem;
            font-weight: 600;
        }
        .form-section {
            background: #fff;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <!-- Ticket Information Sidebar -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm border-0 ticket-info-card">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0 text-primary">
                            <i class="bi bi-info-circle me-2"></i>Ticket Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Ticket ID</label>
                            <div class="fw-bold">#<?php echo $ticket['id']; ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted">Current Status</label>
                            <div>
                                <span class="badge bg-primary status-badge">
                                    <?php echo ucfirst(str_replace('-', ' ', $ticket['status'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted">Current Priority</label>
                            <div>
                                <span class="badge bg-warning text-dark priority-badge">
                                    <?php echo getPriorityIcon($ticket['priority']); ?> <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted">Created By</label>
                            <div><?php echo htmlspecialchars($ticket['creator_name']); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted">Assigned To</label>
                            <div><?php echo htmlspecialchars($ticket['assigned_name'] ?? 'Unassigned'); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted">Created Date</label>
                            <div><?php echo date('M d, Y \a\t H:i', strtotime($ticket['created_at'])); ?></div>
                        </div>
                        
                        <div class="mb-0">
                            <label class="form-label small text-muted">Last Updated</label>
                            <div><?php echo date('M d, Y \a\t H:i', strtotime($ticket['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card shadow-sm border-0 mt-3">
                    <div class="card-header bg-transparent">
                        <h6 class="mb-0 text-success">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-eye me-2"></i>View Details
                            </a>
                            <a href="list_tickets.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-list-task me-2"></i>Back to List
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                                <i class="bi bi-house me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form -->
            <div class="col-lg-8">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="bi bi-pencil-square me-2"></i>Edit Ticket #<?php echo $ticket['id']; ?>
                            </h4>
                            <span class="badge bg-light text-dark">
                                <?php echo ucfirst($role); ?> Access
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'x-circle'); ?> me-2"></i>
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <!-- Basic Information Section -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="bi bi-card-heading me-2"></i>Basic Information
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">
                                        <i class="bi bi-card-text"></i> Ticket Title *
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="title" 
                                           name="title" 
                                           value="<?php echo htmlspecialchars($ticket['title']); ?>"
                                           <?php echo $role === 'agent' ? 'readonly' : ''; ?>
                                           required>
                                    <?php if ($role === 'agent'): ?>
                                        <div class="form-text">
                                            <i class="bi bi-info-circle me-1"></i>Agents cannot modify the ticket title
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">
                                        <i class="bi bi-card-text"></i> Description *
                                    </label>
                                    <textarea class="form-control" 
                                              id="description" 
                                              name="description" 
                                              rows="6" 
                                              required><?php echo htmlspecialchars($ticket['description']); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Priority and Status Section -->
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="bi bi-sliders me-2"></i>Priority & Status
                                </h5>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="priority" class="form-label">
                                            <i class="bi bi-exclamation-triangle"></i> Priority Level
                                        </label>
                                        <select class="form-select" id="priority" name="priority" required>
                                            <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>
                                                🟢 Low - Non-urgent issue
                                            </option>
                                            <option value="medium" <?php echo $ticket['priority'] === 'medium' ? 'selected' : ''; ?>>
                                                🟡 Medium - Normal priority
                                            </option>
                                            <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>
                                                🟠 High - Important issue
                                            </option>
                                            <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>
                                                🔴 Urgent - Critical issue
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <?php if ($role !== 'user'): ?>
                                    <div class="col-md-6">
                                        <label for="status" class="form-label">
                                            <i class="bi bi-flag"></i> Status
                                        </label>
                                        <select class="form-select" id="status" name="status" required>
                                            <?php foreach (getStatusOptions($ticket['status'], $role) as $value => $label): ?>
                                                <option value="<?php echo $value; ?>" <?php echo $ticket['status'] === $value ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Assignment Section (Admin Only) -->
                            <?php if ($role === 'admin' && !empty($agents)): ?>
                            <div class="form-section">
                                <h5 class="section-title">
                                    <i class="bi bi-person-check me-2"></i>Assignment
                                </h5>
                                
                                <div class="mb-3">
                                    <label for="assigned_to" class="form-label">
                                        <i class="bi bi-person"></i> Assign to Agent
                                    </label>
                                    <select class="form-select" id="assigned_to" name="assigned_to">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($agents as $agent): ?>
                                            <option value="<?php echo $agent['id']; ?>" 
                                                    <?php echo $ticket['assigned_to'] == $agent['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($agent['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                <div>
                                    <button type="submit" class="btn-login me-2">
                                        <i class="bi bi-check-circle me-2"></i>Update Ticket
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Changes
                                    </button>
                                </div>
                                <div>
                                    <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn-cancel">
                                        <i class="bi bi-x-circle me-2"></i>Cancel
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            // Add loading state to submit button
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Updating...';
            });
            
            // Auto-resize textarea
            const textarea = document.getElementById('description');
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            
            // Confirm form reset
            const resetBtn = document.querySelector('button[type="reset"]');
            resetBtn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to reset all changes?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
