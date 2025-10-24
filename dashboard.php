<?php
// dashboard.php
session_start(); // เริ่ม session สำหรับ user_name

$DB_HOST = 'localhost';
$DB_USER = 's67160231';
$DB_PASS = 'vZ7168jH';
$DB_NAME = 's67160231';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) die('Database connect error');

$mysqli->set_charset('utf8mb4');

function fetch_all($mysqli, $sql) {
    $res = $mysqli->query($sql);
    if (!$res) return [];
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $res->free();
    return $rows;
}

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

function nf($n){ return number_format((float)$n,2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>🎃 Halloween Sales Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  body { background-color: #000; color: #ffcc33; font-family: 'Arial', sans-serif; }
  .navbar { background-color: #111 !important; }
  .card { background-color: #111; border: 2px solid #ff6600; border-radius: 1rem; }
  .card h5 { color: #ff9900; font-weight: 700; }
  .kpi { font-size: 1.5rem; font-weight: 700; color: #ff6600; }
  .sub { color: #ffcc66; font-size: .9rem; }
  canvas { max-height: 360px; background: #222; border-radius: .5rem; }
.grid { display: grid; gap: 1rem; grid-template-columns: repeat(12,1fr); }
.col-12 { grid-column: span 12; }
.col-6 { grid-column: span 6; }
.col-4 { grid-column: span 4; }
.col-8 { grid-column: span 8; }
@media (max-width: 991px){ .col-6,.col-4,.col-8{grid-column:span 12;} }
canvas { max-height: 360px; background: #222; border-radius: .5rem; }
</style>
</head>
<body class="p-3 p-md-4">
<nav class="navbar navbar-dark border-bottom mb-4">
<div class="container">
  <span class="navbar-brand">Halloween Dashboard</span>
  <div class="d-flex align-items-center gap-3">
    <span class="text-muted small">Hi, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></span>
    <a class="btn btn-outline-warning btn-sm" href="logout.php">Logout</a>
  </div>
</div>
</nav>

<div class="container-fluid">
<h2 class="mb-3">ยอดขาย (Retail DW) — Dashboard</h2>

<div class="grid mb-3">
  <div class="card p-3 col-4"><h5>ยอดขาย 30 วัน</h5><div class="kpi">฿<?= nf($kpi['sales_30d']) ?></div></div>
  <div class="card p-3 col-4"><h5>จำนวนชิ้นขาย 30 วัน</h5><div class="kpi"><?= number_format((int)$kpi['qty_30d']) ?> ชิ้น</div></div>
  <div class="card p-3 col-4"><h5>จำนวนผู้ซื้อ 30 วัน</h5><div class="kpi"><?= number_format((int)$kpi['buyers_30d']) ?> คน</div></div>
</div>

<div class="grid">
  <div class="card p-3 col-8"><h5>ยอดขายรายเดือน (2 ปี)</h5><canvas id="chartMonthly" height="360"></canvas></div>
  <div class="card p-3 col-4"><h5>สัดส่วนยอดขายตามหมวด</h5><canvas id="chartCategory" height="360"></canvas></div>
  <div class="card p-3 col-6"><h5>Top 10 สินค้าขายดี</h5><canvas id="chartTopProducts" height="360"></canvas></div>
  <div class="card p-3 col-6"><h5>ยอดขายตามภูมิภาค</h5><canvas id="chartRegion" height="360"></canvas></div>
  <div class="card p-3 col-6"><h5>วิธีการชำระเงิน</h5><canvas id="chartPayment" height="360"></canvas></div>
  <div class="card p-3 col-6"><h5>ยอดขายรายชั่วโมง</h5><canvas id="chartHourly" height="360"></canvas></div>
  <div class="card p-3 col-12"><h5>ลูกค้าใหม่ vs ลูกค้าเดิม (รายวัน)</h5><canvas id="chartNewReturning" height="360"></canvas></div>
</div>
</div>

<script>
const monthly = <?= json_encode($monthly, JSON_UNESCAPED_UNICODE) ?>;
const category = <?= json_encode($category, JSON_UNESCAPED_UNICODE) ?>;
const region = <?= json_encode($region, JSON_UNESCAPED_UNICODE) ?>;
const topProducts = <?= json_encode($topProducts, JSON_UNESCAPED_UNICODE) ?>;
const payment = <?= json_encode($payment, JSON_UNESCAPED_UNICODE) ?>;
const hourly = <?= json_encode($hourly, JSON_UNESCAPED_UNICODE) ?>;
const newReturning = <?= json_encode($newReturning, JSON_UNESCAPED_UNICODE) ?>;

const toXY = (arr,x,y)=>({labels:arr.map(o=>o[x]),values:arr.map(o=>parseFloat(o[y]))});

// Monthly
(()=>{ const {labels,values}=toXY(monthly,'ym','net_sales');
new Chart(document.getElementById('chartMonthly'),{type:'line',data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,tension:.25,fill:true,backgroundColor:'rgba(255,102,0,0.2)',borderColor:'#ff6600'}]},options:{plugins:{legend:{labels:{color:'#ff9900'}}},scales:{x:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}}}}})})();

// Category
(()=>{ const {labels,values}=toXY(category,'category','net_sales');
new Chart(document.getElementById('chartCategory'),{type:'doughnut',data:{labels,datasets:[{data:values,backgroundColor:['#ff6600','#ff9900','#cc6600','#993300','#660000','#ff3300']}]},options:{plugins:{legend:{position:'bottom',labels:{color:'#ff9900'}}}}})})();

// Top Products
(()=>{ const labels=topProducts.map(o=>o.product_name); const qty=topProducts.map(o=>parseInt(o.qty_sold));
new Chart(document.getElementById('chartTopProducts'),{type:'bar',data:{labels,datasets:[{label:'ชิ้นที่ขาย',data:qty,backgroundColor:'#ff6600'}]},options:{indexAxis:'y',plugins:{legend:{labels:{color:'#ff9900'}}},scales:{x:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}}}}})})();

// Region
(()=>{ const {labels,values}=toXY(region,'region','net_sales');
new Chart(document.getElementById('chartRegion'),{type:'bar',data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,backgroundColor:'#ff6600'}]},options:{plugins:{legend:{labels:{color:'#ff9900'}}},scales:{x:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}}}}})})();

