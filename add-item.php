<?php
session_start();
error_reporting(0);
include('config/database.php');
if (strlen($_SESSION['detsuid'] == 0)) {
  header('location:logout.php');
} else {
  $userid = $_SESSION['detsuid'];
  $msg = "";
  $editItem = null;

  mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblitems (
    ID int(11) NOT NULL AUTO_INCREMENT,
    UserId int(11) NOT NULL,
    ItemName varchar(150) NOT NULL,
    CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    KEY idx_userid (UserId)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  if (isset($_GET['editid'])) {
    $editid = intval($_GET['editid']);
    $editQuery = mysqli_query($con, "SELECT ID, ItemName FROM tblitems WHERE ID='$editid' AND UserId='$userid'");
    $editItem = mysqli_fetch_array($editQuery);
    if (!$editItem) {
      $msg = "Item not found.";
    }
  }

  if (isset($_POST['submit'])) {
    $itemname = trim($_POST['itemname']);
    if ($itemname == "") {
      $msg = "Item name is required.";
    } else {
      $itemname = mysqli_real_escape_string($con, $itemname);
      $check = mysqli_query($con, "SELECT ID FROM tblitems WHERE UserId='$userid' AND ItemName='$itemname'");
      if (mysqli_num_rows($check) > 0) {
        $msg = "This item already exists.";
      } else {
        $query = mysqli_query($con, "INSERT INTO tblitems(UserId, ItemName) VALUES('$userid', '$itemname')");
        if ($query) {
          echo "<script>alert('Item added successfully');</script>";
          echo "<script>window.location.href='add-item.php'</script>";
        } else {
          $msg = "Something went wrong. Please try again.";
        }
      }
    }
  }

  if (isset($_POST['update'])) {
    $itemid = intval($_POST['itemid']);
    $itemname = trim($_POST['itemname']);

    if ($itemname == "") {
      $msg = "Item name is required.";
    } else {
      $itemname = mysqli_real_escape_string($con, $itemname);
      $check = mysqli_query($con, "SELECT ID FROM tblitems WHERE UserId='$userid' AND ItemName='$itemname' AND ID!='$itemid'");
      if (mysqli_num_rows($check) > 0) {
        $msg = "This item already exists.";
      } else {
        $query = mysqli_query($con, "UPDATE tblitems SET ItemName='$itemname' WHERE ID='$itemid' AND UserId='$userid'");
        if ($query) {
          echo "<script>alert('Item updated successfully');</script>";
          echo "<script>window.location.href='add-item.php'</script>";
        } else {
          $msg = "Something went wrong. Please try again.";
        }
      }
    }

    if ($msg != "") {
      $editQuery = mysqli_query($con, "SELECT ID, ItemName FROM tblitems WHERE ID='$itemid' AND UserId='$userid'");
      $editItem = mysqli_fetch_array($editQuery);
    }
  }

  if (isset($_GET['delid'])) {
    $delid = intval($_GET['delid']);
    $deleteQuery = mysqli_query($con, "DELETE FROM tblitems WHERE ID='$delid' AND UserId='$userid'");
    if ($deleteQuery) {
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
  <?php include_once('templates/header.php'); ?>
  <?php include_once('templates/sidebar.php'); ?>

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
                    $ret = mysqli_query($con, "SELECT * FROM tblitems WHERE UserId='$userid' ORDER BY ItemName ASC");
                    $cnt = 1;
                    while ($row = mysqli_fetch_array($ret)) {
                    ?>
                    <tr>
                      <td><?php echo $cnt; ?></td>
                      <td><?php echo $row['ItemName']; ?></td>
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
      <?php include_once('templates/footer.php'); ?>
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
