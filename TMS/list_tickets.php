<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['userid'];
$role = $_SESSION['role'];

// Pagination settings
$tickets_per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $tickets_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : '';

// Build WHERE conditions
$where_conditions = [];
$params = [];
$param_types = '';

// Role-based filtering
if ($role === 'agent') {
    $where_conditions[] = "t.assigned_to = ?";
    $params[] = $user_id;
    $param_types .= 'i';
} elseif ($role === 'user') {
    $where_conditions[] = "t.created_by = ?";
    $params[] = $user_id;
    $param_types .= 'i';
}

// Search functionality
if (!empty($search)) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $param_types .= 'ss';
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

// Priority filter
if (!empty($priority_filter)) {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
    $param_types .= 's';
}

// Build the WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
}

// Base query for different roles
if ($role === 'admin') {
    $base_query = "FROM tickets t 
                   LEFT JOIN users u ON t.assigned_to = u.id 
                   LEFT JOIN users creator ON t.created_by = creator.id";
    $select_fields = "t.*, u.name as assigned_name, creator.name as creator_name";
} elseif ($role === 'agent') {
    $base_query = "FROM tickets t 
                   LEFT JOIN users creator ON t.created_by = creator.id";
    $select_fields = "t.*, creator.name as creator_name";
} else {
    $base_query = "FROM tickets t 
                   LEFT JOIN users u ON t.assigned_to = u.id";
    $select_fields = "t.*, u.name as assigned_name";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) " . $base_query . $where_clause;
if (!empty($params)) {
    $count_stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_tickets = mysqli_fetch_row($count_result)[0];
    mysqli_stmt_close($count_stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
    $total_tickets = mysqli_fetch_row($count_result)[0];
}

$total_pages = ceil($total_tickets / $tickets_per_page);

// Get tickets for current page
$main_query = "SELECT " . $select_fields . " " . $base_query . $where_clause . " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";

// Add pagination parameters
$params[] = $tickets_per_page;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $main_query);
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $result = mysqli_query($conn, $main_query);
    $tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Status badge colors
function getStatusBadge($status) {
    $badges = [
        'open' => 'bg-primary',
        'in-progress' => 'bg-warning text-dark',
        'resolved' => 'bg-success',
        'closed' => 'bg-secondary'
    ];
    return $badges[$status] ?? 'bg-secondary';
}

