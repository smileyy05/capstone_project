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

/* =========================
   HANDLE BALANCE RELOAD (ADMIN)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reload_balance'])) {

    $reloadAmount = $_POST['reload_amount'] ?? '';

    // Validate: whole number only
    if (!is_numeric($reloadAmount) || intval($reloadAmount) <= 0) {
        $error = "Please enter a valid whole number greater than 0!";
    } else {

        $reloadAmount = intval($reloadAmount);

        $result = db_prepare(
            "UPDATE customers
             SET balance = balance + ?
             WHERE id = ?
             RETURNING balance",
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

/* =========================
   HANDLE ARCHIVE
========================= */
if (isset($_GET['action']) && $_GET['action'] === 'archive') {

    $result = db_prepare(
        "UPDATE customers
         SET archived = true, archived_at = NOW()
         WHERE id = ?",
        [$userId]
    );

    if ($result) {
        header("Location: admin-vehicle-entry.php?archived=success");
        exit;
    } else {
        $error = "Failed to archive customer!";
    }
}

/* =========================
   FETCH USER
========================= */
$result = db_prepare(
    "SELECT * FROM customers WHERE id = ?",
    [$userId]
);

$user = db_fetch_assoc($result);

if (!$user) {
    header("Location: admin-vehicle-entry.php");
    exit;
}

