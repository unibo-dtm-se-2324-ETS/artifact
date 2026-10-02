<?php
// Database connection settings. The defaults match a local XAMPP installation;
// set DB_HOST, DB_USER, DB_PASS and DB_NAME to override them (used by CI).
$con=mysqli_connect(
  getenv('DB_HOST') ?: 'localhost',
  getenv('DB_USER') ?: 'root',
  getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
  getenv('DB_NAME') ?: 'detsdb'
);
if(mysqli_connect_errno()){
echo "Connection Fail".mysqli_connect_error();
}

  ?>
