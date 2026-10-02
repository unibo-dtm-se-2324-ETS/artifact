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

if ($msg == '' && isset($_POST['add_category'])) {
  $categoryName = trim(isset($_POST['category_name']) ? $_POST['category_name'] : '');
  if ($categoryName == '') {
    $msg = 'Category name is required.';
  } else {
    $exists = expense_fetch_one_assoc(expense_prepare_and_execute($con, "SELECT ID FROM tblcategories WHERE UserId=? AND CategoryName=? LIMIT 1", 'is', array($userid, $categoryName)));
    if ($exists) {
      $msg = 'This category already exists.';
    } else {
      $stmt = expense_prepare_and_execute($con, "INSERT INTO tblcategories (UserId, CategoryName) VALUES (?, ?)", 'is', array($userid, $categoryName));
      if ($stmt) {
        expense_close_statement($stmt);
        $success = 'Category added successfully.';
      } else {
        $msg = 'Unable to add category right now.';
      }
    }
  }
}

if ($msg == '' && isset($_POST['update_category'])) {
  $categoryId = (int)$_POST['category_id'];
  $categoryName = trim(isset($_POST['category_name']) ? $_POST['category_name'] : '');
  if ($categoryId <= 0 || $categoryName == '') {
    $msg = 'A valid category is required.';
  } else {
    $exists = expense_fetch_one_assoc(expense_prepare_and_execute($con, "SELECT ID FROM tblcategories WHERE UserId=? AND CategoryName=? AND ID<>? LIMIT 1", 'isi', array($userid, $categoryName, $categoryId)));
    if ($exists) {
      $msg = 'This category already exists.';
    } else {
      $stmt = expense_prepare_and_execute($con, "UPDATE tblcategories SET CategoryName=? WHERE ID=? AND UserId=?", 'sii', array($categoryName, $categoryId, $userid));
      if ($stmt) {
        expense_close_statement($stmt);
        $success = 'Category updated successfully.';
        $editCategory = null;
      } else {
        $msg = 'Unable to update this category.';
      }
    }
  }
}

if ($msg == '' && isset($_POST['delete_category'])) {
  $categoryId = (int)$_POST['category_id'];
  $expenseUse = expense_fetch_one_assoc(expense_prepare_and_execute($con, "SELECT COUNT(*) AS total FROM tblexpense WHERE UserId=? AND CategoryId=?", 'ii', array($userid, $categoryId)));
  $budgetUse = expense_fetch_one_assoc(expense_prepare_and_execute($con, "SELECT COUNT(*) AS total FROM tblbudgets WHERE UserId=? AND CategoryId=?", 'ii', array($userid, $categoryId)));
  if (($expenseUse && (int)$expenseUse['total'] > 0) || ($budgetUse && (int)$budgetUse['total'] > 0)) {
    $msg = 'This category is already used by expenses or budgets, so it cannot be deleted.';
  } else {
    $stmt = expense_prepare_and_execute($con, "DELETE FROM tblcategories WHERE ID=? AND UserId=?", 'ii', array($categoryId, $userid));
    if ($stmt) {
      expense_close_statement($stmt);
      $success = 'Category deleted successfully.';
      if ($editCategory && (int)$editCategory['ID'] === $categoryId) {
        $editCategory = null;
      }
    } else {
      $msg = 'Unable to delete this category.';
    }
  }
}

