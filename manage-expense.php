<?php
session_start();
error_reporting(0);
include('config/database.php');
include('src/expense-helpers.php');

if (strlen($_SESSION['detsuid']) == 0) {
  header('location:logout.php');
  exit;
}

$userid = (int)$_SESSION['detsuid'];
$msg = '';
$success = '';
$currencyOptions = expense_currency_options();

expense_ensure_schema($con);
expense_ensure_user_categories($con, $userid);
expense_process_recurring($con, $userid);
$csrfToken = expense_csrf_token();

if (isset($_GET['status'])) {
  if ($_GET['status'] === 'added') {
    $success = 'Expense added successfully.';
  } elseif ($_GET['status'] === 'updated') {
    $success = 'Expense updated successfully.';
  } elseif ($_GET['status'] === 'deleted') {
    $success = 'Expense deleted successfully.';
  }
}

if (isset($_POST['delete_expense'])) {
  $deleteId = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;
  if (!expense_verify_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $msg = 'Your session expired. Please try again.';
  } elseif ($deleteId <= 0) {
    $msg = 'Invalid expense selected.';
  } else {
    $expenseToDelete = expense_fetch_one_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT ReceiptPath FROM tblexpense WHERE ID=? AND UserId=? LIMIT 1",
        'ii',
        array($deleteId, $userid)
      )
    );

    $stmt = expense_prepare_and_execute($con, "DELETE FROM tblexpense WHERE ID=? AND UserId=?", 'ii', array($deleteId, $userid));
    if ($stmt) {
      expense_close_statement($stmt);
      if ($expenseToDelete && !empty($expenseToDelete['ReceiptPath'])) {
        expense_delete_receipt_file($expenseToDelete['ReceiptPath']);
      }
      header('Location: manage-expense.php?status=deleted');
      exit;
    }
    $msg = 'Unable to delete this expense right now.';
  }
}

$filters = array(
  'q' => isset($_GET['q']) ? trim($_GET['q']) : '',
  'currency' => isset($_GET['currency']) && $_GET['currency'] !== '' ? expense_selected_currency($_GET['currency']) : '',
  'categoryid' => isset($_GET['categoryid']) ? (int)$_GET['categoryid'] : 0,
  'window' => isset($_GET['window']) ? trim($_GET['window']) : '',
  'fromdate' => isset($_GET['fromdate']) ? trim($_GET['fromdate']) : '',
  'todate' => isset($_GET['todate']) ? trim($_GET['todate']) : '',
  'minamount' => isset($_GET['minamount']) ? trim($_GET['minamount']) : '',
  'maxamount' => isset($_GET['maxamount']) ? trim($_GET['maxamount']) : ''
);
if (!in_array($filters['window'], array('', 'today', 'yesterday', 'last24h'), true)) {
  $filters['window'] = '';
}

$categories = expense_get_categories($con, $userid);

$sql = "SELECT e.ID, e.ExpenseItem, e.ExpenseCost, e.ExpenseDate, e.Currency, e.CategoryId, e.Notes, e.ReceiptPath, e.CreatedAt, c.CategoryName
        FROM tblexpense e
        LEFT JOIN tblcategories c ON c.ID=e.CategoryId AND c.UserId=e.UserId
        WHERE e.UserId=?";
$types = 'i';
$params = array($userid);

if ($filters['q'] !== '') {
  $sql .= " AND (e.ExpenseItem LIKE ? OR e.Notes LIKE ?)";
  $types .= 'ss';
  $params[] = '%' . $filters['q'] . '%';
  $params[] = '%' . $filters['q'] . '%';
}
if ($filters['currency'] !== '') {
  $sql .= " AND e.Currency=?";
  $types .= 's';
  $params[] = $filters['currency'];
}
if ($filters['categoryid'] > 0) {
  $sql .= " AND e.CategoryId=?";
  $types .= 'i';
  $params[] = $filters['categoryid'];
}
if ($filters['window'] === 'today') {
  $sql .= " AND e.ExpenseDate=CURDATE()";
} elseif ($filters['window'] === 'yesterday') {
  $sql .= " AND e.ExpenseDate=DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($filters['window'] === 'last24h') {
  $sql .= " AND e.CreatedAt BETWEEN DATE_SUB(NOW(), INTERVAL 24 HOUR) AND NOW()";
}
if ($filters['fromdate'] !== '' && strtotime($filters['fromdate'])) {
  $sql .= " AND e.ExpenseDate>=?";
  $types .= 's';
  $params[] = $filters['fromdate'];
}
if ($filters['todate'] !== '' && strtotime($filters['todate'])) {
  $sql .= " AND e.ExpenseDate<=?";
  $types .= 's';
  $params[] = $filters['todate'];
}
if ($filters['minamount'] !== '' && is_numeric($filters['minamount'])) {
  $sql .= " AND e.ExpenseCost>=?";
  $types .= 'd';
  $params[] = (float)$filters['minamount'];
}
if ($filters['maxamount'] !== '' && is_numeric($filters['maxamount'])) {
  $sql .= " AND e.ExpenseCost<=?";
  $types .= 'd';
  $params[] = (float)$filters['maxamount'];
}

