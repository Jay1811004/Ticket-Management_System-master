<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$role = $_SESSION['role'];

// Get ticket ID from URL
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticket_id <= 0) {
    header("Location: list_tickets.php");
    exit();
}

// Build query based on role to ensure users can only view appropriate tickets
$query = "SELECT t.*, 
                 creator.name as creator_name, creator.email as creator_email,
                 assigned.name as assigned_name, assigned.email as assigned_email
          FROM tickets t 
          LEFT JOIN users creator ON t.created_by = creator.id 
          LEFT JOIN users assigned ON t.assigned_to = assigned.id 
          WHERE t.id = ?";

// Add role-based restrictions
$params = [$ticket_id];
$param_types = 'i';

if ($role === 'agent') {
    $query .= " AND t.assigned_to = ?";
    $params[] = $user_id;
    $param_types .= 'i';
} elseif ($role === 'user') {
    $query .= " AND t.created_by = ?";
    $params[] = $user_id;
    $param_types .= 'i';
}

// Prepare and execute query
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, $param_types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ticket = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Check if ticket exists and user has permission to view
if (!$ticket) {
    header("Location: list_tickets.php?error=access_denied");
    exit();
}

// Status badge colors function
function getStatusBadge($status) {
    $badges = [
        'open' => 'bg-primary',
        'in-progress' => 'bg-warning text-dark',
        'resolved' => 'bg-success',
        'closed' => 'bg-secondary'
    ];
    return $badges[$status] ?? 'bg-secondary';
}

// Priority badge colors function
function getPriorityBadge($priority) {
    $badges = [
        'low' => 'bg-success',
        'medium' => 'bg-info',
        'high' => 'bg-warning text-dark',
        'urgent' => 'bg-danger'
    ];
    return $badges[$priority] ?? 'bg-secondary';
}

// Priority icons function
function getPriorityIcon($priority) {
    $icons = [
        'low' => '🟢',
        'medium' => '🟡',
        'high' => '🟠',
        'urgent' => '🔴'
    ];
    return $icons[$priority] ?? '⚪';
}

