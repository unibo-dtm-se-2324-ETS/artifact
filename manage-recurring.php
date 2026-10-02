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
$categories = expense_get_categories($con, $userid);
$items = expense_fetch_all_assoc(expense_prepare_and_execute($con, "SELECT ItemName FROM tblitems WHERE UserId=? ORDER BY ItemName ASC", 'i', array($userid)));

$editRecurring = null;
if (isset($_GET['editid'])) {
  $editRecurring = expense_fetch_one_assoc(expense_prepare_and_execute($con, "SELECT * FROM tblrecurring WHERE ID=? AND UserId=? LIMIT 1", 'ii', array((int)$_GET['editid'], $userid)));
}

$form = array(
  'item' => $editRecurring ? $editRecurring['ExpenseItem'] : '',
  'cost' => $editRecurring ? $editRecurring['ExpenseCost'] : '',
  'currency' => $editRecurring ? expense_selected_currency($editRecurring['Currency']) : 'USD',
  'categoryid' => $editRecurring ? (string)$editRecurring['CategoryId'] : '',
  'frequency' => $editRecurring ? $editRecurring['Frequency'] : 'monthly',
  'startdate' => $editRecurring ? $editRecurring['StartDate'] : date('Y-m-d'),
  'notes' => $editRecurring ? $editRecurring['Notes'] : ''
);

if (isset($_POST['save_recurring']) || isset($_POST['delete_recurring']) || isset($_POST['toggle_recurring'])) {
  if (!expense_verify_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $msg = 'Your session expired. Please try again.';
  }
}

if ($msg == '' && isset($_POST['save_recurring'])) {
  $recurringId = isset($_POST['recurring_id']) ? (int)$_POST['recurring_id'] : 0;
  $form['item'] = isset($_POST['item']) ? trim($_POST['item']) : '';
  $form['cost'] = isset($_POST['cost']) ? trim($_POST['cost']) : '';
  $form['currency'] = expense_selected_currency(isset($_POST['currency']) ? $_POST['currency'] : 'USD');
  $form['categoryid'] = isset($_POST['categoryid']) ? trim($_POST['categoryid']) : '';
  $form['frequency'] = isset($_POST['frequency']) && $_POST['frequency'] === 'weekly' ? 'weekly' : 'monthly';
  $form['startdate'] = isset($_POST['startdate']) ? trim($_POST['startdate']) : date('Y-m-d');
  $form['notes'] = isset($_POST['notes']) ? trim($_POST['notes']) : '';
  $categoryId = (int)$form['categoryid'];

  if ($form['item'] == '' || $form['cost'] == '' || !is_numeric($form['cost']) || (float)$form['cost'] <= 0) {
    $msg = 'Please fill in a valid item and amount.';
  } elseif ($categoryId <= 0) {
    $msg = 'Please choose a category.';
  } elseif ($form['startdate'] == '' || !strtotime($form['startdate'])) {
    $msg = 'Please choose a valid start date.';
  } else {
    if ($recurringId > 0) {
      $stmt = expense_prepare_and_execute(
        $con,
        "UPDATE tblrecurring
         SET ExpenseItem=?, ExpenseCost=?, Currency=?, CategoryId=?, Notes=?, Frequency=?, StartDate=?, NextRunDate=?
         WHERE ID=? AND UserId=?",
        'sdsissssii',
        array($form['item'], (float)$form['cost'], $form['currency'], $categoryId, $form['notes'], $form['frequency'], $form['startdate'], $form['startdate'], $recurringId, $userid)
      );
      $success = $stmt ? 'Recurring expense updated.' : '';
      if (!$stmt) {
        $msg = 'Unable to update recurring expense.';
      } else {
        expense_close_statement($stmt);
      }
    } else {
      $stmt = expense_prepare_and_execute(
        $con,
        "INSERT INTO tblrecurring (UserId, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, Frequency, StartDate, NextRunDate, IsActive)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
        'isdsissss',
        array($userid, $form['item'], (float)$form['cost'], $form['currency'], $categoryId, $form['notes'], $form['frequency'], $form['startdate'], $form['startdate'])
      );
      $success = $stmt ? 'Recurring expense created.' : '';
      if (!$stmt) {
        $msg = 'Unable to create recurring expense.';
      } else {
        expense_close_statement($stmt);
      }
    }
    if ($success !== '') {
      $editRecurring = null;
      $form = array('item' => '', 'cost' => '', 'currency' => 'USD', 'categoryid' => '', 'frequency' => 'monthly', 'startdate' => date('Y-m-d'), 'notes' => '');
    }
  }
}