$sql .= " ORDER BY e.CreatedAt DESC, e.ID DESC";
$rows = expense_fetch_all_assoc(expense_prepare_and_execute($con, $sql, $types, $params));

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=expenses-' . date('Ymd-His') . '.csv');
  $output = fopen('php://output', 'w');
  fputcsv($output, array('Date', 'Recorded At', 'Item', 'Category', 'Amount', 'Currency', 'Notes', 'Receipt'));
  foreach ($rows as $row) {
    fputcsv($output, array(
      $row['ExpenseDate'],
      $row['CreatedAt'],
      $row['ExpenseItem'],
      $row['CategoryName'] ? $row['CategoryName'] : 'Uncategorized',
      number_format((float)$row['ExpenseCost'], 2, '.', ''),
      $row['Currency'],
      preg_replace('/\s+/', ' ', trim((string)$row['Notes'])),
      $row['ReceiptPath']
    ));
  }
  fclose($output);
  exit;
}

$totalRows = count($rows);
$filteredTotal = 0;
foreach ($rows as $row) {
  $filteredTotal += (float)$row['ExpenseCost'];
}
$summaryText = $filters['currency'] !== ''
  ? expense_money($filteredTotal, $filters['currency'])
  : number_format($filteredTotal, 2) . ' (mixed currencies)';
$summaryParts = array($totalRows . ' records', $summaryText);
if ($filters['window'] === 'today') {
  $summaryParts[] = 'for today';
} elseif ($filters['window'] === 'yesterday') {
  $summaryParts[] = 'for yesterday';
} elseif ($filters['window'] === 'last24h') {
  $summaryParts[] = 'created in the last 24 hours';
}

