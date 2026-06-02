<?php
require_once __DIR__ . '/../connect.php';

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

    if ($action === 'update_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $isPopular = isset($_POST['is_popular']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($planId && $name) {
            $stmt = $pdo->prepare("UPDATE pricing_plans SET name = ?, price = ?, description = ?, features = ?, is_popular = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $price, $description, $features, $isPopular, $isActive, $planId]);
            $alert = ['type' => 'success', 'message' => 'Pricing plan updated successfully.'];
        }
    } elseif ($action === 'create_plan') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $features = trim($_POST['features'] ?? '');
        $isPopular = isset($_POST['is_popular']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO pricing_plans (name, price, description, features, is_popular, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $description, $features, $isPopular, $isActive]);
            $alert = ['type' => 'success', 'message' => 'Pricing plan created successfully.'];
        }
    } elseif ($action === 'delete_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        if ($planId) {
            $stmt = $pdo->prepare("DELETE FROM pricing_plans WHERE id = ?");
            $stmt->execute([$planId]);
            $alert = ['type' => 'success', 'message' => 'Pricing plan deleted successfully.'];
        }
    }
}

// Get all pricing plans
$stmt = $pdo->query("SELECT * FROM pricing_plans ORDER BY price ASC");
$plans = $stmt->fetchAll();

// Get subscriber counts per plan
$planCounts = [];
$stmt = $pdo->query("SELECT membership, COUNT(*) as count FROM users GROUP BY membership");
while ($row = $stmt->fetch()) {
    $planCounts[$row['membership']] = $row['count'];
}

// Set sidebar variables
$displayName = $adminUser['name'];
$adminEmail = $adminUser['email'];
$avatar = $adminUser['avatar'];

