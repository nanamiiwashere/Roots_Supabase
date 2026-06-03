<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../auth/auth-handler.php';
require_once __DIR__ . '/../auth/auth3thparty.php';

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$stmt = $pdo->prepare("SELECT membership, name, email, avatar FROM users WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$adminUser = $stmt->fetch();

if (!$adminUser || $adminUser['membership'] !== 'admin') {
    header('Location: ' . APP_URL . '/dashboard/index.php');
    exit;
}

// Handle actions
$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_workspace') {
        $workspaceId = (int)($_POST['workspace_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $industry = $_POST['industry'] ?? '';
        $companySize = $_POST['company_size'] ?? '';

        if ($workspaceId && $name) {
            $stmt = $pdo->prepare("UPDATE workspaces SET name = ?, industry = ?, company_size = ? WHERE id = ?");
            $stmt->execute([$name, $industry, $companySize, $workspaceId]);
            $alert = ['type' => 'success', 'message' => 'Workspace updated successfully.'];
        }
    } elseif ($action === 'delete_workspace') {
        $workspaceId = (int)($_POST['workspace_id'] ?? 0);
        if ($workspaceId) {
            // Delete workspace members first
            $stmt = $pdo->prepare("DELETE FROM workspace_members WHERE workspace_id = ?");
            $stmt->execute([$workspaceId]);
            
            // Delete workspace
            $stmt = $pdo->prepare("DELETE FROM workspaces WHERE id = ?");
            $stmt->execute([$workspaceId]);
            $alert = ['type' => 'success', 'message' => 'Workspace deleted successfully.'];
        }
    }
}

// Search and filter
$search = $_GET['search'] ?? '';
$industryFilter = $_GET['industry'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(w.name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($industryFilter) {
    $where[] = "w.industry = ?";
    $params[] = $industryFilter;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT w.id) as total FROM workspaces w LEFT JOIN workspace_members wm ON w.id = wm.workspace_id LEFT JOIN users u ON wm.user_id = u.id_user $whereClause");
$countStmt->execute($params);
$totalWorkspaces = $countStmt->fetch()['total'];
$totalPages = ceil($totalWorkspaces / $perPage);

// Get workspaces with owner info and member count
// Owner is determined by workspace_members.role = 'owner'
$stmt = $pdo->prepare("
    SELECT w.*, 
    COUNT(DISTINCT wm.user_id) as member_count,
    GROUP_CONCAT(DISTINCT u.name SEPARATOR ', ') as member_names,
    owner.name as owner_name,
    owner.email as owner_email
    FROM workspaces w
    LEFT JOIN workspace_members wm ON w.id = wm.workspace_id
    LEFT JOIN users u ON wm.user_id = u.id_user
    LEFT JOIN workspace_members wm_owner ON w.id = wm_owner.workspace_id AND wm_owner.role = 'owner'
    LEFT JOIN users owner ON wm_owner.user_id = owner.id_user
    $whereClause
    GROUP BY w.id
    ORDER BY w.id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$workspaces = $stmt->fetchAll();

// Get industry counts for filter
$industryCounts = [];
$stmt = $pdo->query("SELECT industry, COUNT(*) as count FROM workspaces WHERE industry IS NOT NULL AND industry != '' GROUP BY industry");
while ($row = $stmt->fetch()) {
    $industryCounts[$row['industry']] = $row['count'];
}

// Get workspace stats for stat cards
$workspaceStats = [];
$stmt = $pdo->query("SELECT industry, COUNT(*) as count FROM workspaces GROUP BY industry");
while ($row = $stmt->fetch()) {
    $workspaceStats[$row['industry']] = (int) $row['count'];
}

// Get total members across all workspaces
$stmt = $pdo->query("SELECT COUNT(*) as total FROM workspace_members");
$totalMembers = $stmt->fetch()['total'];

// Get average members per workspace
$stmt = $pdo->query("SELECT AVG(member_count) as avg_members FROM (SELECT COUNT(*) as member_count FROM workspace_members GROUP BY workspace_id) sub");
$avgMembers = round($stmt->fetch()['avg_members'] ?? 0, 1);

// Set sidebar variables
$displayName = $adminUser['name'];
$adminEmail = $adminUser['email'];
$avatar = $adminUser['avatar'];

$pageTitle = 'Workspace Management - Super Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/superadmin/partials/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="superadmin-shell">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <div class="superadmin-main">
            <header class="superadmin-topbar">
                <div class="superadmin-breadcrumb">
                    <a href="<?php echo APP_URL; ?>/superadmin/index.php">Roots</a>
                    <i></i>
                    <strong>Workspaces</strong>
                </div>
            </header>

            <main class="superadmin-content">
                <h1>Workspace Management</h1>
                <p class="superadmin-subtitle">Manage all workspaces and their configurations.</p>

                <?php if ($alert): ?>
                    <div class="sa-alert sa-alert-<?php echo $alert['type']; ?>">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Stat Cards -->
                <div class="superadmin-stats">
                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: var(--mint-pale);">
                                <svg viewBox="0 0 24 24" fill="none" stroke="var(--mint-dark)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            </div>
                            <span>Total Workspaces</span>
                        </div>
                        <strong><?php echo number_format($totalWorkspaces); ?></strong>
                        <span class="sa-stat-sub">All workspaces</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <span>Total Members</span>
                        </div>
                        <strong><?php echo number_format($totalMembers); ?></strong>
                        <span class="sa-stat-sub">Across all workspaces</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: #ede9fe;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                            </div>
                            <span>Avg. Members</span>
                        </div>
                        <strong><?php echo $avgMembers; ?></strong>
                        <span class="sa-stat-sub">Per workspace</span>
                    </div>

                    <div class="sa-stat-card">
                        <div class="sa-stat-head">
                            <div class="sa-stat-ico" style="background: #fef3c7;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            </div>
                            <span>Retail/E-Commerce</span>
                        </div>
                        <strong><?php echo number_format($workspaceStats['retail_ecommerce'] ?? 0); ?></strong>
                        <span class="sa-stat-sub">Retail workspaces</span>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="sa-toolbar">
                    <form class="sa-search" method="GET" action="">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" name="search" placeholder="Search workspaces..." value="<?php echo htmlspecialchars($search); ?>">
                        <?php if ($industryFilter): ?>
                            <input type="hidden" name="industry" value="<?php echo htmlspecialchars($industryFilter); ?>">
                        <?php endif; ?>
                    </form>

                    <select class="sa-select" onchange="window.location.href='?industry='+this.value+'&search=<?php echo urlencode($search); ?>'">
                        <option value="">All Industries</option>
                        <option value="retail_ecommerce" <?php echo $industryFilter === 'retail_ecommerce' ? 'selected' : ''; ?>>Retail / E-Commerce</option>
                        <option value="service_business" <?php echo $industryFilter === 'service_business' ? 'selected' : ''; ?>>Service Business</option>
                        <option value="manufacturing" <?php echo $industryFilter === 'manufacturing' ? 'selected' : ''; ?>>Manufacturing</option>
                        <option value="finance_accounting" <?php echo $industryFilter === 'finance_accounting' ? 'selected' : ''; ?>>Finance & Accounting</option>
                        <option value="technology" <?php echo $industryFilter === 'technology' ? 'selected' : ''; ?>>Technology</option>
                        <option value="healthcare" <?php echo $industryFilter === 'healthcare' ? 'selected' : ''; ?>>Healthcare</option>
                        <option value="education" <?php echo $industryFilter === 'education' ? 'selected' : ''; ?>>Education</option>
                        <option value="other" <?php echo $industryFilter === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <!-- Workspaces Table -->
                <div class="sa-panel">
                    <?php if (empty($workspaces)): ?>
                        <div class="sa-empty">
                            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            <p>No workspaces found</p>
                        </div>
                    <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Workspace</th>
                                        <th>Owner</th>
                                        <th>Industry</th>
                                        <th>Size</th>
                                        <th>Members</th>
                                        <th>Created</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($workspaces as $ws): ?>
                                        <tr>
                                            <td>
                                                <div class="sa-user-cell">
                                                    <div class="sa-user-avatar-placeholder" style="background:var(--mint-pale);">
                                                        <svg viewBox="0 0 24 24" style="stroke:var(--mint-dark);"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                                    </div>
                                                    <div class="sa-user-info">
                                                        <strong><?php echo htmlspecialchars($ws['name']); ?></strong>
                                                        <span>ID: #<?php echo $ws['id']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="sa-user-info">
                                                    <strong><?php echo htmlspecialchars($ws['owner_name'] ?? 'N/A'); ?></strong>
                                                    <span><?php echo htmlspecialchars($ws['owner_email'] ?? ''); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $industryLabels = [
                                                    'retail_ecommerce' => 'Retail / E-Commerce',
                                                    'service_business' => 'Service Business',
                                                    'manufacturing' => 'Manufacturing',
                                                    'finance_accounting' => 'Finance & Accounting',
                                                    'technology' => 'Technology',
                                                    'healthcare' => 'Healthcare',
                                                    'education' => 'Education',
                                                    'other' => 'Other'
                                                ];
                                                echo htmlspecialchars($industryLabels[$ws['industry']] ?? $ws['industry'] ?? 'N/A');
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($ws['company_size'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="sa-membership-badge pro"><?php echo $ws['member_count']; ?> member(s)</span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($ws['created_at'])); ?></td>
                                            <td>
                                                <div class="sa-actions" style="justify-content: flex-end;">
                                                    <button class="sa-action-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($ws)); ?>)" title="Edit Workspace">
                                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                    </button>
                                                    <button class="sa-action-btn danger" onclick="openDeleteModal(<?php echo $ws['id']; ?>, '<?php echo htmlspecialchars($ws['name']); ?>')" title="Delete Workspace">
                                                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div style="display:flex;justify-content:center;gap:8px;margin-top:20px;">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industryFilter); ?>" class="sa-btn">← Prev</a>
                                <?php endif; ?>
                                <span class="sa-btn" style="pointer-events:none;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&industry=<?php echo urlencode($industryFilter); ?>" class="sa-btn">Next →</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Edit Workspace Modal -->
    <div class="sa-modal" id="editModal">
        <div class="sa-modal-backdrop" onclick="closeModal('editModal')"></div>
        <div class="sa-modal-dialog">
            <div class="sa-modal-header">
                <h2>Edit Workspace</h2>
                <button class="sa-modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_workspace">
                <input type="hidden" name="workspace_id" id="edit_workspace_id">
                <div class="sa-modal-body">
                    <div class="sa-field">
                        <label>Workspace Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="sa-field">
                        <label>Industry</label>
                        <select name="industry" id="edit_industry">
                            <option value="">Select Industry</option>
                            <option value="retail_ecommerce">Retail / E-Commerce</option>
                            <option value="service_business">Service Business</option>
                            <option value="manufacturing">Manufacturing</option>
                            <option value="finance_accounting">Finance & Accounting</option>
                            <option value="technology">Technology</option>
                            <option value="healthcare">Healthcare</option>
                            <option value="education">Education</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="sa-field">
                        <label>Company Size</label>
                        <select name="company_size" id="edit_company_size">
                            <option value="">Select Size</option>
                            <option value="1-10">1 - 10 People</option>
                            <option value="11-50">11 - 50 People</option>
                            <option value="51-100">51 - 100 People</option>
                            <option value="101-200">101 - 200 People</option>
                            <option value="201+">201+ People</option>
                        </select>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="sa-modal" id="deleteModal">
        <div class="sa-modal-backdrop" onclick="closeModal('deleteModal')"></div>
        <div class="sa-modal-dialog">
            <div class="sa-modal-header">
                <h2>Delete Workspace</h2>
                <button class="sa-modal-close" onclick="closeModal('deleteModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_workspace">
                <input type="hidden" name="workspace_id" id="delete_workspace_id">
                <div class="sa-modal-body">
                    <p style="margin:0;font-size:14px;color:#1f2730;">
                        Are you sure you want to delete <strong id="delete_workspace_name"></strong>?
                    </p>
                    <p style="margin:8px 0 0;font-size:13px;color:#e23b54;">
                        This action cannot be undone. All members will be removed from this workspace.
                    </p>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-danger">Delete Workspace</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(ws) {
            document.getElementById('edit_workspace_id').value = ws.id;
            document.getElementById('edit_name').value = ws.name;
            document.getElementById('edit_industry').value = ws.industry || '';
            document.getElementById('edit_company_size').value = ws.company_size || '';
            document.getElementById('editModal').classList.add('is-open');
            document.body.classList.add('sa-modal-open');
        }

        function openDeleteModal(workspaceId, workspaceName) {
            document.getElementById('delete_workspace_id').value = workspaceId;
            document.getElementById('delete_workspace_name').textContent = workspaceName;
            document.getElementById('deleteModal').classList.add('is-open');
            document.body.classList.add('sa-modal-open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('is-open');
            document.body.classList.remove('sa-modal-open');
        }
    </script>
</body>
</html>
