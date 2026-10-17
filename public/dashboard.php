<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/expense-helpers.php');
include(__DIR__ . '/../src/dashboard-service.php');

if (strlen($_SESSION['detsuid']) == 0) {
  header('location:logout.php');
  exit;
}

$userid = (int)$_SESSION['detsuid'];
$currencyOptions = expense_currency_options();

expense_ensure_schema($con);
expense_ensure_user_categories($con, $userid);
expense_process_recurring($con, $userid);
$settings = expense_get_user_settings($con, $userid);
$selectedCurrency = expense_selected_currency(isset($_GET['cur']) ? $_GET['cur'] : $settings['DefaultCurrency']);

// Everything the page shows is calculated by the dashboard service; its values are used by the markup below.
extract(dashboard_build($con, $userid, $selectedCurrency));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker - Dashboard</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <style>
    .dashboard-shell { padding-top: 22px; padding-bottom: 30px; background: linear-gradient(180deg, #f7fafc 0%, #eef3f8 100%); min-height: 100vh; }
    .dashboard-hero, .dashboard-card { background: #ffffff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.06); }
    .dashboard-hero { padding: 28px; margin-bottom: 24px; background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color: #fff; border: 0; }
    .dashboard-hero h1 { margin: 0 0 8px; font-size: 30px; font-weight: 700; color: #ffffff; }
    .dashboard-hero p { margin: 0; color: rgba(255, 255, 255, 0.82); font-size: 14px; }
    .hero-meta { display: inline-block; margin-top: 16px; padding: 9px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.14); font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
    .currency-form { margin-top: 18px; }
    .currency-form .form-control, .currency-form .btn { height: 42px; border-radius: 12px; box-shadow: none; border: 0; }
    .currency-form .form-control { background: rgba(255, 255, 255, 0.94); color: #0f172a; }
    .currency-form .btn { background: #fff; color: #1d4ed8; font-weight: 700; }
    .dashboard-card { padding: 22px; margin-bottom: 22px; }
    .metric-card { padding: 20px; margin-bottom: 20px; position: relative; overflow: hidden; }
    .metric-card::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; border-radius: 4px 0 0 4px; background: var(--accent, #2563eb); }
    .metric-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .metric-icon { flex: 0 0 auto; width: 44px; height: 44px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; background: var(--accent, #2563eb); box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28); }
    .metric-chip { font-size: 11px; font-weight: 800; padding: 4px 9px; border-radius: 999px; white-space: nowrap; letter-spacing: .02em; }
    .metric-chip.up { background: #fee2e2; color: #b91c1c; }
    .metric-chip.down { background: #dcfce7; color: #166534; }
    .metric-chip.flat { background: #f1f5f9; color: #475569; }
    .metric-label { margin: 0 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
    .metric-value { margin: 0; font-size: 28px; font-weight: 700; color: #0f172a; letter-spacing: -.01em; }
    .metric-note { margin-top: 10px; color: #64748b; font-size: 13px; }
    .accent-blue { --accent: #2563eb; } .accent-teal { --accent: #0d9488; } .accent-indigo { --accent: #6366f1; } .accent-slate { --accent: #475569; }
    .accent-teal .metric-icon { box-shadow: 0 8px 18px rgba(13, 148, 136, 0.28); }
    .accent-indigo .metric-icon { box-shadow: 0 8px 18px rgba(99, 102, 241, 0.28); }
    .accent-slate .metric-icon { box-shadow: 0 8px 18px rgba(71, 85, 105, 0.26); }
    .dashboard-card, .metric-card { transition: transform .18s ease, box-shadow .18s ease; }
    .dashboard-card:hover, .metric-card:hover { transform: translateY(-3px); box-shadow: 0 18px 40px rgba(15, 23, 42, 0.10); }
    .section-title .fa { margin-right: 8px; color: #2563eb; }
    .section-title { margin: 0 0 6px; font-size: 18px; font-weight: 700; color: #0f172a; }
    .section-copy { margin: 0 0 18px; color: #64748b; font-size: 13px; }
    .chart-box { position: relative; height: 320px; width: 100%; overflow: hidden; }
    .chart-box canvas { display: block; width: 100% !important; max-width: 100%; height: 100% !important; }
    .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .quick-stat { padding: 16px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .quick-stat strong { display: block; font-size: 22px; color: #0f172a; }
    .quick-stat span { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
    .latest-expense { padding: 18px; border-radius: 16px; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border: 1px solid #dbeafe; }
    .latest-expense .item-name { margin: 0 0 8px; font-size: 22px; font-weight: 700; color: #0f172a; }
    .latest-expense .item-meta, .latest-expense .item-date, .latest-expense .item-category { margin: 0; color: #475569; }
    .donut-card { padding: 10px 8px 2px; border-radius: 18px; background: radial-gradient(circle at top, #f8fbff 0%, #ffffff 62%); }
    .doughnut-wrap { display: flex; align-items: center; justify-content: center; padding: 4px 0 0; }
    .donut-chart { position: relative; width: 220px; height: 220px; margin: 0 auto; }
    .donut-chart svg { width: 100%; height: 100%; transform: rotate(-90deg); filter: drop-shadow(0 12px 20px rgba(15, 23, 42, 0.08)); }
    .donut-track { fill: none; stroke: #e7eef7; stroke-width: 22; }
    .donut-segment { fill: none; stroke-width: 22; stroke-linecap: round; transition: opacity .2s ease, stroke-width .2s ease, filter .2s ease; animation: donut-grow .9s ease both; }
    .donut-chart:hover .donut-segment { opacity: .45; }
    .donut-chart .donut-segment:hover { opacity: 1; stroke-width: 26; filter: brightness(1.04); }
    .donut-center { position: absolute; left: 50%; top: 50%; width: 118px; height: 118px; margin-left: -59px; margin-top: -59px; border-radius: 50%; background: #ffffff; box-shadow: inset 0 0 0 1px #e2e8f0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 12px; }
    .donut-center-label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
    .donut-center-value { margin-top: 7px; font-size: 20px; font-weight: 700; line-height: 1.1; color: #0f172a; }
    .chart-legend { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px 12px; margin: 16px auto 0; padding: 0; list-style: none; max-width: 320px; }
    .chart-legend li { display: inline-flex; align-items: center; color: #475569; font-size: 12px; line-height: 1.4; padding: 4px 0; }
    .chart-legend .legend-dot { display: inline-block; width: 10px; height: 10px; margin-right: 6px; border-radius: 50%; vertical-align: middle; }
    .chart-legend .legend-value { margin-left: 6px; font-weight: 700; color: #0f172a; }
    .budget-summary { padding: 18px; border-radius: 16px; background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%); border: 1px solid #fed7aa; margin-bottom: 18px; }
    .budget-summary strong { display: block; font-size: 24px; color: #0f172a; }
    .budget-summary span { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9a3412; margin-bottom: 8px; }
    .budget-summary p { margin: 8px 0 0; color: #7c2d12; }
    .budget-list { display: grid; gap: 14px; }
    .budget-item { padding: 16px; border-radius: 16px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .budget-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
    .budget-name { font-weight: 700; color: #0f172a; }
    .budget-status { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .budget-status.safe { background: #dcfce7; color: #166534; }
    .budget-status.warn { background: #fef3c7; color: #92400e; }
    .budget-status.danger { background: #fee2e2; color: #b91c1c; }
    .budget-meta { display: flex; justify-content: space-between; gap: 12px; color: #475569; font-size: 13px; margin-bottom: 10px; }
    .budget-bar { height: 10px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
    .budget-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #18c7b8 0%, #2563eb 100%); }
    .budget-fill.warn { background: linear-gradient(90deg, #f59e0b 0%, #f97316 100%); }
    .budget-fill.danger { background: linear-gradient(90deg, #ef4444 0%, #b91c1c 100%); }
    .currency-table { margin-bottom: 0; }
    .currency-table > thead > tr > th, .currency-table > tbody > tr > td { border-top: 1px solid #e2e8f0; padding: 14px 12px; }
    .currency-table > thead > tr > th { border-top: 0; font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
    .empty-state { margin: 0; padding: 22px 0; text-align: center; color: #64748b; }
    @keyframes donut-grow { from { stroke-dasharray: 0 528; } }
    @media (max-width: 767px) {
      .dashboard-hero { padding: 22px; }
      .dashboard-hero h1 { font-size: 24px; }
      .metric-value { font-size: 24px; }
      .quick-grid { grid-template-columns: 1fr; }
      .chart-box { height: 240px; }
      .donut-chart { width: 190px; height: 190px; }
      .donut-center { width: 102px; height: 102px; margin-left: -51px; margin-top: -51px; }
      .donut-center-value { font-size: 16px; }
      .budget-head, .budget-meta { display: block; }
      .budget-meta span { display: block; margin-bottom: 4px; }
    }
  </style>
</head>
<body class="app-page dashboard-page">
  <?php include_once(__DIR__ . '/../templates/header.php'); ?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php'); ?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main dashboard-shell">
    <div class="dashboard-hero">
      <div class="row">
        <div class="col-md-7">
          <h1>Expense overview</h1>
          <p>Track spending, category mix, and monthly budget pressure in one place.</p>
          <div class="hero-meta"><?php echo expense_h($selectedMonthLabel); ?> | <?php echo expense_h($selectedCurrency); ?></div>
        </div>
        <div class="col-md-5">
          <form method="get" action="dashboard.php" class="currency-form form-inline text-right">
            <div class="form-group">
              <select name="cur" id="cur" class="form-control">
                <?php foreach ($currencyOptions as $currency) { ?>
                <option value="<?php echo expense_h($currency); ?>" <?php if ($selectedCurrency == $currency) { echo 'selected'; } ?>><?php echo expense_h($currency); ?></option>
                <?php } ?>
              </select>
            </div>
            <button type="submit" class="btn">Change Currency</button>
          </form>
        </div>
      </div>
    </div>

    <div class="row">
      <?php foreach ($metricCards as $card) { ?>
      <div class="col-sm-6 col-lg-3">
        <div class="dashboard-card metric-card <?php echo expense_h($card['accent']); ?>">
          <div class="metric-top">
            <span class="metric-icon"><i class="fa <?php echo expense_h($card['icon']); ?>"></i></span>
            <?php if ($card['chip'] !== null) { ?>
            <span class="metric-chip <?php echo dashboard_delta_class($card['chip']); ?>"><?php echo expense_h($card['chip']); ?></span>
            <?php } ?>
          </div>
          <p class="metric-label"><?php echo expense_h($card['label']); ?></p>
          <h3 class="metric-value"><?php echo expense_h(expense_money($card['value'], $selectedCurrency)); ?></h3>
          <p class="metric-note"><?php echo expense_h($card['note']); ?></p>
        </div>
      </div>
      <?php } ?>
    </div>

    <div class="row">
      <div class="col-md-8">
        <div class="dashboard-card">
          <h2 class="section-title"><i class="fa fa-line-chart"></i>Daily trend</h2>
          <p class="section-copy">Your spending pattern across <?php echo expense_h($selectedMonthLabel); ?>.</p>
          <div class="chart-box">
            <canvas id="dailyTrendChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="dashboard-card">
          <h2 class="section-title"><i class="fa fa-bolt"></i>Quick stats</h2>
          <p class="section-copy">Signals that help you read the month faster.</p>
          <div class="quick-grid">
            <?php foreach ($quickStats as $stat) { ?>
            <div class="quick-stat">
              <span><?php echo expense_h($stat['label']); ?></span>
              <strong><?php echo expense_h($stat['value']); ?></strong>
            </div>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="dashboard-card">
          <h2 class="section-title"><i class="fa fa-pie-chart"></i>Top categories</h2>
          <p class="section-copy">Highest spending categories this month.</p>
          <?php if (count($topCategorySegments) > 0) { ?>
          <div class="donut-card">
            <div class="doughnut-wrap">
              <div class="donut-chart">
                <svg viewBox="0 0 220 220" aria-hidden="true">
                  <circle class="donut-track" cx="110" cy="110" r="84"></circle>
                  <?php foreach ($topCategorySegments as $segment) { ?>
                  <circle class="donut-segment" cx="110" cy="110" r="84" stroke="<?php echo expense_h($segment['color']); ?>" stroke-dasharray="<?php echo $segment['dash']; ?> <?php echo $segment['gap']; ?>" stroke-dashoffset="<?php echo $segment['offset']; ?>">
                    <title><?php echo expense_h($segment['label']); ?>: <?php echo expense_h(expense_money($segment['value'], $selectedCurrency)); ?></title>
                  </circle>
                  <?php } ?>
                </svg>
                <div class="donut-center">
                  <span class="donut-center-label">Top Spend</span>
                  <span class="donut-center-value"><?php echo expense_h(expense_money($topCategoryTotal, $selectedCurrency)); ?></span>
                </div>
              </div>
            </div>
          </div>
          <ul class="chart-legend">
            <?php foreach ($topCategorySegments as $segment) { ?>
            <li>
              <span class="legend-dot" style="background: <?php echo expense_h($segment['color']); ?>;"></span>
              <?php echo expense_h($segment['label']); ?>
              <span class="legend-value"><?php echo expense_h(expense_money($segment['value'], $selectedCurrency)); ?></span>
            </li>
            <?php } ?>
          </ul>
          <?php } else { ?>
          <p class="empty-state">No category data yet for this month.</p>
          <?php } ?>
        </div>

        <div class="dashboard-card">
          <h2 class="section-title"><i class="fa fa-history"></i>Latest expense</h2>
          <p class="section-copy">Most recent entry in <?php echo expense_h($selectedCurrency); ?>.</p>
          <div class="latest-expense">
            <?php if ($latestExpense) { ?>
            <p class="item-name"><?php echo expense_h($latestExpense['ExpenseItem']); ?></p>
            <p class="item-meta"><?php echo expense_h(expense_money($latestExpense['ExpenseCost'], $selectedCurrency)); ?></p>
            <p class="item-category"><?php echo expense_h($latestExpense['CategoryName']); ?></p>
            <p class="item-date"><?php echo expense_h(date('F j, Y', strtotime($latestExpense['ExpenseDate']))); ?></p>
            <?php if (!empty($latestExpense['CreatedAt'])) { ?>
            <p class="item-date">Recorded at <?php echo expense_h(date('g:i A', strtotime($latestExpense['CreatedAt']))); ?></p>
            <?php } ?>
            <?php } else { ?>
            <p class="item-name">No expenses yet</p>
            <p class="item-meta">Add your first expense to start tracking.</p>
            <?php } ?>
          </div>
        </div>
      </div>

      <div class="col-md-7">
        <div class="dashboard-card">
          <h2 class="section-title"><i class="fa fa-tachometer"></i>Budget tracker</h2>
          <p class="section-copy">Monthly category budgets for <?php echo expense_h($selectedMonthLabel); ?> in <?php echo expense_h($selectedCurrency); ?>.</p>

          <div class="budget-summary">
            <span>Budget Health</span>
            <?php if ($budgetTotal > 0) { ?>
            <strong><?php echo expense_h(expense_money($budgetSpent, $selectedCurrency)); ?> / <?php echo expense_h(expense_money($budgetTotal, $selectedCurrency)); ?></strong>
            <p><?php echo $overBudgetCount > 0 ? expense_h($overBudgetCount . ' categories are over budget.') : expense_h(expense_money(max($budgetRemaining, 0), $selectedCurrency) . ' remaining this month.'); ?></p>
            <?php } else { ?>
            <strong>No budgets set</strong>
            <p><a href="manage-categories.php">Create monthly budgets</a> to start tracking category limits.</p>
            <?php } ?>
          </div>

          <?php if (count($budgetRows) > 0) { ?>
          <div class="budget-list">
            <?php foreach ($budgetRows as $row) {
              $fillClass = $row['status_class'] === 'danger' ? 'danger' : ($row['status_class'] === 'warn' ? 'warn' : '');
            ?>
            <div class="budget-item">
              <div class="budget-head">
                <div class="budget-name"><?php echo expense_h($row['CategoryName']); ?></div>
                <span class="budget-status <?php echo expense_h($row['status_class']); ?>"><?php echo expense_h($row['status_text']); ?></span>
              </div>
              <div class="budget-meta">
                <span>Spent: <?php echo expense_h(expense_money($row['spent'], $selectedCurrency)); ?></span>
                <span>Budget: <?php echo $row['budget'] > 0 ? expense_h(expense_money($row['budget'], $selectedCurrency)) : 'Not set'; ?></span>
                <span><?php echo $row['budget'] > 0 ? 'Remaining: ' . expense_h(expense_money($row['remaining'], $selectedCurrency)) : 'Set a budget'; ?></span>
              </div>
              <div class="budget-bar">
                <div class="budget-fill <?php echo expense_h($fillClass); ?>" style="width: <?php echo $row['budget'] > 0 ? number_format($row['progress'], 2, '.', '') : 0; ?>%;"></div>
              </div>
            </div>
            <?php } ?>
          </div>
          <?php } else { ?>
          <p class="empty-state">No budget activity yet. Add budgets or categorize more expenses.</p>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="dashboard-card">
          <h2 class="section-title"><i class="fa fa-exchange"></i>Totals by currency</h2>
          <p class="section-copy">Useful if you track expenses in more than one currency.</p>
          <?php if (count($currencyTotalsUsed) > 0) { ?>
          <div class="table-responsive">
            <table class="table currency-table">
              <thead>
                <tr>
                  <th>Currency</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($currencyTotalsUsed as $currency => $total) { ?>
                <tr>
                  <td><?php echo expense_h($currency); ?></td>
                  <td><?php echo expense_h(expense_money($total, $currency)); ?></td>
                </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
          <?php } else { ?>
          <p class="empty-state">No expenses recorded yet.</p>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>

  <?php include_once(__DIR__ . '/../templates/footer.php'); ?>
  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/chart.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.js"></script>
  <script src="assets/js/custom.js"></script>
  <script>
    (function () {
      var dayLabels = <?php echo json_encode($dayLabels); ?>;
      var dayValues = <?php echo json_encode($dayValues); ?>;
      var dailyCanvas = document.getElementById("dailyTrendChart");

      if (!dailyCanvas) return;

      new Chart(dailyCanvas.getContext("2d")).Line({
        labels: dayLabels,
        datasets: [{
          fillColor: "rgba(37, 99, 235, 0.14)",
          strokeColor: "rgba(37, 99, 235, 1)",
          pointColor: "rgba(37, 99, 235, 1)",
          pointStrokeColor: "#fff",
          data: dayValues
        }]
      }, {
        responsive: true,
        bezierCurve: false,
        scaleGridLineColor: "rgba(148,163,184,.18)",
        scaleFontColor: "#64748b"
      });
    })();
  </script>
</body>
</html>
