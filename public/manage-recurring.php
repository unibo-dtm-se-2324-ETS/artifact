<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/expense-helpers.php');
include(__DIR__ . '/../src/item-service.php');
include(__DIR__ . '/../src/recurring-service.php');

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
$items = item_list($con, $userid);

$editRecurring = null;
if (isset($_GET['editid'])) {
  $editRecurring = recurring_find($con, $userid, (int)$_GET['editid']);
}

$form = recurring_form_defaults($editRecurring);

if (isset($_POST['save_recurring']) || isset($_POST['delete_recurring']) || isset($_POST['toggle_recurring'])) {
  if (!expense_verify_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $msg = 'Your session expired. Please try again.';
  }
}

$result = null;

if ($msg == '' && isset($_POST['save_recurring'])) {
  $form = recurring_form_from_input($_POST);
  $result = recurring_save($con, $userid, isset($_POST['recurring_id']) ? (int)$_POST['recurring_id'] : 0, $form);
  if ($result['ok']) {
    $editRecurring = null;
    $form = recurring_form_defaults(null);
  }
}

if ($msg == '' && isset($_POST['delete_recurring'])) {
  $result = recurring_delete($con, $userid, (int)$_POST['recurring_id']);
}

if ($msg == '' && isset($_POST['toggle_recurring'])) {
  $result = recurring_toggle($con, $userid, (int)$_POST['recurring_id'], (int)$_POST['is_active']);
}

if ($result !== null) {
  if ($result['ok']) {
    $success = $result['message'];
  } else {
    $msg = $result['message'];
  }
}

$recurringRows = recurring_list($con, $userid);
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
  <?php include_once(__DIR__ . '/../templates/header.php'); ?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php'); ?>

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
    <?php include_once(__DIR__ . '/../templates/footer.php'); ?>
  </div>

  <script src="assets/js/jquery-1.11.1.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>
