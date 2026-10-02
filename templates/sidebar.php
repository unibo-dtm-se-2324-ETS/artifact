<?php
session_start();
error_reporting(0);
include('config/database.php');
?>
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$expensePages = array('add-expense.php', 'manage-expense.php', 'add-item.php', 'edit-expense.php', 'manage-categories.php', 'manage-recurring.php');
$reportPages = array(
    'expense-datewise-reports.php',
    'expense-datewise-reports-detailed.php',
    'expense-monthwise-reports.php',
    'expense-monthwise-reports-detailed.php',
    'expense-yearwise-reports.php',
    'expense-yearwise-reports-detailed.php',
    'expense-reports.php',
    'expense-reports-detailed.php'
);
?>


<div id="sidebar-collapse" class="col-sm-3 col-lg-2 sidebar">
        <div class="profile-sidebar">
            <div class="profile-userpic">
                <img src="assets/images/users/1.jpg" class="img-responsive" alt="User">
            </div>
            <div class="profile-usertitle">
                <?php
$uid=$_SESSION['detsuid'];
$ret=mysqli_query($con,"select FullName from tbluser where ID='$uid'");
$row=mysqli_fetch_array($ret);
$name=$row['FullName'];

?>
                <div class="profile-usertitle-name"><?php echo $name; ?></div>
                <div class="profile-usertitle-status"><span class="indicator label-success"></span>Online</div>
            </div>
            <div class="clear"></div>
        </div>
        <div class="divider"></div>
        
        <ul class="nav menu">
            <li class="<?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>"><a href="dashboard.php"><em class="fa fa-dashboard">&nbsp;</em> Dashboard</a></li>
            
            
           
            <li class="parent <?php echo in_array($currentPage, $expensePages) ? 'active' : ''; ?>"><a data-toggle="collapse" href="#sub-item-1">
                <em class="fa fa-navicon">&nbsp;</em>Expenses <span data-toggle="collapse" href="#sub-item-1" class="icon pull-right"><em class="fa fa-plus"></em></span>
                </a>
                <ul class="children collapse <?php echo in_array($currentPage, $expensePages) ? 'in' : ''; ?>" id="sub-item-1">
                    <li><a class="<?php echo ($currentPage == 'add-expense.php') ? 'active' : ''; ?>" href="add-expense.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Add Expenses
                    </a></li>
                    <li><a class="<?php echo ($currentPage == 'manage-expense.php') ? 'active' : ''; ?>" href="manage-expense.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Manage Expenses
                    </a></li>
                    <li><a class="<?php echo ($currentPage == 'add-item.php') ? 'active' : ''; ?>" href="add-item.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Add Items
                    </a></li>
                    <li><a class="<?php echo ($currentPage == 'manage-categories.php') ? 'active' : ''; ?>" href="manage-categories.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Categories & Budgets
                    </a></li>
                    <li><a class="<?php echo ($currentPage == 'manage-recurring.php') ? 'active' : ''; ?>" href="manage-recurring.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Recurring Expenses
                    </a></li>
                    
                </ul>

            </li>
           
  <li class="parent <?php echo in_array($currentPage, $reportPages) ? 'active' : ''; ?>"><a data-toggle="collapse" href="#sub-item-2">
                <em class="fa fa-navicon">&nbsp;</em>Expense Report <span data-toggle="collapse" href="#sub-item-2" class="icon pull-right"><em class="fa fa-plus"></em></span>
                </a>
                <ul class="children collapse <?php echo in_array($currentPage, $reportPages) ? 'in' : ''; ?>" id="sub-item-2">
                    <li><a class="<?php echo ($currentPage == 'expense-datewise-reports.php' || $currentPage == 'expense-datewise-reports-detailed.php') ? 'active' : ''; ?>" href="expense-datewise-reports.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Daywise Expenses
                    </a></li>
                    <li><a class="<?php echo ($currentPage == 'expense-monthwise-reports.php' || $currentPage == 'expense-monthwise-reports-detailed.php') ? 'active' : ''; ?>" href="expense-monthwise-reports.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Monthwise Expenses
                    </a></li>
                    <li><a class="<?php echo ($currentPage == 'expense-yearwise-reports.php' || $currentPage == 'expense-yearwise-reports-detailed.php') ? 'active' : ''; ?>" href="expense-yearwise-reports.php">
                        <span class="fa fa-arrow-right">&nbsp;</span> Yearwise Expenses
                    </a></li>
                    
                </ul>
            </li>




            
            <li class="<?php echo ($currentPage == 'user-profile.php') ? 'active' : ''; ?>"><a href="user-profile.php"><em class="fa fa-user">&nbsp;</em> Profile</a></li>
             <li class="<?php echo ($currentPage == 'change-password.php') ? 'active' : ''; ?>"><a href="change-password.php"><em class="fa fa-clone">&nbsp;</em> Change Password</a></li>
<li><a href="logout.php"><em class="fa fa-power-off">&nbsp;</em> Logout</a></li>

        </ul>
    </div>