$queryString = $_GET;
$queryString['export'] = 'csv';
$exportLink = 'manage-expense.php?' . http_build_query($queryString);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || Manage Expense</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <style>
    .expense-shell { padding-top: 24px; padding-bottom: 30px; background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%); min-height: 100vh; }
    .expense-card { background: #fff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); padding: 24px; margin-bottom: 22px; }
    .page-title { margin: 0 0 6px; font-size: 28px; font-weight: 700; color: #0f172a; }
    .page-copy { margin: 0; color: #64748b; }
    .filters-row { margin-top: 24px; }
    .summary-chip { display: inline-block; margin-top: 16px; padding: 9px 14px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
    .table-clean > thead > tr > th, .table-clean > tbody > tr > td { padding: 14px 12px; border-top: 1px solid #e2e8f0; vertical-align: middle; }
    .table-clean > thead > tr > th { border-top: 0; color: #64748b; text-transform: uppercase; letter-spacing: .08em; font-size: 12px; }
    .category-pill { display: inline-block; padding: 5px 10px; border-radius: 999px; background: #f1f5f9; color: #334155; font-size: 12px; font-weight: 700; }
    .note-preview { color: #475569; max-width: 260px; white-space: normal; }
    .empty-state { padding: 36px 16px; text-align: center; color: #64748b; }
    .inline-form { display: inline; }
    .date-meta { display: block; margin-top: 4px; color: #64748b; font-size: 12px; }
  </style>
</head>
<body>
  <?php include_once('templates/header.php'); ?>
  <?php include_once('templates/sidebar.php'); ?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main expense-shell">
    <div class="row">
      <div class="col-lg-12">
        <div class="expense-card">
          <ol class="breadcrumb">
            <li><a href="dashboard.php"><em class="fa fa-home"></em></a></li>
            <li class="active">Manage Expenses</li>
          </ol>

          <div class="row">
            <div class="col-md-8">
              <h1 class="page-title">Manage expenses</h1>
              <p class="page-copy">Search, filter, export, and review every expense with category, notes, and receipt details.</p>
            </div>
            <div class="col-md-4 text-right">
              <a href="add-expense.php" class="btn btn-primary">Add Expense</a>
              <a href="<?php echo expense_h($exportLink); ?>" class="btn btn-default">Export CSV</a>
            </div>
          </div>

          <?php if ($msg != '') { ?>
          <div class="alert alert-danger" style="margin-top:18px;"><?php echo expense_h($msg); ?></div>
          <?php } ?>
          <?php if ($success != '') { ?>
          <div class="alert alert-success" style="margin-top:18px;"><?php echo expense_h($success); ?></div>
          <?php } ?>

          <form method="get" action="" class="filters-row">
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label for="q">Search Item / Notes</label>
                  <input class="form-control" type="text" id="q" name="q" value="<?php echo expense_h($filters['q']); ?>" placeholder="Groceries">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="currency">Currency</label>
                  <select class="form-control" id="currency" name="currency">
                    <option value="">All</option>
                    <?php foreach ($currencyOptions as $currency) { ?>
                    <option value="<?php echo expense_h($currency); ?>" <?php if ($filters['currency'] === $currency) { echo 'selected'; } ?>><?php echo expense_h($currency); ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label for="categoryid">Category</label>
                  <select class="form-control" id="categoryid" name="categoryid">
                    <option value="0">All</option>
                    <?php foreach ($categories as $category) { ?>
                    <option value="<?php echo (int)$category['ID']; ?>" <?php if ($filters['categoryid'] === (int)$category['ID']) { echo 'selected'; } ?>><?php echo expense_h($category['CategoryName']); ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="window">Time Window</label>
                  <select class="form-control" id="window" name="window">
                    <option value="">Any time</option>
                    <option value="today" <?php if ($filters['window'] === 'today') { echo 'selected'; } ?>>Today</option>
                    <option value="yesterday" <?php if ($filters['window'] === 'yesterday') { echo 'selected'; } ?>>Yesterday</option>
                    <option value="last24h" <?php if ($filters['window'] === 'last24h') { echo 'selected'; } ?>>Last 24 hours</option>
                  </select>
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="fromdate">From</label>
                  <input class="form-control" type="date" id="fromdate" name="fromdate" value="<?php echo expense_h($filters['fromdate']); ?>">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-2">
                <div class="form-group">
                  <label for="todate">To</label>
                  <input class="form-control" type="date" id="todate" name="todate" value="<?php echo expense_h($filters['todate']); ?>">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="minamount">Min Amount</label>
                  <input class="form-control" type="number" min="0" step="0.01" id="minamount" name="minamount" value="<?php echo expense_h($filters['minamount']); ?>">
                </div>
              </div>
              <div class="col-md-2">
                <div class="form-group">
                  <label for="maxamount">Max Amount</label>
                  <input class="form-control" type="number" min="0" step="0.01" id="maxamount" name="maxamount" value="<?php echo expense_h($filters['maxamount']); ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group" style="margin-top:25px;">
                  <button type="submit" class="btn btn-primary">Apply Filters</button>
                  <a href="manage-expense.php" class="btn btn-default">Reset</a>
                </div>
              </div>
            </div>
          </form>

          <div class="summary-chip"><?php echo expense_h(implode(' | ', $summaryParts)); ?></div>

          <div class="table-responsive" style="margin-top:22px;">
            <table class="table table-clean">
              <thead>
                <tr>
                  <th>S.NO</th>
                  <th>Expense Item</th>
                  <th>Category</th>
                  <th>Expense Cost</th>
                  <th>Expense Date</th>
                  <th>Notes</th>
                  <th>Receipt</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($totalRows > 0) { $cnt = 1; foreach ($rows as $row) { ?>
                <tr>
                  <td><?php echo $cnt; ?></td>
                  <td><?php echo expense_h($row['ExpenseItem']); ?></td>
                  <td><span class="category-pill"><?php echo expense_h($row['CategoryName'] ? $row['CategoryName'] : 'Uncategorized'); ?></span></td>
                  <td><?php echo expense_h(expense_money($row['ExpenseCost'], $row['Currency'] ? $row['Currency'] : 'USD')); ?></td>
                  <td>
                    <?php echo expense_h(date('F j, Y', strtotime($row['ExpenseDate']))); ?>
                    <?php if (!empty($row['CreatedAt'])) { ?>
                    <span class="date-meta">Recorded <?php echo expense_h(date('g:i A', strtotime($row['CreatedAt']))); ?></span>
                    <?php } ?>
                  </td>
                  <td class="note-preview"><?php echo $row['Notes'] !== '' ? expense_h($row['Notes']) : '-'; ?></td>
                  <td><?php if (!empty($row['ReceiptPath'])) { ?><a href="<?php echo expense_h($row['ReceiptPath']); ?>" target="_blank">Open</a><?php } else { echo '-'; } ?></td>
                  <td>
                    <a class="btn btn-xs btn-primary" href="edit-expense.php?editid=<?php echo (int)$row['ID']; ?>"><em class="fa fa-pencil"></em> Edit</a>
                    <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this expense?');">
                      <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
                      <input type="hidden" name="expense_id" value="<?php echo (int)$row['ID']; ?>">
                      <button type="submit" class="btn btn-xs btn-danger" name="delete_expense"><em class="fa fa-trash"></em> Delete</button>
                    </form>
                  </td>
                </tr>
                <?php $cnt++; } } else { ?>
                <tr>
                  <td colspan="8" class="empty-state">No expenses matched your filters.</td>
                </tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php include_once('templates/footer.php'); ?>
    </div>
  </div>

  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.js"></script>
  <script src="assets/js/custom.js"></script>
</body>
</html>
