<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/expense-helpers.php');

if (strlen($_SESSION['detsuid']) == 0) {
  header('location:logout.php');
  exit;
}

$userid = (int)$_SESSION['detsuid'];
$editid = isset($_GET['editid']) ? (int)$_GET['editid'] : 0;
$msg = '';

expense_ensure_schema($con);
expense_ensure_user_categories($con, $userid);
expense_process_recurring($con, $userid);
$csrfToken = expense_csrf_token();

$expense = expense_fetch_one_assoc(
  expense_prepare_and_execute(
    $con,
    "SELECT ID, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, ReceiptPath FROM tblexpense WHERE ID=? AND UserId=? LIMIT 1",
    'ii',
    array($editid, $userid)
  )
);

if (!$expense) {
  $msg = 'Invalid expense record.';
}

$form = array(
  'dateexpense' => $expense ? $expense['ExpenseDate'] : date('Y-m-d'),
  'item' => $expense ? $expense['ExpenseItem'] : '',
  'costitem' => $expense ? $expense['ExpenseCost'] : '',
  'currency' => $expense ? expense_selected_currency($expense['Currency']) : 'USD',
  'categoryid' => $expense && $expense['CategoryId'] ? (string)$expense['CategoryId'] : '',
  'notes' => $expense ? $expense['Notes'] : ''
);

if ($expense && isset($_POST['submit'])) {
  $form['dateexpense'] = isset($_POST['dateexpense']) ? trim($_POST['dateexpense']) : '';
  $form['item'] = isset($_POST['item']) ? trim($_POST['item']) : '';
  $form['costitem'] = isset($_POST['costitem']) ? trim($_POST['costitem']) : '';
  $form['currency'] = expense_selected_currency(isset($_POST['currency']) ? $_POST['currency'] : 'USD');
  $form['categoryid'] = isset($_POST['categoryid']) ? trim($_POST['categoryid']) : '';
  $form['notes'] = isset($_POST['notes']) ? trim($_POST['notes']) : '';
  $categoryId = (int)$form['categoryid'];
  $removeReceipt = isset($_POST['remove_receipt']) ? 1 : 0;

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
      $receiptPath = $expense['ReceiptPath'];
      if ($removeReceipt) {
        expense_delete_receipt_file($receiptPath);
        $receiptPath = '';
      }

      $upload = expense_handle_receipt_upload(isset($_FILES['receipt']) ? $_FILES['receipt'] : array(), $userid);
      if ($upload['error'] !== '') {
        $msg = $upload['error'];
      } elseif ($upload['path'] !== '') {
        if (!empty($receiptPath)) {
          expense_delete_receipt_file($receiptPath);
        }
        $receiptPath = $upload['path'];
      }

      if ($msg == '') {
        $cost = (float)$form['costitem'];
        $stmt = expense_prepare_and_execute(
          $con,
          "UPDATE tblexpense SET ExpenseDate=?, ExpenseItem=?, ExpenseCost=?, Currency=?, CategoryId=?, Notes=?, ReceiptPath=? WHERE ID=? AND UserId=?",
          'ssdsissii',
          array($form['dateexpense'], $form['item'], $cost, $form['currency'], $categoryId, $form['notes'], $receiptPath, $editid, $userid)
        );

        if ($stmt) {
          expense_close_statement($stmt);
          header('Location: manage-expense.php?status=updated');
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
$currencyOptions = expense_currency_options();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || Edit Expense</title>
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
    .receipt-box { padding: 12px 14px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; margin-bottom: 14px; }
  </style>
</head>
<body>
  <?php include_once(__DIR__ . '/../templates/header.php'); ?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php'); ?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main expense-shell">
    <div class="row">
      <div class="col-lg-12">
        <div class="expense-card">
          <ol class="breadcrumb">
            <li><a href="dashboard.php"><em class="fa fa-home"></em></a></li>
            <li><a href="manage-expense.php">Expenses</a></li>
            <li class="active">Edit Expense</li>
          </ol>

          <h1 class="page-title">Edit expense</h1>
          <p class="page-copy">Update the item, category, notes, receipt, amount, and currency.</p>

          <?php if ($msg != '') { ?>
          <div class="alert alert-danger"><?php echo expense_h($msg); ?></div>
          <?php } ?>

          <?php if ($expense) { ?>
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
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="costitem">Cost of Item</label>
                  <input class="form-control" type="number" min="0.01" step="0.01" id="costitem" name="costitem" required value="<?php echo expense_h($form['costitem']); ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="receipt">Replace Receipt</label>
                  <input class="form-control" type="file" id="receipt" name="receipt" accept=".jpg,.jpeg,.png,.pdf">
                </div>
              </div>
            </div>

            <?php if (!empty($expense['ReceiptPath'])) { ?>
            <div class="receipt-box">
              <div><strong>Current receipt:</strong> <a href="<?php echo expense_h($expense['ReceiptPath']); ?>" target="_blank">Open receipt</a></div>
              <label style="margin-top:8px;"><input type="checkbox" name="remove_receipt" value="1"> Remove current receipt</label>
            </div>
            <?php } ?>

            <div class="form-group">
              <label for="notes">Notes</label>
              <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Optional details about this expense"><?php echo expense_h($form['notes']); ?></textarea>
            </div>

            <div class="form-group">
              <button type="submit" class="btn btn-primary" name="submit">Update Expense</button>
              <a href="manage-expense.php" class="btn btn-default">Cancel</a>
            </div>
          </form>
          <?php } ?>
        </div>
      </div>
      <?php include_once(__DIR__ . '/../templates/footer.php'); ?>
    </div>
  </div>

  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <script src="assets/js/bootstrap-datepicker.js"></script>
  <script src="assets/js/custom.js"></script>
</body>
</html>