/* =========================
   FETCH PARKING HISTORY
========================= */
$historyResult = db_prepare(
    "SELECT * FROM parking_logs
     WHERE customer_id = ?
     ORDER BY entry_time DESC
     LIMIT 10",
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View User Details</title>
<style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      background: #f3f4f6;
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
      overflow-x: hidden;
    }
    
    .admin-dashboard {
      display: flex;
      min-height: 100vh;
    }
    
    /* Sidebar Styles */
    .admin-sidebar {
      background: linear-gradient(180deg, #1e5bb8 0%, #1651c6 100%);
      color: #fff;
      width: 240px;
      padding: 2rem 1.2rem;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 100;
      transition: transform 0.3s ease;
    }
    
    .admin-sidebar h3 {
      font-size: 1.4rem;
      margin-bottom: 2.5rem;
      color: #5eead4;
      letter-spacing: 0.5px;
      line-height: 1.2;
      font-weight: 700;
    }
    
    .admin-sidebar nav {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      width: 100%;
    }
    
    .admin-sidebar a {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      background: none;
      color: #fff;
      text-decoration: none;
      font-size: 1rem;
      padding: 0.875rem 1rem;
      border-radius: 10px;
      transition: all 0.2s ease;
      font-weight: 500;
    }
    
    .admin-sidebar a.active {
      background: #ffffff;
      color: #1e5bb8;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    
    .admin-sidebar a:hover:not(.active) {
      background: rgba(255, 255, 255, 0.15);
    }
    
    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
      display: none;
      position: fixed;
      top: 1.25rem;
      left: 1rem;
      z-index: 101;
      background: #1e5bb8;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 0.6rem 0.8rem;
      font-size: 1.5rem;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }
    
    .mobile-menu-toggle:active {
      transform: scale(0.95);
    }
    
    .overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 99;
    }
    
    /* Main Content */
    .admin-main {
      flex: 1;
      background: #f3f4f6;
      min-height: 100vh;
      margin-left: 240px;
      width: calc(100% - 240px);
    }
    
    .admin-header {
      background: linear-gradient(90deg, #1e5bb8 0%, #1651c6 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1.5rem 2.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      gap: 1rem;
      flex-wrap: wrap;
    }
    
    .admin-header .back-btn {
      background: transparent;
      border: 2px solid #fff;
      color: #fff;
      border-radius: 8px;
      padding: 0.6rem 1.5rem;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
      text-decoration: none;
      display: inline-block;
      white-space: nowrap;
    }
    
    .admin-header .back-btn:hover {
      background: #fff;
      color: #1e5bb8;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
    }
    
    .admin-header button {
      background: transparent;
      border: 2px solid #fff;
      color: #fff;
      border-radius: 8px;
      padding: 0.6rem 2rem;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
      white-space: nowrap;
    }
    
    .admin-header button:hover {
      background: #fff;
      color: #1e5bb8;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
    }
    
    .admin-content {
      padding: 2.5rem;
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
      flex-shrink: 0;
    }
    
    .user-basic-info {
      flex: 1;
      min-width: 0;
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
      letter-spacing: 0.5px;
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
      flex-wrap: wrap;
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
      transition: all 0.3s ease;
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
      letter-spacing: 0.5px;
      white-space: nowrap;
    }
    
    .btn-reload:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    
    .action-buttons {
      display: flex;
      gap: 1rem;
      margin-top: 2rem;
      flex-wrap: wrap;
    }
    
    .btn-archive {
      flex: 1;
      min-width: 200px;
      padding: 0.8rem 2rem;
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: #ffffff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }
    
    .btn-archive:hover {
      background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
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
      margin: 0 -1rem;
      padding: 0 1rem;
    }
    
    .history-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 600px;
    }
    
    .history-table thead {
      background: #f8fafc;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .history-table th {
      padding: 1rem 1.5rem;
      text-align: left;
      font-weight: 700;
      font-size: 0.9rem;
      color: #1a1a1a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
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
    
    .status-badge {
      padding: 0.4rem 0.8rem;
      border-radius: 6px;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
      display: inline-block;
      white-space: nowrap;
    }
    
    .status-active {
      background: #dcfce7;
      color: #166534;
    }
    
    .status-completed {
      background: #dbeafe;
      color: #1e40af;
    }
    
    .empty-history {
      text-align: center;
      padding: 3rem;
      color: #9ca3af;
    }
    
    /* Modal Styles */
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
      padding: 1rem;
    }
    
    .modal-overlay.active {
      display: flex;
    }
    
    .modal-content {
      background: #ffffff;
      border-radius: 16px;
      padding: 2rem;
      max-width: 500px;
      width: 100%;
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
      flex-wrap: wrap;
    }
    
    .btn-confirm,
    .btn-cancel {
      flex: 1;
      min-width: 120px;
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
    
    /* Tablet Styles (768px - 1024px) */
    @media (max-width: 1024px) {
      .admin-sidebar {
        width: 200px;
        padding: 1.5rem 1rem;
      }
      
      .admin-sidebar h3 {
        font-size: 1.2rem;
        margin-bottom: 2rem;
      }
      
      .admin-sidebar a {
        font-size: 0.95rem;
        padding: 0.75rem 0.875rem;
      }
      
      .admin-main {
        margin-left: 200px;
        width: calc(100% - 200px);
      }
      
      .admin-header {
        padding: 1.25rem 2rem;
      }
      
      .admin-content {
        padding: 2rem;
      }
      
      .user-details-container,
      .history-section {
        padding: 2rem;
      }
      
      .user-header {
        gap: 1.5rem;
      }
      
      .user-qr-code {
        width: 120px;
        height: 120px;
      }
      
      .user-basic-info h2 {
        font-size: 1.6rem;
      }
      
      .user-details-grid {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      }
    }
    
    /* Mobile Styles (up to 767px) */
    @media (max-width: 767px) {
      .mobile-menu-toggle {
        display: block;
      }
      
      .admin-sidebar {
        transform: translateX(-100%);
        width: 280px;
      }
      
      .admin-sidebar.active {
        transform: translateX(0);
      }
      
      .overlay.active {
        display: block;
      }
      
      .admin-main {
        margin-left: 0;
        width: 100%;
      }
      
      .admin-header {
        padding: 1rem 1.5rem 1rem 4.5rem;
        gap: 0.75rem;
      }
      
      .admin-header .back-btn,
      .admin-header button {
        font-size: 0.875rem;
        padding: 0.55rem 1.25rem;
      }
      
      .admin-content {
        padding: 1.5rem 1rem;
      }
      
      .user-details-container,
      .history-section {
        padding: 1.5rem;
        border-radius: 12px;
      }
      
      .user-header {
        flex-direction: column;
        text-align: center;
        gap: 1.5rem;
      }
      
      .user-qr-code {
        width: 130px;
        height: 130px;
      }
      
      .user-basic-info h2 {
        font-size: 1.4rem;
      }
      
      .user-basic-info p {
        font-size: 0.95rem;
      }
      
      .user-details-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      
      .detail-item {
        padding: 1.25rem;
      }
      
      .reload-section {
        padding: 1.5rem;
      }
      
      .reload-section h3 {
        font-size: 1.1rem;
      }
      
      .reload-form {
        flex-direction: column;
        gap: 1rem;
      }
      
      .form-group {
        width: 100%;
        min-width: unset;
      }
      
      .btn-reload {
        width: 100%;
      }
      
      .action-buttons {
        flex-direction: column;
      }
      
      .btn-archive {
        width: 100%;
        min-width: unset;
      }
      
      .history-section h3 {
        font-size: 1.25rem;
      }
      
      .history-table th,
      .history-table td {
        padding: 0.875rem 1rem;
        font-size: 0.85rem;
      }
    }
    
    /* Small Mobile Styles (up to 480px) */
    @media (max-width: 480px) {
      .admin-sidebar {
        width: 260px;
        padding: 1.25rem 0.875rem;
      }
      
      .admin-sidebar h3 {
        font-size: 1.1rem;
        margin-bottom: 1.75rem;
      }
      
      .admin-sidebar a {
        font-size: 0.9rem;
        padding: 0.7rem 0.75rem;
      }
      
      .admin-header {
        padding: 0.875rem 1rem 0.875rem 4rem;
      }
      
      .admin-header .back-btn,
      .admin-header button {
        font-size: 0.8rem;
        padding: 0.5rem 1rem;
      }
      
      .admin-content {
        padding: 1.25rem 0.875rem;
      }
      
      .user-details-container,
      .history-section {
        padding: 1.25rem;
      }
      
      .user-qr-code {
        width: 110px;
        height: 110px;
      }
      
      .user-basic-info h2 {
        font-size: 1.25rem;
      }
      
      .user-basic-info p {
        font-size: 0.875rem;
      }
      
      .detail-item {
        padding: 1rem;
      }
      
      .detail-item .value {
        font-size: 1rem;
      }
      
      .balance-value {
        font-size: 1.3rem;
      }
      
      .reload-section {
        padding: 1.25rem;
      }
      
      .reload-section h3 {
        font-size: 1rem;
      }
      
      .form-group input,
      .btn-reload {
        font-size: 0.95rem;
      }
      
      .btn-archive {
        font-size: 0.95rem;
      }
      
      .history-section h3 {
        font-size: 1.15rem;
      }
      
      .history-table {
        min-width: 550px;
      }
      
      .history-table th,
      .history-table td {
        padding: 0.75rem 0.875rem;
        font-size: 0.8rem;
      }
      
      .modal-content {
        padding: 1.5rem;
      }
      
      .modal-content h3 {
        font-size: 1.2rem;
      }
      
      .modal-content p {
        font-size: 0.95rem;
      }
      
      .btn-confirm,
      .btn-cancel {
        font-size: 0.95rem;
        padding: 0.7rem;
      }
    }
    
    /* Extra Small Mobile (up to 360px) */
    @media (max-width: 360px) {
      .admin-sidebar {
        width: 240px;
      }
      
      .mobile-menu-toggle {
        top: 1rem;
        left: 0.75rem;
        padding: 0.5rem 0.7rem;
        font-size: 1.3rem;
      }
      
      .admin-header {
        padding: 0.75rem 0.875rem 0.75rem 3.75rem;
      }
      
      .admin-content {
        padding: 1rem 0.75rem;
      }
      
      .user-details-container,
      .history-section {
        padding: 1rem;
      }
      
      .user-qr-code {
        width: 100px;
        height: 100px;
      }
      
      .user-basic-info h2 {
        font-size: 1.15rem;
      }
      
      .modal-buttons {
        flex-direction: column;
      }
      
      .btn-confirm,
      .btn-cancel {
        width: 100%;
      }
    }
    
    /* Landscape Mobile */
    @media (max-height: 500px) and (orientation: landscape) {
      .admin-sidebar {
        padding: 1rem 0.875rem;
      }
      
      .admin-sidebar h3 {
        margin-bottom: 1rem;
        font-size: 1rem;
      }
      
      .admin-sidebar a {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
      }
      
      .user-header {
        flex-direction: row;
        text-align: left;
      }
      
      .user-qr-code {
        width: 100px;
        height: 100px;
      }
    }
  </style>
</head>
<body>

<div class="admin-dashboard">
<main class="admin-main">

<div class="admin-header">
<a href="admin-vehicle-entry.php" class="back-btn">← Back to Vehicle Entry</a>
<h1 style="margin: 0; font-size: 1.5rem; flex: 1;">User Details</h1>
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
<label>Vehicle Type</label>
<div class="value"><?= htmlspecialchars($user['vehicle_type']) ?></div>
</div>

<div class="detail-item">
<label>Account Balance</label>
<div class="balance-value">
₱<?= number_format($user['balance'], 2) ?>
</div>
</div>

<div class="detail-item">
<label>Contact Number</label>
<div class="value"><?= htmlspecialchars($user['contact'] ?? 'N/A') ?></div>
</div>

</div>

<!-- =========================
     RELOAD FORM (ADMIN)
========================= -->
<div class="reload-section">
<h3>💳 Reload Customer Balance</h3>

<form method="POST" class="reload-form">
<div class="form-group">
<label>Amount to Reload</label>
<input
type="number"
name="reload_amount"
min="1"
step="1"
required
>
</div>

<button
type="submit"
name="reload_balance"
class="btn-reload"
>
Reload Balance
</button>
</form>
</div>

<!-- =========================
     ARCHIVE BUTTON
========================= -->
<div class="action-buttons">
<a
href="admin-view-user.php?id=<?= $userId ?>&action=archive"
class="btn-archive"
onclick="return confirm('Archive this customer?')"
>
📦 Archive Customer
</a>
</div>

</div>

<!-- =========================
     PARKING HISTORY
========================= -->
<div class="history-section">
<h3>Recent Parking History</h3>

<?php if (db_num_rows($historyResult) > 0): ?>
<table>
<tr>
<th>Plate</th>
<th>Entry</th>
<th>Exit</th>
<th>Fee</th>
</tr>

<?php while ($row = db_fetch_assoc($historyResult)): ?>
<tr>
<td><?= htmlspecialchars($row['plate']) ?></td>
<td><?= htmlspecialchars($row['entry_time']) ?></td>
<td><?= htmlspecialchars($row['exit_time'] ?? '-') ?></td>
<td>₱<?= number_format($row['fee'] ?? 0, 2) ?></td>
</tr>
<?php endwhile; ?>
</table>
<?php else: ?>
<p>No parking history found for this customer.</p>
<?php endif; ?>

</div>

</main>
</div>

</body>
</html>
