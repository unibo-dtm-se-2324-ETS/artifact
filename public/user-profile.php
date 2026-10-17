<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/expense-helpers.php');
include(__DIR__ . '/../src/account-service.php');

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
$csrfToken = expense_csrf_token();
$categories = expense_get_categories($con, $userid);

$profile = profile_get($con, $userid);
$form = profile_form_defaults($profile);

if (isset($_POST['submit'])) {
  $form = profile_form_from_input($_POST);

  if (!expense_verify_csrf(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
    $msg = 'Your session expired. Please try again.';
  } else {
    $msg = profile_update($con, $userid, $form);
    if ($msg === '') {
      $success = 'Profile and defaults updated successfully.';
      $profile = profile_get($con, $userid);
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || User Profile</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
  <style>
    .profile-shell { padding-top: 24px; padding-bottom: 30px; background: linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%); min-height: 100vh; }
    .profile-card { background: #fff; border: 1px solid #dbe4ee; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06); padding: 24px; }
    .page-title { margin: 0 0 6px; font-size: 28px; font-weight: 700; color: #0f172a; }
    .page-copy { margin: 0 0 22px; color: #64748b; }
  </style>
</head>
<body>
  <?php include_once(__DIR__ . '/../templates/header.php'); ?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php'); ?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main profile-shell">
    <div class="row">
      <div class="col-lg-12">
        <div class="profile-card">
          <ol class="breadcrumb">
            <li><a href="dashboard.php"><em class="fa fa-home"></em></a></li>
            <li class="active">Profile</li>
          </ol>

          <h1 class="page-title">Profile</h1>
          <p class="page-copy">Update your personal details and choose default expense settings.</p>

          <?php if ($msg != '') { ?><div class="alert alert-danger"><?php echo expense_h($msg); ?></div><?php } ?>
          <?php if ($success != '') { ?><div class="alert alert-success"><?php echo expense_h($success); ?></div><?php } ?>

          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo expense_h($csrfToken); ?>">
            <div class="form-group">
              <label for="fullname">Full Name</label>
              <input class="form-control" type="text" id="fullname" name="fullname" required value="<?php echo expense_h($form['fullname']); ?>">
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" class="form-control" id="email" value="<?php echo $profile ? expense_h($profile['Email']) : ''; ?>" readonly>
            </div>
            <div class="form-group">
              <label for="contactnumber">Mobile Number</label>
              <input class="form-control" type="text" id="contactnumber" name="contactnumber" value="<?php echo expense_h($form['contactnumber']); ?>" maxlength="20">
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="defaultcurrency">Default Currency</label>
                  <select class="form-control" id="defaultcurrency" name="defaultcurrency">
                    <?php foreach ($currencyOptions as $currency) { ?>
                    <option value="<?php echo expense_h($currency); ?>" <?php if ($form['defaultcurrency'] === $currency) { echo 'selected'; } ?>><?php echo expense_h($currency); ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label for="defaultcategoryid">Default Category</label>
                  <select class="form-control" id="defaultcategoryid" name="defaultcategoryid">
                    <option value="0">Choose later</option>
                    <?php foreach ($categories as $category) { ?>
                    <option value="<?php echo (int)$category['ID']; ?>" <?php if ((string)$form['defaultcategoryid'] === (string)$category['ID']) { echo 'selected'; } ?>><?php echo expense_h($category['CategoryName']); ?></option>
                    <?php } ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label>Registration Date</label>
              <input class="form-control" type="text" value="<?php echo $profile ? expense_h($profile['RegDate']) : ''; ?>" readonly>
            </div>
            <div class="form-group">
              <button type="submit" class="btn btn-primary" name="submit">Update Profile</button>
            </div>
          </form>
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
