<?php
require __DIR__ . '/config_mysqli.php';
// เริ่ม session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
  header('Location: login.php'); exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function fetch_all($mysqli, $sql) {
  $res = $mysqli->query($sql);
  if (!$res) { return []; }
  $rows = [];
  while ($row = $res->fetch_assoc()) { $rows[] = $row; }
  $res->free();
  return $rows;
}

// Data fetching (ไม่มีการเปลี่ยนแปลง)
$monthly = fetch_all($mysqli, "SELECT ym, net_sales FROM v_monthly_sales");
$category = fetch_all($mysqli, "SELECT category, net_sales FROM v_sales_by_category");
$region = fetch_all($mysqli, "SELECT region, net_sales FROM v_sales_by_region");
$topProducts = fetch_all($mysqli, "SELECT product_name, qty_sold, net_sales FROM v_top_products");
$payment = fetch_all($mysqli, "SELECT payment_method, net_sales FROM v_payment_share");
$hourly = fetch_all($mysqli, "SELECT hour_of_day, net_sales FROM v_hourly_sales");
$newReturning = fetch_all($mysqli, "SELECT date_key, new_customer_sales, returning_sales FROM v_new_vs_returning ORDER BY date_key");
$kpis = fetch_all($mysqli, "
  SELECT
    (SELECT SUM(net_amount) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS sales_30d,
    (SELECT SUM(quantity)   FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS qty_30d,
    (SELECT COUNT(DISTINCT customer_id) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) AS buyers_30d
");
$kpi = $kpis ? $kpis[0] : ['sales_30d'=>0,'qty_30d'=>0,'buyers_30d'=>0];
function nf($n) { return number_format((float)$n, 2); }
?>


<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sales Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background:#f4f7fd; color:#2c3e50; }
.card { border-radius:1rem; box-shadow:0 8px 20px rgba(0,0,0,0.08); transition:0.3s; }
.card:hover { transform:translateY(-5px); }
.kpi-card .value { font-size:2rem; font-weight:700; }
.card-chart-fixed-height canvas { max-height:350px; }
</style>
</head>
<body class="p-3">
<div class="container-fluid">
<header class="d-flex justify-content-between align-items-center mb-4">
<h1 class="fs-3">Sales Dashboard</h1>
<a href="logout.php" class="btn btn-outline-primary btn-sm">Logout</a>
</header>


<div class="row g-4 mb-4">
<div class="col-lg-4 col-md-6">
<div class="card p-3 kpi-card text-center bg-primary text-white">
<div class="small">ยอดขาย 30 วัน</div>
<div class="value">฿<?= nf($kpi['sales_30d']) ?></div>
</div>
</div>
<div class="col-lg-4 col-md-6">
<div class="card p-3 kpi-card text-center bg-success text-white">
<div class="small">จำนวนชิ้นขาย 30 วัน</div>
<div class="value"><?= number_format((int)$kpi['qty_30d']) ?></div>
</div>
</div>
<div class="col-lg-4 col-md-12">
<div class="card p-3 kpi-card text-center bg-danger text-white">
<div class="small">จำนวนผู้ซื้อ 30 วัน</div>
<div class="value"><?= number_format((int)$kpi['buyers_30d']) ?></div>
</div>
</div>
</div>


<div class="row g-4">
<div class="col-lg-8"><div class="card p-3 card-chart-fixed-height"><canvas id="chartMonthly"></canvas></div></div>
<div class="col-lg-4"><div class="card p-3 card-chart-fixed-height"><canvas id="chartCategory"></canvas></div></div>
<div class="col-lg-6"><div class="card p-3 card-chart-fixed-height"><canvas id="chartTopProducts"></canvas></div></div>
<div class="col-lg-6"><div class="card p-3 card-chart-fixed-height"><canvas id="chartRegion"></canvas></div></div>
<div class="col-lg-6"><div class="card p-3 card-chart-fixed-height"><canvas id="chartPayment"></canvas></div></div>
<div class="col-lg-6"><div class="card p-3 card-chart-fixed-height"><canvas id="chartHourly"></canvas></div></div>
<div class="col-12"><div class="card p-3 card-chart-fixed-height"><canvas id="chartNewReturning"></canvas></div></div>
</div>
</div>


<script>
const monthlyData=<?= json_encode($monthly) ?>;
const categoryData=<?= json_encode($category) ?>;
const regionData=<?= json_encode($region) ?>;
const topProductsData=<?= json_encode($topProducts) ?>;
</html>