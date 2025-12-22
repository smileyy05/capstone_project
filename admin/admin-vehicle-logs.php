<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin-login.php");
    exit;
}

require_once __DIR__ . '/../DB/DB_connection.php';

$table_check = db_query("SELECT EXISTS (
    SELECT FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name = 'parking_logs'
)");
$row = db_fetch_assoc($table_check);

if ($row['exists'] === 'f' || $row['exists'] === false) {
    db_query("CREATE TABLE parking_logs (
        id SERIAL PRIMARY KEY,
        customer_id INTEGER NULL,
        customer_name VARCHAR(100) NULL,
        plate VARCHAR(20) NULL,
        vehicle VARCHAR(50) NULL,
        entry_time TIMESTAMP,
        exit_time TIMESTAMP NULL,
        parking_fee NUMERIC(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    )");
}

$sql = "SELECT 
    pl.id,
    pl.customer_id,
    COALESCE(c.name, pl.customer_name) AS customer_name,
    COALESCE(c.plate, pl.plate) AS plate,
    COALESCE(c.vehicle, pl.vehicle) AS vehicle,
    pl.entry_time,
    pl.exit_time,
    pl.parking_fee
FROM parking_logs pl
LEFT JOIN customers c ON pl.customer_id = c.id
ORDER BY pl.entry_time DESC";

$result = db_query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

<title>Admin - Vehicle Logs</title>

<style>
/* ================= RESET ================= */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

/* ================= BODY ================= */
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
  background: #f3f4f6;
  overflow-x: hidden;
}

/* ================= LAYOUT ================= */
.admin-dashboard {
  display: flex;
  min-height: 100vh;
}

.admin-sidebar {
  width: 240px;
  background: linear-gradient(180deg, #1e5bb8, #1651c6);
  color: #fff;
  padding: 2rem 1.2rem;
  position: fixed;
  height: 100vh;
  overflow-y: auto;
  transition: transform .3s ease;
  z-index: 100;
}

.admin-main {
  margin-left: 240px;
  width: calc(100% - 240px);
}

/* ================= HEADER ================= */
.admin-header {
  background: linear-gradient(90deg, #1e5bb8, #1651c6);
  color: #fff;
  padding: 1.5rem 2rem;
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: space-between;
  align-items: center;
}

/* ================= TABLE ================= */
.logs-table-container {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0,0,0,.08);
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.logs-table {
  width: 100%;
  min-width: 900px;
  border-collapse: collapse;
}

.logs-table th,
.logs-table td {
  padding: 1rem;
  font-size: .95rem;
}

/* ================= MOBILE CARDS ================= */
.mobile-card-view {
  display: none;
}

.log-card {
  background: #fff;
  border-radius: 12px;
  padding: 1rem;
  margin-bottom: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

/* ================= MOBILE MENU ================= */
.mobile-menu-toggle {
  display: none;
  position: fixed;
  top: 1rem;
  left: 1rem;
  z-index: 101;
  font-size: 1.5rem;
  background: #1e5bb8;
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: .5rem .75rem;
}

/* ================= BREAKPOINTS ================= */

/* Tablets */
@media (max-width: 1024px) {
  .admin-sidebar { width: 200px; }
  .admin-main { margin-left: 200px; width: calc(100% - 200px); }
}

/* Small Tablets */
@media (max-width: 900px) {
  .logs-table th,
  .logs-table td {
    font-size: .85rem;
    padding: .75rem;
  }
}

/* Mobile */
@media (max-width: 767px) {
  .mobile-menu-toggle { display: block; }

  .admin-sidebar {
    transform: translateX(-100%);
    width: 280px;
  }

  .admin-sidebar.active {
    transform: translateX(0);
  }

  .admin-main {
    margin-left: 0;
    width: 100%;
  }

  .logs-table-container {
    display: none;
  }

  .mobile-card-view {
    display: block;
  }
}

/* Small Mobile */
@media (max-width: 480px) {
  .admin-header {
    padding-left: 4rem;
  }

  .log-card {
    padding: .9rem;
  }
}

/* Large Screens */
@media (min-width: 1600px) {
  .admin-content {
    max-width: 1400px;
    margin: auto;
  }
}
</style>
</head>

<body>

<button class="mobile-menu-toggle" onclick="toggleMobileMenu()">☰</button>

<div class="admin-dashboard">
  <aside class="admin-sidebar">
    <h3>SOUTHWOODS<br>MALL</h3>
  </aside>

  <main class="admin-main">
    <div class="admin-header">
      <h2>Vehicle Logs</h2>
      <input type="text" placeholder="Search logs...">
    </div>

    <div class="admin-content">
      <div class="logs-table-container">
        <!-- ORIGINAL TABLE UNCHANGED -->
      </div>

      <div class="mobile-card-view">
        <!-- ORIGINAL MOBILE CARDS UNCHANGED -->
      </div>
    </div>
  </main>
</div>

<script>
function toggleMobileMenu() {
  document.querySelector('.admin-sidebar').classList.toggle('active');
}
</script>

</body>
</html>
