<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

require_once __DIR__ . '/../DB/DB_connection.php';

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header("Location: admin-vehicle-entry.php");
    exit;
}

$success = '';
$error   = '';

/* HANDLE ARCHIVE - MOVED BEFORE USER FETCH */
if (isset($_GET['action']) && $_GET['action'] === 'archive') {
    try {
        // First check if user exists and is not already archived
        $checkResult = db_prepare(
            "SELECT id, archived FROM customers WHERE id = ?",
            [$userId]
        );
        
        if ($checkResult && db_num_rows($checkResult) === 1) {
            $userData = db_fetch_assoc($checkResult);
            
            if ($userData['archived']) {
                $error = "Customer is already archived!";
            } else {
                // Proceed with archiving
                $updateResult = db_prepare(
                    "UPDATE customers SET archived = true, archived_at = NOW() WHERE id = ? AND archived = false",
                    [$userId]
                );
                
                // Check if update was successful
                // Some db_prepare implementations return true on success
                if ($updateResult !== false) {
                    header("Location: admin-vehicle-entry.php?archived=success");
                    exit;
                } else {
                    $error = "Database error: Failed to archive customer!";
                }
            }
        } else {
            $error = "Customer not found!";
        }
    } catch (Exception $e) {
        $error = "Error archiving customer: " . $e->getMessage();
    }
}

/* HANDLE BALANCE RELOAD */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reload_balance'])) {
    $reloadAmount = $_POST['reload_amount'] ?? '';

    if (!is_numeric($reloadAmount) || intval($reloadAmount) <= 0) {
        $error = "Please enter a valid whole number greater than 0!";
    } else {
        $reloadAmount = intval($reloadAmount);

        $result = db_prepare(
            "UPDATE customers SET balance = balance + ? WHERE id = ? RETURNING balance",
            [$reloadAmount, $userId]
        );

        if ($result && db_num_rows($result) === 1) {
            header("Location: admin-view-user.php?id=$userId&reload=success");
            exit;
        } else {
            $error = "Failed to reload balance!";
        }
    }
}

if (isset($_GET['reload']) && $_GET['reload'] === 'success') {
    $success = "Balance reloaded successfully!";
}

/* FETCH USER */
$result = db_prepare("SELECT * FROM customers WHERE id = ?", [$userId]);
$user = db_fetch_assoc($result);

if (!$user) {
    header("Location: admin-vehicle-entry.php");
    exit;
}

/* FETCH PARKING HISTORY */
$historyResult = db_prepare(
    "SELECT * FROM parking_logs WHERE customer_id = ? ORDER BY entry_time DESC LIMIT 10",
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View User Details</title>
<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background: #f3f4f6;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
}

