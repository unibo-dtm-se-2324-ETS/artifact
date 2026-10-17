<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../config/database.php');
include(__DIR__ . '/../src/expense-helpers.php');
include(__DIR__ . '/../src/item-service.php');
if (strlen($_SESSION['detsuid'] == 0)) {
  header('location:logout.php');
} else {
  $userid = (int)$_SESSION['detsuid'];
  $msg = "";
  $editItem = null;

  expense_ensure_schema($con);

  if (isset($_GET['editid'])) {
    $editItem = item_find($con, $userid, (int)$_GET['editid']);
    if (!$editItem) {
      $msg = "Item not found.";
    }
  }

  if (isset($_POST['submit'])) {
    $result = item_add($con, $userid, isset($_POST['itemname']) ? $_POST['itemname'] : '');
    if ($result['ok']) {
      echo "<script>alert('Item added successfully');</script>";
      echo "<script>window.location.href='add-item.php'</script>";
    } else {
      $msg = $result['message'];
    }
  }

  if (isset($_POST['update'])) {
    $itemid = isset($_POST['itemid']) ? (int)$_POST['itemid'] : 0;
    $result = item_rename($con, $userid, $itemid, isset($_POST['itemname']) ? $_POST['itemname'] : '');
    if ($result['ok']) {
      echo "<script>alert('Item updated successfully');</script>";
      echo "<script>window.location.href='add-item.php'</script>";
    } else {
      $msg = $result['message'];
      $editItem = item_find($con, $userid, $itemid);
    }
  }

  if (isset($_GET['delid'])) {
    if (item_delete($con, $userid, (int)$_GET['delid'])) {
      echo "<script>alert('Item deleted successfully');</script>";
      echo "<script>window.location.href='add-item.php'</script>";
    } else {
      $msg = "Unable to delete item.";
    }
  }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daily Expense Tracker || Add Items</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/font-awesome.min.css" rel="stylesheet">
  <link href="assets/css/datepicker3.css" rel="stylesheet">
  <link href="assets/css/styles.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
</head>
<body>
  <?php include_once(__DIR__ . '/../templates/header.php'); ?>
  <?php include_once(__DIR__ . '/../templates/sidebar.php'); ?>

  <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
      <ol class="breadcrumb">
        <li><a href="#"><em class="fa fa-home"></em></a></li>
        <li class="active">Items</li>
      </ol>
    </div>

    <div class="row">
      <div class="col-lg-12">
        <div class="panel panel-default">
          <div class="panel-heading"><?php echo $editItem ? 'Edit Item' : 'Add Items'; ?></div>
          <div class="panel-body">
            <p style="font-size:16px; color:red" align="center"><?php if ($msg) { echo $msg; } ?></p>

            <div class="col-md-6">
              <form role="form" method="post" action="">
                <?php if ($editItem) { ?>
                <input type="hidden" name="itemid" value="<?php echo $editItem['ID']; ?>">
                <?php } ?>
                <div class="form-group">
                  <label>Item Name</label>
                  <input class="form-control" type="text" name="itemname" required="true" maxlength="150" placeholder="e.g. Groceries" value="<?php echo $editItem ? htmlentities($editItem['ItemName']) : ''; ?>">
                </div>
                <div class="form-group has-success">
                  <?php if ($editItem) { ?>
                  <button type="submit" class="btn btn-primary" name="update">Update Item</button>
                  <a href="add-item.php" class="btn btn-default">Cancel</a>
                  <?php } else { ?>
                  <button type="submit" class="btn btn-primary" name="submit">Add Item</button>
                  <?php } ?>
                </div>
              </form>
            </div>

            <div class="col-md-12">
              <hr />
              <div class="table-responsive">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>S.NO</th>
                      <th>Item Name</th>
                      <th>Created On</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $cnt = 1;
                    foreach (item_list($con, $userid) as $row) {
                    ?>
                    <tr>
                      <td><?php echo $cnt; ?></td>
                      <td><?php echo expense_h($row['ItemName']); ?></td>
                      <td><?php echo $row['CreatedAt']; ?></td>
                      <td>
                        <a class="btn btn-xs btn-info" href="add-item.php?editid=<?php echo $row['ID']; ?>">
                          <em class="fa fa-pencil"></em> Edit
                        </a>
                        <a class="btn btn-xs btn-danger" href="add-item.php?delid=<?php echo $row['ID']; ?>" onclick="return confirm('Delete this item?')">
                          <em class="fa fa-trash"></em> Delete
                        </a>
                      </td>
                    </tr>
                    <?php
                    $cnt = $cnt + 1;
                    }
                    if ($cnt == 1) {
                    ?>
                    <tr>
                      <td colspan="4" align="center">No items found.</td>
                    </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </div>
      </div>
      <?php include_once(__DIR__ . '/../templates/footer.php'); ?>
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