// Calculate time elapsed
function timeElapsed($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M d, Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticket['id']; ?> - <?php echo htmlspecialchars($ticket['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        .ticket-header {
            background: linear-gradient(135deg,rgb(6, 6, 7) 0%,rgb(53, 53, 53) 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .ticket-meta {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .priority-high {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .ticket-actions .btn {
            margin: 0.25rem;
        }
        .description-content {
            line-height: 1.6;
            white-space: pre-wrap;
            font-size: 1rem;
            padding: 1.5rem;
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
        }
        .info-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .info-card .card-header {
            font-weight: 600;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Main Ticket Content -->
            <div class="col-lg-8">
                <div class="card shadow-lg border-0 mb-4">
                    <!-- Ticket Header -->
                    <div class="ticket-header p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="mb-2">
                                    <i class="bi bi-ticket-detailed me-2"></i>
                                    <?php echo htmlspecialchars($ticket['title']); ?>
                                </h2>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="badge bg-light text-dark fs-6">
                                        Ticket #<?php echo $ticket['id']; ?>
                                    </span>
                                    <span class="badge <?php echo getStatusBadge($ticket['status']); ?> fs-6">
                                        <?php echo ucfirst(str_replace('-', ' ', $ticket['status'])); ?>
                                    </span>
                                    <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?> fs-6 <?php echo $ticket['priority'] === 'urgent' ? 'priority-high' : ''; ?>">
                                        <?php echo getPriorityIcon($ticket['priority']); ?> <?php echo ucfirst($ticket['priority']); ?> Priority
                                    </span>
                                </div>
                            </div>
                            <div class="ticket-actions text-end">
                                <?php if ($role === 'admin' || ($role === 'user' && $ticket['created_by'] == $user_id && in_array($ticket['status'], ['open', 'in-progress']))): ?>
                                    <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-light btn-sm">
                                        <i class="bi bi-pencil"></i> Edit Ticket
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($role === 'admin' || $role === 'agent'): ?>
                                    <a href="update_status.php?id=<?php echo $ticket['id']; ?>" class="btn btn-light btn-sm">
                                        <i class="bi bi-arrow-up-circle"></i> Update Status
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($role === 'admin'): ?>
                                    <a href="assign_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-light btn-sm">
                                        <i class="bi bi-person-plus"></i> Reassign
                                    </a>
                                <?php endif; ?>
                                
                                <a href="list_tickets.php" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ticket Description -->
                    <div class="card-body">
                        <h5 class="mb-3">
                            <i class="bi bi-file-text me-2 text-primary"></i>Description
                        </h5>
                        <div class="description-content">
                            <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                        </div>
                        
                        <!-- Additional Ticket Details -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card info-card">
                                    <div class="card-header bg-primary text-white">
                                        <i class="bi bi-calendar me-2"></i>Timeline
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong>Created:</strong>
                                            <div class="text-muted">
                                                <?php echo date('F d, Y \a\t g:i A', strtotime($ticket['created_at'])); ?>
                                                <br>
                                                <small>(<?php echo timeElapsed($ticket['created_at']); ?>)</small>
                                            </div>
                                        </div>
                                        <?php if ($ticket['updated_at'] && $ticket['updated_at'] !== $ticket['created_at']): ?>
                                            <div>
                                                <strong>Last Updated:</strong>
                                                <div class="text-muted">
                                                    <?php echo date('F d, Y \a\t g:i A', strtotime($ticket['updated_at'])); ?>
                                                    <br>
                                                    <small>(<?php echo timeElapsed($ticket['updated_at']); ?>)</small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card info-card">
                                    <div class="card-header bg-success text-white">
                                        <i class="bi bi-gear me-2"></i>Current Status
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong>Status:</strong>
                                            <span class="badge <?php echo getStatusBadge($ticket['status']); ?> ms-2">
                                                <?php echo ucfirst(str_replace('-', ' ', $ticket['status'])); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <strong>Priority Level:</strong>
                                            <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?> ms-2">
                                                <?php echo getPriorityIcon($ticket['priority']); ?> <?php echo ucfirst($ticket['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Ticket Information -->
                <div class="card shadow border-0 mb-4">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-info-circle me-2"></i>Ticket Summary
                        </h6>
                    </div>
                    <div class="card-body ticket-meta">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>Ticket ID:</strong>
                                    <span class="badge bg-primary">#<?php echo $ticket['id']; ?></span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>Status:</strong>
                                    <span class="badge <?php echo getStatusBadge($ticket['status']); ?>">
                                        <?php echo ucfirst(str_replace('-', ' ', $ticket['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>Priority:</strong>
                                    <span class="badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                        <?php echo getPriorityIcon($ticket['priority']); ?> <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-12">
                                <strong>Created:</strong>
                                <div class="text-muted small">
                                    <?php echo date('M d, Y \a\t g:i A', strtotime($ticket['created_at'])); ?>
                                </div>
                            </div>
                            <?php if ($ticket['updated_at'] && $ticket['updated_at'] !== $ticket['created_at']): ?>
                                <div class="col-12">
                                    <strong>Updated:</strong>
                                    <div class="text-muted small">
                                        <?php echo date('M d, Y \a\t g:i A', strtotime($ticket['updated_at'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- People Involved -->
                <div class="card shadow border-0 mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-people me-2"></i>People Involved
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Created By:</strong>
                            <div class="mt-2 p-2 bg-light rounded">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person-circle me-2 text-primary"></i>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($ticket['creator_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($ticket['creator_email']); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <strong>Assigned To:</strong>
                            <div class="mt-2 p-2 bg-light rounded">
                                <?php if ($ticket['assigned_name']): ?>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-person-check me-2 text-success"></i>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($ticket['assigned_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($ticket['assigned_email']); ?></small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted">
                                        <i class="bi bi-person-x display-6"></i>
                                        <div class="mt-1">Not assigned yet</div>
                                        <?php if ($role === 'admin'): ?>
                                            <a href="assign_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-outline-primary mt-2">
                                                <i class="bi bi-person-plus"></i> Assign Agent
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card shadow border-0">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($role === 'admin' || ($role === 'user' && $ticket['created_by'] == $user_id && in_array($ticket['status'], ['open', 'in-progress']))): ?>
                                <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-pencil me-2"></i>Edit Ticket Details
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($role === 'admin' || $role === 'agent'): ?>
                                <a href="update_status.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-success btn-sm">
                                    <i class="bi bi-arrow-up-circle me-2"></i>Change Status
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($role === 'admin'): ?>
                                <a href="assign_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-person-plus me-2"></i>Reassign Ticket
                                </a>
                            <?php endif; ?>
                            
                            <a href="list_tickets.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-list me-2"></i>View All Tickets
                            </a>
                            
                            <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
                                <i class="bi bi-house me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