// Payment
(()=>{ const {labels,values}=toXY(payment,'payment_method','net_sales');
new Chart(document.getElementById('chartPayment'),{type:'pie',data:{labels,datasets:[{data:values,backgroundColor:['#ff6600','#ff9900','#cc6600','#993300']}]},options:{plugins:{legend:{position:'bottom',labels:{color:'#ff9900'}}}}})})();

// Hourly
(()=>{ const {labels,values}=toXY(hourly,'hour_of_day','net_sales');
new Chart(document.getElementById('chartHourly'),{type:'bar',data:{labels,datasets:[{label:'ยอดขาย (฿)',data:values,backgroundColor:'#ff6600'}]},options:{plugins:{legend:{labels:{color:'#ff9900'}}},scales:{x:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}}}}})})();

// New vs Returning
(()=>{ const labels=newReturning.map(o=>o.date_key); const newC=newReturning.map(o=>parseFloat(o.new_customer_sales));
const retC=newReturning.map(o=>parseFloat(o.returning_sales));
new Chart(document.getElementById('chartNewReturning'),{type:'line',data:{labels,datasets:[{label:'ลูกค้าใหม่ (฿)',data:newC,tension:.25,fill:false,borderColor:'#ff6600'},{label:'ลูกค้าเดิม (฿)',data:retC,tension:.25,fill:false,borderColor:'#ff9900'}]},options:{plugins:{legend:{labels:{color:'#ff9900'}}},scales:{x:{ticks:{color:'#ff9900',maxTicksLimit:12},grid:{color:'rgba(255,255,255,.08)'}},y:{ticks:{color:'#ff9900'},grid:{color:'rgba(255,255,255,.08)'}}}}})})();
</script>

</body>
</html>
