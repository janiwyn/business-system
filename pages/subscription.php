<?php
include '../includes/db.php';
include '../includes/auth.php';
require_role(["super"]);
include '../pages/super_sidebar.php';
include '../includes/header.php';

// Handle subscription updates
if (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    $start = $_POST['subscription_start'];
    $end = $_POST['subscription_end'];
    $status = $_POST['subscription_status'];

    $stmt = $conn->prepare("UPDATE businesses SET subscription_start=?, subscription_end=?, subscription_status=? WHERE id=?");
    $stmt->bind_param("sssi", $start, $end, $status, $id);
    $stmt->execute();
}

// Expire subscriptions automatically
$conn->query("UPDATE businesses SET subscription_status='expired' WHERE subscription_end < CURDATE()");

// Search
$search = $_GET['q'] ?? '';
$search_sql = $search ? "AND (b.name LIKE '%$search%' OR u.email LIKE '%$search%')" : '';

$query = "
    SELECT 
        b.*,
        u.email AS admin_email,
        u.username AS admin_name
    FROM businesses b
    LEFT JOIN users u ON b.id = u.business_id AND u.role = 'admin'
    WHERE 1 $search_sql
    ORDER BY b.subscription_end DESC
";

$result = $conn->query($query);

?>

<div class="container mt-4">
    <h2 class="mb-4">Business Subscriptions</h2>

    <form method="GET" class="mb-3 row g-2">
        <div class="col-auto">
            <input type="text" name="q" class="form-control" placeholder="Search by business or email" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Business Name</th>
                            <th>Email</th>
                            <th>Subscription Start</th>
                            <th>Subscription End</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($b = $result->fetch_assoc()): 
                            $statusClass = match($b['subscription_status']) {
                                'active' => 'badge bg-success',
                                'pending' => 'badge bg-warning text-dark',
                                'expired' => 'badge bg-danger',
                                default => 'badge bg-secondary',
                            };
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($b['name']); ?></td>
                            <td><?= htmlspecialchars($b['admin_email'] ?? '—'); ?></td>

                            <td>
                                <form method="POST" class="d-flex flex-wrap align-items-center gap-2">
                                    <input type="hidden" name="id" value="<?= $b['id']; ?>">
                                    <input type="date" name="subscription_start" class="form-control form-control-sm" value="<?= $b['subscription_start']; ?>">
                            </td>
                            <td>
                                    <input type="date" name="subscription_end" class="form-control form-control-sm" value="<?= $b['subscription_end']; ?>">
                            </td>
                            <td>
                                    <select name="subscription_status" class="form-select form-select-sm">
                                        <option value="active" <?= $b['subscription_status']=='active'?'selected':''; ?>>Active</option>
                                        <option value="pending" <?= $b['subscription_status']=='pending'?'selected':''; ?>>Pending</option>
                                        <option value="expired" <?= $b['subscription_status']=='expired'?'selected':''; ?>>Expired</option>
                                    </select>
                            </td>
                            <td>
                                    <button type="submit" name="update" class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