// Priority badge colors
function getPriorityBadge($priority) {
    $badges = [
        'low' => 'bg-success',
        'medium' => 'bg-info',
        'high' => 'bg-warning text-dark',
        'urgent' => 'bg-danger'
    ];
    return $badges[$priority] ?? 'bg-secondary';
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
    <title>
        <?php 
        if ($role === 'admin') echo 'All Tickets - Admin Panel';
        elseif ($role === 'agent') echo 'Assigned Tickets - Agent Panel';
        else echo 'My Tickets - User Panel';
        ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
    <style>
        .ticket-title {
            font-weight: 600;
            color: #2c3e50;
        }
        .ticket-description {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.4;
        }
        .priority-badge {
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.75rem;
            font-weight: 600;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
        }
        .search-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="card shadow-lg border-0">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="bi bi-list-task me-2"></i>
                        <?php 
                        if ($role === 'admin') echo 'All Tickets Management';
                        elseif ($role === 'agent') echo 'My Assigned Tickets';
                        else echo 'My Tickets';
                        ?>
                        <span class="badge bg-light text-primary ms-2"><?php echo $total_tickets; ?></span>
                    </h4>
                    <div>
                        <?php if ($role === 'user'): ?>
                            <a href="add_ticket.php" class="btn btn-light btn-sm me-2">
                                <i class="bi bi-plus-circle"></i> Create New Ticket
                            </a>
                        <?php endif; ?>
                        <a href="dashboard.php" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Enhanced Search and Filter Form -->
                <div class="search-form">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Search Tickets</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by title or description..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>🟦 Open</option>
                                <option value="in-progress" <?php echo $status_filter === 'in-progress' ? 'selected' : ''; ?>>🟨 In Progress</option>
                                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>🟩 Resolved</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>⬜ Closed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="">All Priorities</option>
                                <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>🟢 Low</option>
                                <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>🟡 Medium</option>
                                <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>🟠 High</option>
                                <option value="urgent" <?php echo $priority_filter === 'urgent' ? 'selected' : ''; ?>>🔴 Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100 d-block">
                                <i class="bi bi-funnel"></i> Apply Filters
                            </button>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted">&nbsp;</label>
                            <a href="list_tickets.php" class="btn btn-outline-secondary w-100 d-block">
                                <i class="bi bi-arrow-clockwise"></i> Clear All
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Results Summary -->
                <?php if (!empty($search) || !empty($status_filter) || !empty($priority_filter)): ?>
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Showing <?php echo count($tickets); ?> of <?php echo $total_tickets; ?> tickets
                        <?php if (!empty($search)): ?>
                            matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($status_filter)): ?>
                            with status "<strong><?php echo ucfirst(str_replace('-', ' ', $status_filter)); ?></strong>"
                        <?php endif; ?>
                        <?php if (!empty($priority_filter)): ?>
                            with priority "<strong><?php echo ucfirst($priority_filter); ?></strong>"
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Tickets Table -->
                <?php if (empty($tickets)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No tickets found</h5>
                        <p class="text-muted">
                            <?php if (!empty($search) || !empty($status_filter) || !empty($priority_filter)): ?>
                                Try adjusting your search criteria or filters.
                            <?php else: ?>
                                <?php if ($role === 'user'): ?>
                                    Get started by creating your first support ticket.
                                <?php else: ?>
                                    No tickets match your current view.
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($role === 'user' && empty($search) && empty($status_filter) && empty($priority_filter)): ?>
                            <a href="add_ticket.php" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-circle"></i> Create Your First Ticket
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 60px;">ID</th>
                                    <th style="width: 30%;">Ticket Details</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 100px;">Priority</th>
                                    <?php if ($role === 'admin'): ?>
                                        <th style="width: 120px;">Created By</th>
                                        <th style="width: 120px;">Assigned To</th>
                                    <?php elseif ($role === 'agent'): ?>
                                        <th style="width: 120px;">Created By</th>
                                    <?php else: ?>
                                        <th style="width: 120px;">Assigned To</th>
                                    <?php endif; ?>
                                    <th style="width: 120px;">Created Date</th>
                                    <th style="width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark">#<?php echo $ticket['id']; ?></span>
                                        </td>
                                        <td>
                                            <div class="ticket-title">
                                                <?php echo htmlspecialchars($ticket['title']); ?>
                                            </div>
                                            <div class="ticket-description">
                                                <?php echo substr(htmlspecialchars($ticket['description']), 0, 80) . '...'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge status-badge <?php echo getStatusBadge($ticket['status']); ?>">
                                                <?php echo ucfirst(str_replace('-', ' ', $ticket['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge priority-badge <?php echo getPriorityBadge($ticket['priority']); ?>">
                                                <?php echo getPriorityIcon($ticket['priority']); ?> <?php echo ucfirst($ticket['priority']); ?>
                                            </span>
                                        </td>
                                        <?php if ($role === 'admin'): ?>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($ticket['creator_name'] ?? 'Unknown User'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($ticket['assigned_name'] ?? 'Unassigned'); ?>
                                                </small>
                                            </td>
                                        <?php elseif ($role === 'agent'): ?>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($ticket['creator_name'] ?? 'Unknown User'); ?>
                                                </small>
                                            </td>
                                        <?php else: ?>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($ticket['assigned_name'] ?? 'Unassigned'); ?>
                                                </small>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                                <br>
                                                <?php echo date('H:i', strtotime($ticket['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($role === 'admin' || ($role === 'user' && $ticket['created_by'] == $user_id && in_array($ticket['status'], ['open', 'in-progress']))): ?>
                                                    <a href="edit_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                                       class="btn btn-outline-warning" title="Edit Ticket">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($role === 'admin' || $role === 'agent'): ?>
                                                    <a href="update_status.php?id=<?php echo $ticket['id']; ?>" 
                                                       class="btn btn-outline-success" title="Update Status">
                                                        <i class="bi bi-arrow-up-circle"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Enhanced Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="text-muted">
                                    Showing <?php echo (($page - 1) * $tickets_per_page) + 1; ?> to 
                                    <?php echo min($page * $tickets_per_page, $total_tickets); ?> of 
                                    <?php echo $total_tickets; ?> tickets
                                </div>
                                <div class="text-muted">
                                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                </div>
                            </div>
                            
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