if ($msg == '' && isset($_POST['delete_recurring'])) {
  $recurringId = (int)$_POST['recurring_id'];
  $stmt = expense_prepare_and_execute($con, "DELETE FROM tblrecurring WHERE ID=? AND UserId=?", 'ii', array($recurringId, $userid));
  if ($stmt) {
    expense_close_statement($stmt);
    $success = 'Recurring expense deleted.';
  } else {
    $msg = 'Unable to delete recurring expense.';
  }
}

if ($msg == '' && isset($_POST['toggle_recurring'])) {
  $recurringId = (int)$_POST['recurring_id'];
  $isActive = (int)$_POST['is_active'] === 1 ? 0 : 1;
  $stmt = expense_prepare_and_execute($con, "UPDATE tblrecurring SET IsActive=? WHERE ID=? AND UserId=?", 'iii', array($isActive, $recurringId, $userid));
  if ($stmt) {
    expense_close_statement($stmt);
    $success = $isActive ? 'Recurring expense activated.' : 'Recurring expense paused.';
  } else {
    $msg = 'Unable to update recurring expense.';
  }
}

$recurringRows = expense_fetch_all_assoc(
  expense_prepare_and_execute(
    $con,
    "SELECT r.*, c.CategoryName
     FROM tblrecurring r
     LEFT JOIN tblcategories c ON c.ID=r.CategoryId AND c.UserId=r.UserId
     WHERE r.UserId=?
     ORDER BY r.IsActive DESC, r.NextRunDate ASC, r.ID DESC",
    'i',
    array($userid)
  )
);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || Recurring Expenses</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <style>
    .recurring-shell { padding-top: 24px; padding-bottom: 30px; background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%); min-height: 100vh; }
    .recurring-card { background: #fff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); padding: 24px; margin-bottom: 22px; }
    .table-clean > thead > tr > th, .table-clean > tbody > tr > td { padding: 14px 12px; border-top: 1px solid #e2e8f0; vertical-align: middle; }
    .table-clean > thead > tr > th { border-top: 0; color: #64748b; text-transform: uppercase; letter-spacing: .08em; font-size: 12px; }
    .status-pill { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .status-pill.active { background: #dcfce7; color: #166534; }
    .status-pill.paused { background: #e2e8f0; color: #334155; }
    .inline-form { display: inline; }
  </style>
</head>
<body>
  <?php include_once('templates/header.php'); ?>
  <?php include_once('templates/sidebar.php'); ?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main recurring-shell">
    <div class="row">
      <div class="col-md-5">
        <div class="recurring-card">
          <h3 class="metric-label"><?php echo $editRecurring ? 'Edit recurring expense' : 'Add recurring expense'; ?></h3>
          <?php if ($msg !== '') { ?><div class="alert alert-danger"><?php echo expense_h($msg); ?></div><?php } ?>
          <?php if ($success !== '') { ?><div class="alert alert-success"><?php echo expense_h($success); ?></div><?php } ?>
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
            <?php if ($editRecurring) { ?><input type="hidden" name="recurring_id" value="<?php echo (int)$editRecurring['ID']; ?>"><?php } ?>
            <div class="form-group">
              <label for="item">Item</label>
              <select class="form-control" id="item" name="item" required>
                <option value="">Select item</option>
                <?php foreach ($items as $itemRow) { ?>
                <option value="<?php echo expense_h($itemRow['ItemName']); ?>" <?php if ($form['item'] === $itemRow['ItemName']) { echo 'selected'; } ?>><?php echo expense_h($itemRow['ItemName']); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="cost">Amount</label>
                  <input class="form-control" type="number" min="0.01" step="0.01" id="cost" name="cost" required value="<?php echo expense_h($form['cost']); ?>">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="currency">Currency</label>
                  <select class="form-control" id="currency" name="currency">
                    <?php foreach ($currencyOptions as $currency) { ?>
                    <option value="<?php echo expense_h($currency); ?>" <?php if ($form['currency'] === $currency) { echo 'selected'; } ?>><?php echo expense_h($currency); ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="categoryid">Category</label>
              <select class="form-control" id="categoryid" name="categoryid" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $category) { ?>
                <option value="<?php echo (int)$category['ID']; ?>" <?php if ((string)$form['categoryid'] === (string)$category['ID']) { echo 'selected'; } ?>><?php echo expense_h($category['CategoryName']); ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="frequency">Frequency</label>
                  <select class="form-control" id="frequency" name="frequency">
                    <option value="monthly" <?php if ($form['frequency'] === 'monthly') { echo 'selected'; } ?>>Monthly</option>
                    <option value="weekly" <?php if ($form['frequency'] === 'weekly') { echo 'selected'; } ?>>Weekly</option>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label for="startdate">Start Date</label>
                  <input class="form-control" type="date" id="startdate" name="startdate" required value="<?php echo expense_h($form['startdate']); ?>">
                </div>
              </div>
            </div>
            <div class="form-group">
              <label for="notes">Notes</label>
              <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo expense_h($form['notes']); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary" name="save_recurring"><?php echo $editRecurring ? 'Update Recurring' : 'Add Recurring'; ?></button>
            <?php if ($editRecurring) { ?><a href="manage-recurring.php" class="btn btn-default">Cancel</a><?php } ?>
          </form>
        </div>
      </div>
      <div class="col-md-7">
        <div class="recurring-card">
          <h3 class="metric-label">Recurring schedule</h3>
          <div class="table-responsive">
            <table class="table table-clean">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Details</th>
                  <th>Next Run</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($recurringRows) > 0) { foreach ($recurringRows as $row) { ?>
                <tr>
                  <td><?php echo expense_h($row['ExpenseItem']); ?></td>
                  <td><?php echo expense_h(expense_money($row['ExpenseCost'], $row['Currency'])); ?><br><?php echo expense_h($row['CategoryName']); ?><?php if ($row['Notes'] !== '') { ?><br><?php echo expense_h($row['Notes']); ?><?php } ?></td>
                  <td><?php echo expense_h(date('F j, Y', strtotime($row['NextRunDate']))); ?><br><small><?php echo expense_h(ucfirst($row['Frequency'])); ?></small></td>
                  <td><span class="status-pill <?php echo $row['IsActive'] ? 'active' : 'paused'; ?>"><?php echo $row['IsActive'] ? 'Active' : 'Paused'; ?></span></td>
                  <td>
                    <a class="btn btn-xs btn-info" href="manage-recurring.php?editid=<?php echo (int)$row['ID']; ?>"><em class="fa fa-pencil"></em> Edit</a>
                    <form method="post" action="" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
                      <input type="hidden" name="recurring_id" value="<?php echo (int)$row['ID']; ?>">
                      <input type="hidden" name="is_active" value="<?php echo (int)$row['IsActive']; ?>">
                      <button type="submit" class="btn btn-xs btn-default" name="toggle_recurring"><?php echo $row['IsActive'] ? 'Pause' : 'Activate'; ?></button>
                    </form>
                    <form method="post" action="" class="inline-form" onsubmit="return confirm('Delete this recurring expense?');">
                      <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
                      <input type="hidden" name="recurring_id" value="<?php echo (int)$row['ID']; ?>">
                      <button type="submit" class="btn btn-xs btn-danger" name="delete_recurring"><em class="fa fa-trash"></em> Delete</button>
                    </form>
                  </td>
                </tr>
                <?php } } else { ?>
                <tr><td colspan="5" align="center">No recurring expenses yet.</td></tr>
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
</body>
</html>