if ($msg == '' && isset($_POST['save_budget'])) {
  $categoryId = (int)$_POST['budget_category_id'];
  $selectedMonth = expense_month_key(isset($_POST['budget_month']) ? $_POST['budget_month'] . '-01' : null);
  $selectedCurrency = expense_selected_currency(isset($_POST['currency']) ? $_POST['currency'] : 'USD');
  $budgetAmount = trim(isset($_POST['budget_amount']) ? $_POST['budget_amount'] : '');

  if ($categoryId <= 0) {
    $msg = 'Please choose a category for the budget.';
  } elseif ($budgetAmount == '' || !is_numeric($budgetAmount) || (float)$budgetAmount <= 0) {
    $msg = 'Please enter a valid budget amount greater than zero.';
  } else {
    $category = expense_find_category_by_id($con, $userid, $categoryId);
    if (!$category) {
      $msg = 'The selected category is invalid.';
    } else {
      $existingBudget = expense_fetch_one_assoc(expense_prepare_and_execute($con, "SELECT ID FROM tblbudgets WHERE UserId=? AND CategoryId=? AND BudgetMonth=? AND Currency=? LIMIT 1", 'iiss', array($userid, $categoryId, $selectedMonth, $selectedCurrency)));
      $amount = (float)$budgetAmount;
      if ($existingBudget) {
        $stmt = expense_prepare_and_execute($con, "UPDATE tblbudgets SET BudgetAmount=? WHERE ID=? AND UserId=?", 'dii', array($amount, $existingBudget['ID'], $userid));
      } else {
        $stmt = expense_prepare_and_execute($con, "INSERT INTO tblbudgets (UserId, CategoryId, BudgetMonth, Currency, BudgetAmount) VALUES (?, ?, ?, ?, ?)", 'iissd', array($userid, $categoryId, $selectedMonth, $selectedCurrency, $amount));
      }

      if ($stmt) {
        expense_close_statement($stmt);
        $success = 'Budget saved successfully.';
      } else {
        $msg = 'Unable to save this budget.';
      }
    }
  }
}

if ($msg == '' && isset($_POST['delete_budget'])) {
  $budgetId = (int)$_POST['budget_id'];
  $stmt = expense_prepare_and_execute($con, "DELETE FROM tblbudgets WHERE ID=? AND UserId=?", 'ii', array($budgetId, $userid));
  if ($stmt) {
    expense_close_statement($stmt);
    $success = 'Budget deleted successfully.';
  } else {
    $msg = 'Unable to delete this budget.';
  }
}

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$categories = expense_fetch_all_assoc(
  expense_prepare_and_execute(
    $con,
    "SELECT c.ID, c.CategoryName,
      COALESCE(SUM(CASE WHEN e.ExpenseDate BETWEEN ? AND ? AND e.Currency=? THEN e.ExpenseCost ELSE 0 END), 0) AS month_spent,
      (
        SELECT b.BudgetAmount
        FROM tblbudgets b
        WHERE b.UserId=c.UserId AND b.CategoryId=c.ID AND b.BudgetMonth=? AND b.Currency=?
        LIMIT 1
      ) AS month_budget
     FROM tblcategories c
     LEFT JOIN tblexpense e ON e.CategoryId=c.ID AND e.UserId=c.UserId
     WHERE c.UserId=?
     GROUP BY c.ID, c.CategoryName
     ORDER BY c.CategoryName ASC",
    'sssssi',
    array($monthStart, $monthEnd, $selectedCurrency, $selectedMonth, $selectedCurrency, $userid)
  )
);

$budgets = expense_fetch_all_assoc(
  expense_prepare_and_execute(
    $con,
    "SELECT b.ID, b.BudgetMonth, b.Currency, b.BudgetAmount, c.CategoryName
     FROM tblbudgets b
     INNER JOIN tblcategories c ON c.ID=b.CategoryId AND c.UserId=b.UserId
     WHERE b.UserId=? AND b.BudgetMonth=? AND b.Currency=?
     ORDER BY c.CategoryName ASC",
    'iss',
    array($userid, $selectedMonth, $selectedCurrency)
  )
);
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
  <?php include_once('templates/header.php'); ?>
  <?php include_once('templates/sidebar.php'); ?>

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
    <?php include_once('templates/footer.php'); ?>
  </div>

  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.js"></script>
  <script src="assets/js/custom.js"></script>
</body>
</html>
