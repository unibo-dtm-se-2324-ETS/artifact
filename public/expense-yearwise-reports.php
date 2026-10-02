<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/report-helpers.php');
if (strlen($_SESSION['detsuid']==0)) {
  header('location:logout.php');
} else {
  $currencyOptions = report_currency_options();
  $selectedCurrency = isset($_GET['cur']) ? strtoupper($_GET['cur']) : 'USD';
  if (!in_array($selectedCurrency, $currencyOptions)) {
    $selectedCurrency = 'USD';
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
    .report-shell {
      padding-top: 24px;
      padding-bottom: 32px;
      background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
      min-height: 100vh;
    }
    .report-card {
      background: #fff;
      border: 1px solid #dbe4ee;
      border-radius: 18px;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
      overflow: hidden;
    }
    .report-card-head {
      padding: 24px 26px 10px;
    }
    .report-card-head h1 {
      margin: 0 0 8px;
      font-size: 28px;
      font-weight: 700;
      color: #0f172a;
    }
    .report-card-head p {
      margin: 0;
      color: #64748b;
    }
    .report-card-body {
      padding: 12px 26px 26px;
    }
    .report-form .form-control,
    .report-form .btn {
      height: 46px;
      border-radius: 12px;
    }
    .report-form .form-control {
      border: 1px solid #dbe4ee;
      box-shadow: none;
    }
    .report-form label {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #64748b;
    }
    .quick-note {
      margin-top: 18px;
      padding: 14px 16px;
      border-radius: 14px;
      background: #f8fafc;
      color: #475569;
    }
  </style>
</head>
<body>
  <?php include_once(__DIR__ . '/../templates/header.php');?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php');?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main report-shell">
    <div class="report-card">
      <div class="report-card-head">
        <h1>Yearly report</h1>
        <p>Review yearly totals to understand long-term spending trends.</p>
      </div>
      <div class="report-card-body">
        <form class="report-form" method="post" action="expense-yearwise-reports-detailed.php">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label for="fromyear">From year</label>
                <input class="form-control" type="number" id="fromyear" name="fromyear" required min="2000" max="2100" value="<?php echo date('Y') - 4; ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="toyear">To year</label>
                <input class="form-control" type="number" id="toyear" name="toyear" required min="2000" max="2100" value="<?php echo date('Y'); ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="currency">Currency</label>
                <select class="form-control" id="currency" name="currency">
                  <?php foreach ($currencyOptions as $currency) { ?>
                  <option value="<?php echo $currency; ?>" <?php if ($selectedCurrency == $currency) { echo 'selected'; } ?>><?php echo $currency; ?></option>
                  <?php } ?>
                </select>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">Generate report</button>
        </form>
        <div class="quick-note">
          Best for long-range review and annual budget analysis.
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/chart.min.js"></script>
  <script src="assets/js/chart-data.js"></script>
  <script src="assets/js/easypiechart.js"></script>
  <script src="assets/js/easypiechart-data.js"></script>
  <script src="assets/js/bootstrap-datepicker.js"></script>
  <script src="assets/js/custom.js"></script>
</body>
</html>
<?php } ?>
