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
$currencyOptions = expense_currency_options();

expense_ensure_schema($con);
expense_ensure_user_categories($con, $userid);
expense_process_recurring($con, $userid);

$csrfToken = expense_csrf_token();
$settings = expense_get_user_settings($con, $userid);
$form = array(
  'dateexpense' => date('Y-m-d'),
  'item' => '',
  'costitem' => '',
  'currency' => $settings['DefaultCurrency'],
  'categoryid' => $settings['DefaultCategoryId'] ? (string)$settings['DefaultCategoryId'] : '',
  'notes' => ''
);

if (isset($_POST['submit'])) {
  $form['dateexpense'] = isset($_POST['dateexpense']) ? trim($_POST['dateexpense']) : date('Y-m-d');
  $form['item'] = isset($_POST['item']) ? trim($_POST['item']) : '';
  $form['costitem'] = isset($_POST['costitem']) ? trim($_POST['costitem']) : '';
  $form['currency'] = expense_selected_currency(isset($_POST['currency']) ? $_POST['currency'] : $settings['DefaultCurrency']);
  $form['categoryid'] = isset($_POST['categoryid']) ? trim($_POST['categoryid']) : '';
  $form['notes'] = isset($_POST['notes']) ? trim($_POST['notes']) : '';
  $categoryId = (int)$form['categoryid'];

  if (!expense_verify_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $msg = 'Your session expired. Please try again.';
  } elseif ($form['dateexpense'] == '' || !strtotime($form['dateexpense'])) {
    $msg = 'Please choose a valid expense date.';
  } elseif ($form['item'] == '') {
    $msg = 'Please select an item.';
  } elseif ($form['costitem'] == '' || !is_numeric($form['costitem']) || (float)$form['costitem'] <= 0) {
    $msg = 'Please enter a valid cost greater than zero.';
  } elseif ($categoryId <= 0) {
    $msg = 'Please choose a category.';
  } else {
    $category = expense_find_category_by_id($con, $userid, $categoryId);
    if (!$category) {
      $msg = 'The selected category is invalid.';
    } else {
      $upload = expense_handle_receipt_upload(isset($_FILES['receipt']) ? $_FILES['receipt'] : array(), $userid);
      if ($upload['error'] !== '') {
        $msg = $upload['error'];
      } else {
        $cost = (float)$form['costitem'];
        $receiptPath = $upload['path'];
        $stmt = expense_prepare_and_execute(
          $con,
          "INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, ReceiptPath) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
          'issdsiss',
          array($userid, $form['dateexpense'], $form['item'], $cost, $form['currency'], $categoryId, $form['notes'], $receiptPath)
        );

        if ($stmt) {
          expense_close_statement($stmt);
          header('Location: manage-expense.php?status=added');
          exit;
        }

        $msg = 'Something went wrong. Please try again.';
      }
    }
  }
}

$items = expense_fetch_all_assoc(
  expense_prepare_and_execute($con, "SELECT ItemName FROM tblitems WHERE UserId=? ORDER BY ItemName ASC", 'i', array($userid))
);
$categories = expense_get_categories($con, $userid);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || Add Expense</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <style>
    .expense-shell { padding-top: 24px; padding-bottom: 30px; background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%); min-height: 100vh; }
    .expense-card { background: #fff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); padding: 24px; }
    .page-title { margin: 0 0 6px; font-size: 28px; font-weight: 700; color: #0f172a; }
    .page-copy { margin: 0 0 22px; color: #64748b; }
    .helper-links { margin-top: 8px; color: #64748b; font-size: 12px; }
    .helper-links a { font-weight: 600; }
    .alert-inline { margin-bottom: 18px; }
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
            <li><a href="manage-expense.php">Expenses</a></li>
            <li class="active">Add Expense</li>
          </ol>

          <h1 class="page-title">Add expense</h1>
          <p class="page-copy">Capture the amount, category, note, receipt, and currency in one step.</p>

          <?php if ($msg != '') { ?>
          <div class="alert alert-danger alert-inline"><?php echo expense_h($msg); ?></div>
          <?php } ?>

          <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="dateexpense">Date of Expense</label>
                  <input class="form-control" type="date" id="dateexpense" name="dateexpense" required value="<?php echo expense_h($form['dateexpense']); ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="currency">Currency</label>
                  <select class="form-control" id="currency" name="currency" required>
                    <?php foreach ($currencyOptions as $currency) { ?>
                    <option value="<?php echo expense_h($currency); ?>" <?php if ($form['currency'] == $currency) { echo 'selected'; } ?>><?php echo expense_h($currency); ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="item">Item</label>
                  <select class="form-control" id="item" name="item" required>
                    <option value="">Select item</option>
                    <?php foreach ($items as $itemRow) { ?>
                    <option value="<?php echo expense_h($itemRow['ItemName']); ?>" <?php if ($form['item'] == $itemRow['ItemName']) { echo 'selected'; } ?>><?php echo expense_h($itemRow['ItemName']); ?></option>
                    <?php } ?>
                  </select>
                  <div class="helper-links">Missing an item? <a href="add-item.php">Add a new item</a>.</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="categoryid">Category</label>
                  <select class="form-control" id="categoryid" name="categoryid" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $category) { ?>
                    <option value="<?php echo (int)$category['ID']; ?>" <?php if ((string)$form['categoryid'] === (string)$category['ID']) { echo 'selected'; } ?>><?php echo expense_h($category['CategoryName']); ?></option>
                    <?php } ?>
                  </select>
                  <div class="helper-links">Need a new category or budget? <a href="manage-categories.php">Manage categories and budgets</a>.</div>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="costitem">Cost of Item</label>
                  <input class="form-control" type="number" min="0.01" step="0.01" id="costitem" name="costitem" required value="<?php echo expense_h($form['costitem']); ?>" placeholder="0.00">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="receipt">Receipt</label>
                  <input class="form-control" type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="notes">Notes</label>
              <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Optional details about this expense"><?php echo expense_h($form['notes']); ?></textarea>
            </div>

            <div class="form-group">
              <button type="submit" class="btn btn-primary" name="submit">Add Expense</button>
              <a href="manage-expense.php" class="btn btn-default">Cancel</a>
            </div>
          </form>
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
