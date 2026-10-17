<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/expense-helpers.php');
include(__DIR__ . '/../src/category-service.php');

if (strlen($_SESSION['detsuid']) == 0) {
  header('location:logout.php');
  exit;
}

$userid = (int)$_SESSION['detsuid'];
$msg = '';
$success = '';
$currencyOptions = expense_currency_options();
$selectedMonth = expense_month_key(isset($_REQUEST['budget_month']) ? $_REQUEST['budget_month'] . '-01' : null);
$selectedCurrency = expense_selected_currency(isset($_REQUEST['currency']) ? $_REQUEST['currency'] : 'USD');

expense_ensure_schema($con);
expense_ensure_user_categories($con, $userid);
$csrfToken = expense_csrf_token();

$editCategory = null;
if (isset($_GET['editid'])) {
  $editCategory = expense_find_category_by_id($con, $userid, (int)$_GET['editid']);
}

if (isset($_POST['add_category']) || isset($_POST['update_category']) || isset($_POST['delete_category']) || isset($_POST['save_budget']) || isset($_POST['delete_budget'])) {
  if (!expense_verify_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $msg = 'Your session expired. Please try again.';
  }
}

$result = null;

if ($msg == '' && isset($_POST['add_category'])) {
  $result = category_add($con, $userid, isset($_POST['category_name']) ? $_POST['category_name'] : '');
}

if ($msg == '' && isset($_POST['update_category'])) {
  $result = category_rename($con, $userid, (int)$_POST['category_id'], isset($_POST['category_name']) ? $_POST['category_name'] : '');
  if ($result['ok']) {
    $editCategory = null;
  }
}

if ($msg == '' && isset($_POST['delete_category'])) {
  $categoryId = (int)$_POST['category_id'];
  $result = category_remove($con, $userid, $categoryId);
  if ($result['ok'] && $editCategory && (int)$editCategory['ID'] === $categoryId) {
    $editCategory = null;
  }
}

if ($msg == '' && isset($_POST['save_budget'])) {
  $selectedMonth = expense_month_key(isset($_POST['budget_month']) ? $_POST['budget_month'] . '-01' : null);
  $selectedCurrency = expense_selected_currency(isset($_POST['currency']) ? $_POST['currency'] : 'USD');
  $result = budget_save($con, $userid, (int)$_POST['budget_category_id'], $selectedMonth, $selectedCurrency, isset($_POST['budget_amount']) ? $_POST['budget_amount'] : '');
}

if ($msg == '' && isset($_POST['delete_budget'])) {
  $result = budget_remove($con, $userid, (int)$_POST['budget_id']);
}