$pageTitle = 'Pricing Plans - Super Admin';
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
                    <strong>Pricing Plans</strong>
                </div>
            </header>

            <main class="superadmin-content">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div>
                        <h1>Pricing Plans</h1>
                        <p class="superadmin-subtitle">Manage subscription pricing plans and features.</p>
                    </div>
                    <button class="sa-btn sa-btn-primary" onclick="openCreateModal()">
                        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Plan
                    </button>
                </div>

                <?php if ($alert): ?>
                    <div class="sa-alert sa-alert-<?php echo $alert['type']; ?>">
                        <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </div>
                <?php endif; ?>

                <!-- Plans Grid -->
                <?php if (empty($plans)): ?>
                    <div class="sa-empty">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <p>No pricing plans found. Create your first plan to get started.</p>
                    </div>
                <?php else: ?>
                    <div class="sa-plans-grid">
                        <?php foreach ($plans as $plan): ?>
                            <div class="sa-plan-card <?php echo $plan['is_popular'] ? 'is-popular' : ''; ?> <?php echo !$plan['is_active'] ? 'is-inactive' : ''; ?>">
                                <?php if ($plan['is_popular']): ?>
                                    <span class="sa-plan-popular-badge">Most Popular</span>
                                <?php endif; ?>
                                
                                <div class="sa-plan-header">
                                    <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                                    <div class="sa-plan-price">
                                        <span class="sa-plan-currency">$</span>
                                        <span class="sa-plan-amount"><?php echo number_format($plan['price'], 0); ?></span>
                                        <span class="sa-plan-period">/month</span>
                                    </div>
                                    <p class="sa-plan-desc"><?php echo htmlspecialchars($plan['description']); ?></p>
                                </div>

                                <div class="sa-plan-features">
                                    <?php 
                                    $features = explode("\n", $plan['features']);
                                    foreach ($features as $feature):
                                        $feature = trim($feature);
                                        if (empty($feature)) continue;
                                    ?>
                                        <div class="sa-plan-feature">
                                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span><?php echo htmlspecialchars($feature); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="sa-plan-stats">
                                    <span><?php echo $planCounts[strtolower($plan['name'])] ?? 0; ?> subscribers</span>
                                    <span class="sa-plan-status <?php echo $plan['is_active'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>

                                <div class="sa-plan-actions">
                                    <button class="sa-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($plan)); ?>)">
                                        <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit
                                    </button>
                                    <button class="sa-btn sa-btn-danger" onclick="openDeleteModal(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>')">
                                        <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Create Plan Modal -->
    <div class="sa-modal" id="createModal">
        <div class="sa-modal-backdrop" onclick="closeModal('createModal')"></div>
        <div class="sa-modal-dialog">
            <div class="sa-modal-header">
                <h2>Create Pricing Plan</h2>
                <button class="sa-modal-close" onclick="closeModal('createModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_plan">
                <div class="sa-modal-body">
                    <div class="sa-field">
                        <label>Plan Name</label>
                        <input type="text" name="name" placeholder="e.g., Pro" required>
                    </div>
                    <div class="sa-field">
                        <label>Monthly Price ($)</label>
                        <input type="number" name="price" step="0.01" min="0" placeholder="29.00" required>
                    </div>
                    <div class="sa-field">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Brief description of this plan..."></textarea>
                    </div>
                    <div class="sa-field">
                        <label>Features (one per line)</label>
                        <textarea name="features" rows="5" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"></textarea>
                    </div>
                    <div class="sa-field" style="display:flex;gap:20px;">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="is_popular" value="1">
                            Mark as Popular
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            Active
                        </label>
                    </div>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-primary">Create Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div class="sa-modal" id="editModal">
        <div class="sa-modal-backdrop" onclick="closeModal('editModal')"></div>
        <div class="sa-modal-dialog">
            <div class="sa-modal-header">
                <h2>Edit Pricing Plan</h2>
                <button class="sa-modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_plan">
                <input type="hidden" name="plan_id" id="edit_plan_id">
                <div class="sa-modal-body">
                    <div class="sa-field">
                        <label>Plan Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="sa-field">
                        <label>Monthly Price ($)</label>
                        <input type="number" name="price" id="edit_price" step="0.01" min="0" required>
                    </div>
                    <div class="sa-field">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="2"></textarea>
                    </div>
                    <div class="sa-field">
                        <label>Features (one per line)</label>
                        <textarea name="features" id="edit_features" rows="5"></textarea>
                    </div>
                    <div class="sa-field" style="display:flex;gap:20px;">
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="is_popular" id="edit_is_popular" value="1">
                            Mark as Popular
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                            Active
                        </label>
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
                <h2>Delete Pricing Plan</h2>
                <button class="sa-modal-close" onclick="closeModal('deleteModal')">×</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_plan">
                <input type="hidden" name="plan_id" id="delete_plan_id">
                <div class="sa-modal-body">
                    <p style="margin:0;font-size:14px;color:#1f2730;">
                        Are you sure you want to delete the <strong id="delete_plan_name"></strong> plan?
                    </p>
                    <p style="margin:8px 0 0;font-size:13px;color:#e23b54;">
                        This action cannot be undone. Users on this plan will need to be reassigned.
                    </p>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-btn" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="sa-btn sa-btn-danger">Delete Plan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('is-open');
            document.body.classList.add('sa-modal-open');
        }

        function openEditModal(plan) {
            document.getElementById('edit_plan_id').value = plan.id;
            document.getElementById('edit_name').value = plan.name;
            document.getElementById('edit_price').value = plan.price;
            document.getElementById('edit_description').value = plan.description || '';
            document.getElementById('edit_features').value = plan.features || '';
            document.getElementById('edit_is_popular').checked = plan.is_popular == 1;
            document.getElementById('edit_is_active').checked = plan.is_active == 1;
            document.getElementById('editModal').classList.add('is-open');
            document.body.classList.add('sa-modal-open');
        }

        function openDeleteModal(planId, planName) {
            document.getElementById('delete_plan_id').value = planId;
            document.getElementById('delete_plan_name').textContent = planName;
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