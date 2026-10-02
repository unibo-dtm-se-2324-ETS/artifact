<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/report-helpers.php');
if (strlen($_SESSION['detsuid']==0)) {
  header('location:logout.php');
} else {
  $userid = $_SESSION['detsuid'];
  $currency = report_selected_currency('currency');
  $msg = '';
  $fromYear = isset($_POST['fromyear']) ? intval($_POST['fromyear']) : (isset($_GET['fromyear']) ? intval($_GET['fromyear']) : 0);
  $toYear = isset($_POST['toyear']) ? intval($_POST['toyear']) : (isset($_GET['toyear']) ? intval($_GET['toyear']) : 0);
  $rows = array();
  $labels = array();
  $values = array();
  $totalExpense = 0;
  $recordCount = 0;
  $topPeriodLabel = 'N/A';
  $topPeriodValue = 0;
  $fromDate = '';
  $toDate = '';
  $exportLink = '';
  $categoryRows = array();
  $categoryLabels = array();
  $categoryValues = array();

  $currencyColumn = mysqli_query($con, "SHOW COLUMNS FROM tblexpense LIKE 'Currency'");
  if (mysqli_num_rows($currencyColumn) == 0) {
    mysqli_query($con, "ALTER TABLE tblexpense ADD COLUMN Currency varchar(10) NOT NULL DEFAULT 'USD' AFTER ExpenseCost");
  }

  if ($fromYear == 0 || $toYear == 0) {
    $msg = 'Please choose both years.';
  } elseif ($fromYear > $toYear) {
    $msg = 'From year cannot be after To year.';
  } else {
    $fromDate = $fromYear . '-01-01';
    $toDate = $toYear . '-12-31';
    $query = mysqli_query($con, "SELECT YEAR(ExpenseDate) as reportyear, SUM(ExpenseCost) as totalamount FROM tblexpense WHERE UserId='$userid' AND Currency='$currency' AND ExpenseDate BETWEEN '$fromDate' AND '$toDate' GROUP BY YEAR(ExpenseDate) ORDER BY YEAR(ExpenseDate)");
    while ($row = mysqli_fetch_array($query)) {
      $rows[] = $row;
      $labels[] = (string)$row['reportyear'];
      $values[] = (float)$row['totalamount'];
      $totalExpense += (float)$row['totalamount'];
    }

    $recordCount = count($rows);
    $avgPeriodValue = $recordCount > 0 ? $totalExpense / $recordCount : 0;
    if ($recordCount > 0) {
      $topPeriodValue = max($values);
      foreach ($rows as $row) {
        if ((float)$row['totalamount'] === (float)$topPeriodValue) {
          $topPeriodLabel = $row['reportyear'];
          break;
        }
      }
    }

    $exportLink = 'expense-yearwise-reports-detailed.php?' . http_build_query(array(
      'fromyear' => $fromYear,
      'toyear' => $toYear,
      'currency' => $currency,
      'export' => 'csv'
    ));

    $catQuery = mysqli_query($con, "SELECT COALESCE(c.CategoryName, 'Uncategorized') as catname, SUM(e.ExpenseCost) as totalamount FROM tblexpense e LEFT JOIN tblcategories c ON e.CategoryId = c.ID WHERE e.UserId='$userid' AND e.Currency='$currency' AND e.ExpenseDate BETWEEN '$fromDate' AND '$toDate' GROUP BY e.CategoryId, c.CategoryName ORDER BY totalamount DESC");
    if ($catQuery) {
      while ($catRow = mysqli_fetch_array($catQuery)) {
        $categoryRows[] = $catRow;
        $categoryLabels[] = $catRow['catname'] ? $catRow['catname'] : 'Uncategorized';
        $categoryValues[] = (float)$catRow['totalamount'];
      }
    }
  }

  if ($msg == '' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=yearly-report-' . date('Ymd-His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Year', 'Total', 'Currency'));
    foreach ($rows as $row) {
      fputcsv($output, array(
        $row['reportyear'],
        number_format((float)$row['totalamount'], 2, '.', ''),
        $currency
      ));
    }
    fputcsv($output, array('Grand Total', number_format((float)$totalExpense, 2, '.', ''), $currency));
    fclose($output);
    exit;
  }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || Yearly Expense Report</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <style>
    .report-shell { padding-top: 24px; padding-bottom: 32px; background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%); min-height: 100vh; }
    .report-block { background: #fff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); padding: 24px; margin-bottom: 22px; }
    .report-title { margin: 0 0 6px; font-size: 28px; font-weight: 700; color: #0f172a; }
    .report-subtitle { margin: 0; color: #64748b; }
    .metric-card { padding: 20px; }
    .metric-label { margin: 0 0 8px; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; font-weight: 700; }
    .metric-value { margin: 0; font-size: 28px; font-weight: 700; color: #0f172a; }
    .chart-box { position: relative; height: 320px; overflow: hidden; }
    .chart-box canvas { display: block; width: 100% !important; height: 100% !important; }
    .table-clean > thead > tr > th, .table-clean > tbody > tr > td, .table-clean > tfoot > tr > th { padding: 14px 12px; border-top: 1px solid #e2e8f0; }
    .table-clean > thead > tr > th { border-top: 0; color: #64748b; text-transform: uppercase; letter-spacing: .08em; font-size: 12px; }
    .empty-state { padding: 36px 16px; text-align: center; color: #64748b; }
    .alert-lite { margin-top: 18px; padding: 14px 16px; border-radius: 14px; background: #fff7ed; color: #9a3412; }
    .toolbar-link { margin-top: 14px; display: inline-block; }
    @media (max-width: 767px) {
      .report-block { padding: 18px; }
      .report-title { font-size: 24px; }
      .metric-value { font-size: 22px; }
      .chart-box { height: 240px; }
    }
    @media print {
      #sidebar-collapse, .toolbar-link { display: none !important; }
      .report-shell { background: #fff; padding: 0; }
      .report-block { box-shadow: none; border-color: #ccc; }
    }
  </style>
</head>
<body>
  <?php include_once(__DIR__ . '/../templates/header.php');?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php');?>
  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main report-shell">
    <div class="report-block">
      <h1 class="report-title">Yearly report</h1>
      <p class="report-subtitle">Range: <strong><?php echo report_h((string)$fromYear); ?></strong> to <strong><?php echo report_h((string)$toYear); ?></strong> in <strong><?php echo report_h($currency); ?></strong></p>
      <a class="toolbar-link btn btn-default" href="expense-yearwise-reports.php?cur=<?php echo report_h($currency); ?>">Change filters</a>
      <?php if ($msg == '') { ?><a class="toolbar-link btn btn-primary" href="<?php echo report_h($exportLink); ?>">Export CSV</a> <button type="button" class="toolbar-link btn btn-default" onclick="window.print()">Print</button><?php } ?>
      <?php if ($msg != '') { ?>
      <div class="alert-lite"><?php echo report_h($msg); ?></div>
      <?php } ?>
    </div>

    <?php if ($msg == '') { ?>
    <div class="row">
      <div class="col-sm-6 col-md-3"><div class="report-block metric-card"><p class="metric-label">Total</p><h3 class="metric-value"><?php echo report_money($totalExpense, $currency); ?></h3></div></div>
      <div class="col-sm-6 col-md-3"><div class="report-block metric-card"><p class="metric-label">Years</p><h3 class="metric-value"><?php echo $recordCount; ?></h3></div></div>
      <div class="col-sm-6 col-md-3"><div class="report-block metric-card"><p class="metric-label">Highest Year</p><h3 class="metric-value"><?php echo report_money($topPeriodValue, $currency); ?></h3><p class="report-subtitle"><?php echo $topPeriodLabel; ?></p></div></div>
      <div class="col-sm-6 col-md-3"><div class="report-block metric-card"><p class="metric-label">Avg / Year</p><h3 class="metric-value"><?php echo report_money($avgPeriodValue, $currency); ?></h3></div></div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="report-block">
          <h3 class="metric-label">Trend</h3>
          <?php if ($recordCount > 0) { ?>
          <div class="chart-box" id="yearLineChartWrap"><canvas id="yearLineChart"></canvas></div>
          <?php } else { ?>
          <div class="empty-state">No yearly expenses found for this range.</div>
          <?php } ?>
        </div>
      </div>
      <div class="col-md-5">
        <div class="report-block">
          <h3 class="metric-label">Totals</h3>
          <?php if ($recordCount > 0) { ?>
          <div class="chart-box" id="yearBarChartWrap"><canvas id="yearBarChart"></canvas></div>
          <?php } else { ?>
          <div class="empty-state">Nothing to compare yet.</div>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="report-block">
      <h3 class="metric-label">Breakdown</h3>
      <div class="table-responsive">
        <table class="table table-clean">
          <thead>
            <tr><th>#</th><th>Year</th><th>Total</th></tr>
          </thead>
          <tbody>
            <?php if ($recordCount > 0) { $cnt = 1; foreach ($rows as $row) { ?>
            <tr>
              <td><?php echo $cnt; ?></td>
              <td><?php echo $row['reportyear']; ?></td>
              <td><?php echo report_money($row['totalamount'], $currency); ?></td>
            </tr>
            <?php $cnt++; } } else { ?>
            <tr><td colspan="3" class="empty-state">No records found.</td></tr>
            <?php } ?>
          </tbody>
          <?php if ($recordCount > 0) { ?>
          <tfoot>
            <tr><th colspan="2" style="text-align:center;">Grand Total</th><th><?php echo report_money($totalExpense, $currency); ?></th></tr>
          </tfoot>
          <?php } ?>
        </table>
      </div>
    </div>

    <?php if (count($categoryRows) > 0) { ?>
    <div class="report-block">
      <h3 class="metric-label">By Category</h3>
      <div class="row">
        <div class="col-md-5">
          <div class="chart-box" id="catPieChartWrap" style="height:260px"><canvas id="catPieChart"></canvas></div>
        </div>
        <div class="col-md-7">
          <div class="table-responsive">
            <table class="table table-clean">
              <thead><tr><th>Category</th><th>Total</th><th>%</th></tr></thead>
              <tbody>
                <?php foreach ($categoryRows as $catRow) { ?>
                <tr>
                  <td><?php echo report_h($catRow['catname']); ?></td>
                  <td><?php echo report_money($catRow['totalamount'], $currency); ?></td>
                  <td><?php echo $totalExpense > 0 ? number_format(((float)$catRow['totalamount'] / $totalExpense) * 100, 1) : '0.0'; ?>%</td>
                </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php } ?>

    <?php } ?>
  </div>
  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/chart.min.js"></script>
  <script src="assets/js/chart-data.js"></script>
  <script src="assets/js/bootstrap-datepicker.js"></script>
  <script src="assets/js/custom.js"></script>
  <script>
    (function () {
      var labels = <?php echo json_encode($labels); ?>;
      var values = <?php echo json_encode($values); ?>;
      var moneySuffix = <?php echo json_encode(' ' . report_currency_symbol($currency)); ?>;
      var isMobile = window.innerWidth < 768;
      if (!labels.length) return;

      function setChartHeight(id, count) {
        var box = document.getElementById(id);
        if (!box) return;
        if (isMobile) {
          box.style.height = "240px";
          return;
        }
        box.style.height = Math.min(420, Math.max(280, 220 + (count * 8))) + "px";
      }

      function compactMoney(value) {
        if (value >= 1000000) return (value / 1000000).toFixed(1) + "M" + moneySuffix;
        if (value >= 1000) return (value / 1000).toFixed(1) + "K" + moneySuffix;
        return value.toFixed(0) + moneySuffix;
      }

      function slimLabels(source) {
        if (!isMobile || source.length <= 6) return source;
        return source.map(function (label, index) {
          return index % 2 === 0 ? label : "";
        });
      }

      if (Chart.types.BarWithLabels === undefined) {
        Chart.types.Bar.extend({
          name: "BarWithLabels",
          draw: function () {
            Chart.types.Bar.prototype.draw.apply(this, arguments);
            var ctx = this.chart.ctx;
            ctx.font = (isMobile ? "9px" : "10px") + " Montserrat";
            ctx.fillStyle = "#334155";
            ctx.textAlign = "center";
            ctx.textBaseline = "bottom";
            this.datasets.forEach(function (dataset) {
              dataset.bars.forEach(function (bar) {
                var labelY = Math.max(bar.y - 8, isMobile ? 18 : 24);
                ctx.fillText(compactMoney(bar.value), bar.x, labelY);
              });
            });
          }
        });
      }

      setChartHeight("yearLineChartWrap", labels.length);
      setChartHeight("yearBarChartWrap", labels.length);

      new Chart(document.getElementById("yearLineChart").getContext("2d")).Line({
        labels: slimLabels(labels),
        datasets: [{ fillColor: "rgba(37,99,235,0.14)", strokeColor: "rgba(37,99,235,1)", pointColor: "rgba(37,99,235,1)", pointStrokeColor: "#fff", data: values }]
      }, {
        responsive: true,
        bezierCurve: false,
        pointDotRadius: isMobile ? 3 : 4,
        scaleGridLineColor: "rgba(148,163,184,.18)",
        scaleFontColor: "#64748b",
        scaleFontSize: isMobile ? 10 : 12
      });

      new Chart(document.getElementById("yearBarChart").getContext("2d")).BarWithLabels({
        labels: slimLabels(labels),
        datasets: [{ fillColor: "rgba(15,23,42,0.85)", strokeColor: "rgba(15,23,42,1)", highlightFill: "rgba(30,41,59,1)", highlightStroke: "rgba(30,41,59,1)", data: values }]
      }, {
        responsive: true,
        barShowStroke: false,
        barValueSpacing: isMobile ? 4 : 8,
        barDatasetSpacing: isMobile ? 2 : 4,
        scaleGridLineColor: "rgba(148,163,184,.14)",
        scaleLineColor: "rgba(148,163,184,.2)",
        scaleFontColor: "#64748b",
        scaleFontSize: isMobile ? 10 : 12,
        scaleOverride: false
      });
    })();

    (function () {
      var catLabels = <?php echo json_encode($categoryLabels); ?>;
      var catValues = <?php echo json_encode($categoryValues); ?>;
      if (!catLabels.length) return;
      var palette = ["#3b82f6","#10b981","#f59e0b","#ef4444","#8b5cf6","#ec4899","#06b6d4","#84cc16","#f97316","#6366f1"];
      var catData = catLabels.map(function (label, i) {
        return { value: catValues[i], color: palette[i % palette.length], highlight: palette[i % palette.length], label: label };
      });
      var catCanvas = document.getElementById("catPieChart");
      if (catCanvas) {
        new Chart(catCanvas.getContext("2d")).Doughnut(catData, { responsive: true, segmentShowStroke: false, animateScale: true });
      }
    })();
  </script>
</body>
</html>
<?php } ?>