if ($result !== null) {
  if ($result['ok']) {
    $success = $result['message'];
  } else {
    $msg = $result['message'];
  }
}

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$categories = category_overview($con, $userid, $selectedMonth, $selectedCurrency);
$budgets = budget_list($con, $userid, $selectedMonth, $selectedCurrency);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || Categories and Budgets</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <style>
    .budget-shell { padding-top: 24px; padding-bottom: 30px; background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%); min-height: 100vh; }
    .budget-card { background: #fff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); padding: 24px; margin-bottom: 22px; }
    .page-title { margin: 0 0 6px; font-size: 28px; font-weight: 700; color: #0f172a; }
    .page-copy { margin: 0; color: #64748b; }
    .table-clean > thead > tr > th, .table-clean > tbody > tr > td { padding: 14px 12px; border-top: 1px solid #e2e8f0; vertical-align: middle; }
    .table-clean > thead > tr > th { border-top: 0; color: #64748b; text-transform: uppercase; letter-spacing: .08em; font-size: 12px; }
    .metric-chip { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .metric-chip.safe { background: #dcfce7; color: #166534; }
    .metric-chip.warn { background: #fef3c7; color: #92400e; }
    .metric-chip.danger { background: #fee2e2; color: #b91c1c; }
    .inline-form { display: inline; }
    .empty-state { padding: 30px 16px; text-align: center; color: #64748b; }
  </style>
</head>
<body>
  <?php include_once(__DIR__ . '/../templates/header.php'); ?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php'); ?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main budget-shell">
    <div class="row">
      <div class="col-lg-12">
        <div class="budget-card">
          <ol class="breadcrumb">
            <li><a href="dashboard.php"><em class="fa fa-home"></em></a></li>
            <li class="active">Categories and Budgets</li>
          </ol>
          <h1 class="page-title">Categories and budgets</h1>
          <p class="page-copy">Organize your spending and define monthly limits that appear on the dashboard.</p>

          <?php if ($msg != '') { ?>
          <div class="alert alert-danger" style="margin-top:18px;"><?php echo expense_h($msg); ?></div>
          <?php } ?>
          <?php if ($success != '') { ?>
          <div class="alert alert-success" style="margin-top:18px;"><?php echo expense_h($success); ?></div>
          <?php } ?>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="budget-card">
          <h3 class="metric-label"><?php echo $editCategory ? 'Edit category' : 'Add category'; ?></h3>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
            <?php if ($editCategory) { ?>
            <input type="hidden" name="category_id" value="<?php echo (int)$editCategory['ID']; ?>">
            <?php } ?>
            <div class="form-group">
              <label for="category_name">Category Name</label>
              <input class="form-control" type="text" id="category_name" name="category_name" maxlength="100" required value="<?php echo $editCategory ? expense_h($editCategory['CategoryName']) : ''; ?>" placeholder="Food">
            </div>
            <button type="submit" class="btn btn-primary" name="<?php echo $editCategory ? 'update_category' : 'add_category'; ?>"><?php echo $editCategory ? 'Update Category' : 'Add Category'; ?></button>
            <?php if ($editCategory) { ?>
            <a href="manage-categories.php?budget_month=<?php echo expense_h($selectedMonth); ?>&currency=<?php echo expense_h($selectedCurrency); ?>" class="btn btn-default">Cancel</a>
            <?php } ?>
          </form>
        </div>

        <div class="budget-card">
          <h3 class="metric-label">Monthly budget</h3>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
            <div class="form-group">
              <label for="budget_month">Month</label>
              <input class="form-control" type="month" id="budget_month" name="budget_month" required value="<?php echo expense_h($selectedMonth); ?>">
            </div>
            <div class="form-group">
              <label for="currency">Currency</label>
              <select class="form-control" id="currency" name="currency" required>
                <?php foreach ($currencyOptions as $currency) { ?>
                <option value="<?php echo expense_h($currency); ?>" <?php if ($selectedCurrency === $currency) { echo 'selected'; } ?>><?php echo expense_h($currency); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group">
              <label for="budget_category_id">Category</label>
              <select class="form-control" id="budget_category_id" name="budget_category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $category) { ?>
                <option value="<?php echo (int)$category['ID']; ?>"><?php echo expense_h($category['CategoryName']); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="form-group">
              <label for="budget_amount">Budget Amount</label>
              <input class="form-control" type="number" min="0.01" step="0.01" id="budget_amount" name="budget_amount" required placeholder="0.00">
            </div>
            <button type="submit" class="btn btn-primary" name="save_budget">Save Budget</button>
          </form>
        </div>
      </div>

      <div class="col-md-7">
        <div class="budget-card">
          <form method="get" action="" class="row">
            <div class="col-sm-4">
              <div class="form-group">
                <label for="budget_month_view">View Month</label>
                <input class="form-control" type="month" id="budget_month_view" name="budget_month" value="<?php echo expense_h($selectedMonth); ?>">
              </div>
            </div>
            <div class="col-sm-4">
              <div class="form-group">
                <label for="currency_view">View Currency</label>
                <select class="form-control" id="currency_view" name="currency">
                  <?php foreach ($currencyOptions as $currency) { ?>
                  <option value="<?php echo expense_h($currency); ?>" <?php if ($selectedCurrency === $currency) { echo 'selected'; } ?>><?php echo expense_h($currency); ?></option>
                  <?php } ?>
                </select>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="form-group" style="margin-top:25px;">
                <button type="submit" class="btn btn-default">Refresh View</button>
              </div>
            </div>
          </form>

          <h3 class="metric-label">Category performance</h3>
          <div class="table-responsive">
            <table class="table table-clean">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Spent</th>
                  <th>Budget</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($categories) > 0) { foreach ($categories as $category) {
                  $spent = (float)$category['month_spent'];
                  $budget = (float)$category['month_budget'];
                  $progress = expense_budget_progress($spent, $budget);
                  $statusClass = 'safe';
                  $statusText = $budget > 0 ? number_format($progress, 0) . '% used' : 'No budget';
                  if ($budget > 0 && $progress >= 100) {
                    $statusClass = 'danger';
                    $statusText = 'Over budget';
                  } elseif ($budget > 0 && $progress >= 80) {
                    $statusClass = 'warn';
                    $statusText = number_format($progress, 0) . '% used';
                  }
                ?>
                <tr>
                  <td><?php echo expense_h($category['CategoryName']); ?></td>
                  <td><?php echo expense_h(expense_money($spent, $selectedCurrency)); ?></td>
                  <td><?php echo $budget > 0 ? expense_h(expense_money($budget, $selectedCurrency)) : '-'; ?></td>
                  <td><span class="metric-chip <?php echo $statusClass; ?>"><?php echo expense_h($statusText); ?></span></td>
                  <td>
                    <a class="btn btn-xs btn-info" href="manage-categories.php?editid=<?php echo (int)$category['ID']; ?>&budget_month=<?php echo expense_h($selectedMonth); ?>&currency=<?php echo expense_h($selectedCurrency); ?>"><em class="fa fa-pencil"></em> Edit</a>
                    <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this category?');">
                      <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
                      <input type="hidden" name="category_id" value="<?php echo (int)$category['ID']; ?>">
                      <button type="submit" class="btn btn-xs btn-danger" name="delete_category"><em class="fa fa-trash"></em> Delete</button>
                    </form>
                  </td>
                </tr>
                <?php } } else { ?>
                <tr><td colspan="5" class="empty-state">No categories found.</td></tr>
                <?php } ?>
              </tbody>
            </table>
          </div>

          <h3 class="metric-label" style="margin-top:22px;">Budgets for <?php echo expense_h(date('F Y', strtotime($monthStart))); ?> in <?php echo expense_h($selectedCurrency); ?></h3>
          <div class="table-responsive">
            <table class="table table-clean">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Budget</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($budgets) > 0) { foreach ($budgets as $budget) { ?>
                <tr>
                  <td><?php echo expense_h($budget['CategoryName']); ?></td>
                  <td><?php echo expense_h(expense_money($budget['BudgetAmount'], $budget['Currency'])); ?></td>
                  <td>
                    <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this budget?');">
                      <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
                      <input type="hidden" name="budget_id" value="<?php echo (int)$budget['ID']; ?>">
                      <button type="submit" class="btn btn-xs btn-danger" name="delete_budget"><em class="fa fa-trash"></em> Delete</button>
                    </form>
                  </td>
                </tr>
                <?php } } else { ?>
                <tr><td colspan="3" class="empty-state">No budgets set for this month and currency.</td></tr>
                <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php include_once(__DIR__ . '/../templates/footer.php'); ?>
  </div>

  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.js"></script>
  <script src="assets/js/custom.js"></script>
</body>
</html>