.admin-header {
  background: linear-gradient(90deg, #1e5bb8 0%, #1651c6 100%);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1.5rem 2.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.back-btn {
  background: transparent;
  border: 2px solid #fff;
  color: #fff;
  border-radius: 8px;
  padding: 0.6rem 1.5rem;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: all 0.3s ease;
}

.back-btn:hover {
  background: #fff;
  color: #1e5bb8;
  transform: translateY(-2px);
}

.admin-content {
  padding: 2.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.msg-success {
  background: #dcfce7;
  color: #16a34a;
  padding: 1rem 1.5rem;
  border-radius: 10px;
  margin-bottom: 1.5rem;
  font-weight: 600;
  border-left: 4px solid #16a34a;
}

.msg-error {
  background: #fee2e2;
  color: #dc2626;
  padding: 1rem 1.5rem;
  border-radius: 10px;
  margin-bottom: 1.5rem;
  font-weight: 600;
  border-left: 4px solid #dc2626;
}

.user-details-container {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  padding: 2.5rem;
  margin-bottom: 2rem;
}

.user-header {
  display: flex;
  align-items: center;
  gap: 2rem;
  margin-bottom: 2rem;
  padding-bottom: 2rem;
  border-bottom: 2px solid #f1f5f9;
}

.user-qr-code {
  width: 150px;
  height: 150px;
  object-fit: contain;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 0.5rem;
}

.user-basic-info h2 {
  color: #1e5bb8;
  font-size: 1.8rem;
  margin-bottom: 0.5rem;
}

.user-basic-info p {
  color: #64748b;
  font-size: 1rem;
  margin-bottom: 0.3rem;
}

.user-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

.detail-item {
  background: #f8fafc;
  padding: 1.5rem;
  border-radius: 12px;
  border-left: 4px solid #1e5bb8;
}

.detail-item label {
  display: block;
  font-size: 0.85rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
}

.detail-item .value {
  font-size: 1.1rem;
  color: #1a1a1a;
  font-weight: 600;
}

.balance-value {
  color: #059669;
  font-size: 1.5rem;
  font-weight: 700;
}

.reload-section {
  background: #f0f9ff;
  border: 2px solid #3b82f6;
  border-radius: 12px;
  padding: 2rem;
  margin-top: 2rem;
}

.reload-section h3 {
  color: #1e5bb8;
  font-size: 1.2rem;
  margin-bottom: 1rem;
}

.reload-form {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
}

.form-group {
  flex: 1;
  min-width: 200px;
}

.form-group label {
  display: block;
  font-size: 0.9rem;
  font-weight: 600;
  color: #475569;
  margin-bottom: 0.5rem;
}

.form-group input {
  width: 100%;
  padding: 0.8rem;
  border: 2px solid #cbd5e1;
  border-radius: 8px;
  font-size: 1rem;
}

.form-group input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn-reload {
  padding: 0.8rem 2rem;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-reload:hover {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  transform: translateY(-2px);
}

.action-buttons {
  display: flex;
  gap: 1rem;
  margin-top: 2rem;
}

.btn-archive {
  flex: 1;
  padding: 0.8rem 2rem;
  background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-archive:hover {
  background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
  transform: translateY(-2px);
}

.history-section {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  padding: 2.5rem;
}

.history-section h3 {
  color: #1e5bb8;
  font-size: 1.4rem;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f1f5f9;
}

.history-table-container {
  overflow-x: auto;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 600px;
}

.history-table thead {
  background: #f8fafc;
}

.history-table th {
  padding: 1rem 1.5rem;
  text-align: left;
  font-weight: 700;
  font-size: 0.9rem;
  color: #1a1a1a;
  text-transform: uppercase;
}

.history-table td {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
  font-size: 0.95rem;
  color: #4a5568;
}

.history-table tbody tr:hover {
  background: #f8fafc;
}

.empty-history {
  text-align: center;
  padding: 3rem;
  color: #9ca3af;
}

/* Modal */
.modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}

.modal-overlay.active {
  display: flex;
}

.modal-content {
  background: #ffffff;
  border-radius: 16px;
  padding: 2rem;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-content h3 {
  color: #dc2626;
  font-size: 1.4rem;
  margin-bottom: 1rem;
}

.modal-content p {
  color: #64748b;
  margin-bottom: 1.5rem;
  line-height: 1.6;
}

.modal-buttons {
  display: flex;
  gap: 1rem;
}

.btn-confirm,
.btn-cancel {
  flex: 1;
  padding: 0.8rem;
  border: none;
  border-radius: 8px;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-confirm {
  background: #dc2626;
  color: #fff;
}

.btn-confirm:hover {
  background: #b91c1c;
}

.btn-cancel {
  background: #e5e7eb;
  color: #1f2937;
}

.btn-cancel:hover {
  background: #d1d5db;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .admin-header {
    padding: 1.25rem 1.5rem;
    flex-direction: column;
    gap: 1rem;
  }

  .admin-content {
    padding: 1.5rem 1rem;
  }

  .user-header {
    flex-direction: column;
    text-align: center;
  }

  .user-qr-code {
    width: 130px;
    height: 130px;
  }

  .user-details-grid {
    grid-template-columns: 1fr;
  }

  .reload-form {
    flex-direction: column;
  }

  .form-group {
    width: 100%;
  }

  .btn-reload {
    width: 100%;
  }

  .action-buttons {
    flex-direction: column;
  }

  .btn-archive {
    width: 100%;
  }
}
</style>
</head>
<body>

<div class="admin-header">
  <a href="admin-vehicle-entry.php" class="back-btn">← Back to Vehicle Entry</a>
  <h1 style="margin: 0; font-size: 1.5rem;">User Details</h1>
</div>

<div class="admin-content">

  <?php if ($success): ?>
  <div class="msg-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="msg-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="user-details-container">

    <div class="user-header">
      <img
        src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($user['id'].'-'.$user['plate']) ?>"
        class="user-qr-code"
        alt="User QR Code">

      <div class="user-basic-info">
        <h2><?= htmlspecialchars($user['name']) ?></h2>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>User ID:</strong> #<?= $user['id'] ?></p>
        <p><strong>Member Since:</strong> <?= date('F d, Y', strtotime($user['created_at'])) ?></p>
      </div>
    </div>

    <div class="user-details-grid">
      <div class="detail-item">
        <label>Plate Number</label>
        <div class="value"><?= htmlspecialchars($user['plate']) ?></div>
      </div>

      <div class="detail-item">
        <label>Account Balance</label>
        <div class="balance-value">₱<?= number_format($user['balance'], 2) ?></div>
      </div>
    </div>

    <!-- RELOAD FORM -->
    <div class="reload-section">
      <h3>💳 Reload Customer Balance</h3>
      <form method="POST" class="reload-form">
        <div class="form-group">
          <label>Amount to Reload</label>
          <input type="number" name="reload_amount" min="1" step="1" required>
        </div>
        <button type="submit" name="reload_balance" class="btn-reload">Reload Balance</button>
      </form>
    </div>

    <!-- ARCHIVE BUTTON -->
    <div class="action-buttons">
      <button class="btn-archive" onclick="showArchiveModal(event, <?= $userId ?>)">
        📦 Archive Customer
      </button>
    </div>

  </div>

  <!-- PARKING HISTORY -->
  <div class="history-section">
    <h3>Recent Parking History</h3>

    <?php if (db_num_rows($historyResult) > 0): ?>
    <div class="history-table-container">
      <table class="history-table">
        <thead>
          <tr>
            <th>Plate</th>
            <th>Entry</th>
            <th>Exit</th>
            <th>Fee</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = db_fetch_assoc($historyResult)): ?>
          <tr>
            <td><?= htmlspecialchars($row['plate']) ?></td>
            <td><?= htmlspecialchars($row['entry_time']) ?></td>
            <td><?= htmlspecialchars($row['exit_time'] ?? '-') ?></td>
            <td>₱<?= number_format($row['fee'] ?? 0, 2) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="empty-history">No parking history found for this customer.</div>
    <?php endif; ?>

  </div>

</div>

<!-- Archive Modal -->
<div id="archiveModal" class="modal-overlay">
  <div class="modal-content">
    <h3>Archive Customer</h3>
    <p>Are you sure you want to archive this customer? This action will move them to the archived list.</p>
    <div class="modal-buttons">
      <button class="btn-confirm" onclick="confirmArchive()">Yes, Archive</button>
      <button class="btn-cancel" onclick="closeModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
let currentUserId = null;

function showArchiveModal(event, userId) {
  event.preventDefault();
  currentUserId = userId;
  document.getElementById('archiveModal').classList.add('active');
}

function closeModal() {
  document.getElementById('archiveModal').classList.remove('active');
  currentUserId = null;
}

function confirmArchive() {
  if (currentUserId) {
    window.location.href = `admin-view-user.php?id=${currentUserId}&action=archive`;
  }
}

// Close modal when clicking outside
document.getElementById('archiveModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeModal();
  }
});
</script>

</body>
</html>
