<?php
if (!function_exists('expense_currency_options')) {
  function expense_currency_options() {
    return array('USD', 'EUR', 'IQD', 'GBP', 'AED', 'SAR');
  }
}

if (!function_exists('expense_currency_symbol')) {
  function expense_currency_symbol($currency) {
    $symbols = array(
      'USD' => '$',
      'EUR' => '€',
      'IQD' => 'IQD',
      'GBP' => '£',
      'AED' => 'AED',
      'SAR' => 'SAR'
    );

    return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
  }
}

if (!function_exists('expense_money')) {
  function expense_money($amount, $currency) {
    return number_format((float)$amount, 2) . ' ' . expense_currency_symbol($currency);
  }
}

if (!function_exists('expense_h')) {
  function expense_h($value) {
    return htmlentities((string)$value, ENT_QUOTES, 'UTF-8');
  }
}

if (!function_exists('expense_month_key')) {
  function expense_month_key($value = null) {
    $timestamp = $value ? strtotime($value) : time();

    return date('Y-m', $timestamp);
  }
}

if (!function_exists('expense_selected_currency')) {
  function expense_selected_currency($value, $default = 'USD') {
    $currency = strtoupper(trim((string)$value));
    $options = expense_currency_options();

    if (!in_array($currency, $options)) {
      $currency = $default;
    }

    return $currency;
  }
}

if (!function_exists('expense_csrf_token')) {
  function expense_csrf_token() {
    if (empty($_SESSION['expense_csrf_token'])) {
      $_SESSION['expense_csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['expense_csrf_token'];
  }
}

if (!function_exists('expense_verify_csrf')) {
  function expense_verify_csrf($token) {
    return isset($_SESSION['expense_csrf_token']) && is_string($token) && hash_equals($_SESSION['expense_csrf_token'], $token);
  }
}

if (!function_exists('expense_prepare_and_execute')) {
  function expense_prepare_and_execute($con, $sql, $types = '', $params = array()) {
    $stmt = mysqli_prepare($con, $sql);

    if (!$stmt) {
      return false;
    }

    if ($types !== '' && !empty($params)) {
      $bindParams = array($stmt, $types);
      foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
      }
      call_user_func_array('mysqli_stmt_bind_param', $bindParams);
    }

    if (!mysqli_stmt_execute($stmt)) {
      mysqli_stmt_close($stmt);
      return false;
    }

    return $stmt;
  }
}

if (!function_exists('expense_fetch_all_assoc')) {
  function expense_fetch_all_assoc($stmt) {
    $rows = array();

    if (!$stmt) {
      return $rows;
    }

    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
      while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
      }
      mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);
    return $rows;
  }
}

if (!function_exists('expense_fetch_one_assoc')) {
  function expense_fetch_one_assoc($stmt) {
    $row = null;

    if (!$stmt) {
      return $row;
    }

    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
      $row = mysqli_fetch_assoc($result);
      mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);
    return $row;
  }
}

if (!function_exists('expense_close_statement')) {
  function expense_close_statement($stmt) {
    if ($stmt) {
      mysqli_stmt_close($stmt);
    }
  }
}

if (!function_exists('expense_ensure_schema')) {
  function expense_ensure_schema($con) {
    $currencyColumn = mysqli_query($con, "SHOW COLUMNS FROM tblexpense LIKE 'Currency'");
    if ($currencyColumn && mysqli_num_rows($currencyColumn) == 0) {
      mysqli_query($con, "ALTER TABLE tblexpense ADD COLUMN Currency varchar(10) NOT NULL DEFAULT 'USD' AFTER ExpenseCost");
    }

    $categoryColumn = mysqli_query($con, "SHOW COLUMNS FROM tblexpense LIKE 'CategoryId'");
    if ($categoryColumn && mysqli_num_rows($categoryColumn) == 0) {
      mysqli_query($con, "ALTER TABLE tblexpense ADD COLUMN CategoryId int(11) DEFAULT NULL AFTER Currency");
    }

    $notesColumn = mysqli_query($con, "SHOW COLUMNS FROM tblexpense LIKE 'Notes'");
    if ($notesColumn && mysqli_num_rows($notesColumn) == 0) {
      mysqli_query($con, "ALTER TABLE tblexpense ADD COLUMN Notes text DEFAULT NULL AFTER CategoryId");
    }

    $receiptColumn = mysqli_query($con, "SHOW COLUMNS FROM tblexpense LIKE 'ReceiptPath'");
    if ($receiptColumn && mysqli_num_rows($receiptColumn) == 0) {
      mysqli_query($con, "ALTER TABLE tblexpense ADD COLUMN ReceiptPath varchar(255) DEFAULT NULL AFTER Notes");
    }

    $createdAtColumn = mysqli_query($con, "SHOW COLUMNS FROM tblexpense LIKE 'CreatedAt'");
    if ($createdAtColumn && mysqli_num_rows($createdAtColumn) == 0) {
      mysqli_query($con, "ALTER TABLE tblexpense ADD COLUMN CreatedAt timestamp NULL DEFAULT NULL AFTER ReceiptPath");
      mysqli_query($con, "UPDATE tblexpense SET CreatedAt = CASE WHEN ExpenseDate IS NOT NULL THEN CONCAT(ExpenseDate, ' 00:00:00') ELSE NOW() END WHERE CreatedAt IS NULL");
      mysqli_query($con, "ALTER TABLE tblexpense MODIFY CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblitems (
      ID int(11) NOT NULL AUTO_INCREMENT,
      UserId int(11) NOT NULL,
      ItemName varchar(150) NOT NULL,
      CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (ID),
      KEY idx_userid (UserId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblcategories (
      ID int(11) NOT NULL AUTO_INCREMENT,
      UserId int(11) NOT NULL,
      CategoryName varchar(100) NOT NULL,
      CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (ID),
      KEY idx_userid (UserId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblbudgets (
      ID int(11) NOT NULL AUTO_INCREMENT,
      UserId int(11) NOT NULL,
      CategoryId int(11) NOT NULL,
      BudgetMonth char(7) NOT NULL,
      Currency varchar(10) NOT NULL DEFAULT 'USD',
      BudgetAmount decimal(12,2) NOT NULL DEFAULT 0.00,
      CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UpdatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (ID),
      KEY idx_userid_month_currency (UserId, BudgetMonth, Currency),
      KEY idx_categoryid (CategoryId)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS tblrecurring (
      ID int(11) NOT NULL AUTO_INCREMENT,
      UserId int(11) NOT NULL,
      ExpenseItem varchar(150) NOT NULL,
      ExpenseCost decimal(12,2) NOT NULL DEFAULT 0.00,
      Currency varchar(10) NOT NULL DEFAULT 'USD',
      CategoryId int(11) NOT NULL,
      Notes text DEFAULT NULL,
      Frequency varchar(20) NOT NULL DEFAULT 'monthly',
      StartDate date NOT NULL,
      NextRunDate date NOT NULL,
      LastRunDate date DEFAULT NULL,
      IsActive tinyint(1) NOT NULL DEFAULT 1,
      CreatedAt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (ID),
      KEY idx_userid_nextrun (UserId, NextRunDate)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $defaultCurrencyColumn = mysqli_query($con, "SHOW COLUMNS FROM tbluser LIKE 'DefaultCurrency'");
    if ($defaultCurrencyColumn && mysqli_num_rows($defaultCurrencyColumn) == 0) {
      mysqli_query($con, "ALTER TABLE tbluser ADD COLUMN DefaultCurrency varchar(10) NOT NULL DEFAULT 'USD' AFTER MobileNumber");
    }

    $defaultCategoryColumn = mysqli_query($con, "SHOW COLUMNS FROM tbluser LIKE 'DefaultCategoryId'");
    if ($defaultCategoryColumn && mysqli_num_rows($defaultCategoryColumn) == 0) {
      mysqli_query($con, "ALTER TABLE tbluser ADD COLUMN DefaultCategoryId int(11) DEFAULT NULL AFTER DefaultCurrency");
    }
  }
}

if (!function_exists('expense_ensure_user_categories')) {
  function expense_ensure_user_categories($con, $userid) {
    $checkStmt = expense_prepare_and_execute($con, "SELECT ID FROM tblcategories WHERE UserId=? LIMIT 1", 'i', array($userid));
    $existing = expense_fetch_one_assoc($checkStmt);

    if ($existing) {
      return;
    }

    $defaults = array('Food', 'Transport', 'Bills', 'Shopping', 'Health', 'Entertainment');
    $insertStmt = mysqli_prepare($con, "INSERT INTO tblcategories (UserId, CategoryName) VALUES (?, ?)");
    if (!$insertStmt) {
      return;
    }

    foreach ($defaults as $name) {
      mysqli_stmt_bind_param($insertStmt, 'is', $userid, $name);
      mysqli_stmt_execute($insertStmt);
    }

    mysqli_stmt_close($insertStmt);
  }
}

if (!function_exists('expense_get_categories')) {
  function expense_get_categories($con, $userid) {
    $stmt = expense_prepare_and_execute($con, "SELECT ID, CategoryName FROM tblcategories WHERE UserId=? ORDER BY CategoryName ASC", 'i', array($userid));
    return expense_fetch_all_assoc($stmt);
  }
}

if (!function_exists('expense_find_category_by_id')) {
  function expense_find_category_by_id($con, $userid, $categoryId) {
    $stmt = expense_prepare_and_execute($con, "SELECT ID, CategoryName FROM tblcategories WHERE ID=? AND UserId=? LIMIT 1", 'ii', array($categoryId, $userid));
    return expense_fetch_one_assoc($stmt);
  }
}

if (!function_exists('expense_budget_progress')) {
  function expense_budget_progress($spent, $budget) {
    if ((float)$budget <= 0) {
      return 0;
    }

    $progress = ((float)$spent / (float)$budget) * 100;

    return max(0, min(100, $progress));
  }
}

if (!function_exists('expense_get_user_settings')) {
  function expense_get_user_settings($con, $userid) {
    $defaults = array(
      'DefaultCurrency' => 'USD',
      'DefaultCategoryId' => null
    );

    $row = expense_fetch_one_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT DefaultCurrency, DefaultCategoryId FROM tbluser WHERE ID=? LIMIT 1",
        'i',
        array($userid)
      )
    );

    if (!$row) {
      return $defaults;
    }

    $defaults['DefaultCurrency'] = expense_selected_currency($row['DefaultCurrency'], 'USD');
    $defaults['DefaultCategoryId'] = $row['DefaultCategoryId'] ? (int)$row['DefaultCategoryId'] : null;

    return $defaults;
  }
}

if (!function_exists('expense_handle_receipt_upload')) {
  function expense_handle_receipt_upload($file, $userid) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
      return array('path' => '', 'error' => '');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
      return array('path' => '', 'error' => 'Receipt upload failed.');
    }

    $allowedExtensions = array('jpg', 'jpeg', 'png', 'pdf');
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions)) {
      return array('path' => '', 'error' => 'Receipt must be a JPG, PNG, or PDF file.');
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0777, true);
    }

    $filename = 'receipt_' . $userid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
      return array('path' => '', 'error' => 'Could not save the uploaded receipt.');
    }

    return array(
      'path' => 'uploads/receipts/' . $filename,
      'error' => ''
    );
  }
}

if (!function_exists('expense_delete_receipt_file')) {
  function expense_delete_receipt_file($relativePath) {
    $relativePath = trim((string)$relativePath);

    if ($relativePath === '') {
      return;
    }

    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($fullPath)) {
      @unlink($fullPath);
    }
  }
}

if (!function_exists('expense_process_recurring')) {
  function expense_process_recurring($con, $userid) {
    $today = date('Y-m-d');
    $stmt = expense_prepare_and_execute(
      $con,
      "SELECT ID, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, Frequency, NextRunDate
       FROM tblrecurring
       WHERE UserId=? AND IsActive=1 AND NextRunDate<=?
       ORDER BY NextRunDate ASC",
      'is',
      array($userid, $today)
    );
    $rows = expense_fetch_all_assoc($stmt);

    foreach ($rows as $row) {
      $nextRun = $row['NextRunDate'];
      $frequency = $row['Frequency'];

      while ($nextRun <= $today) {
        $insertStmt = expense_prepare_and_execute(
          $con,
          "INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, ReceiptPath)
           VALUES (?, ?, ?, ?, ?, ?, ?, '')",
          'issdsis',
          array(
            $userid,
            $nextRun,
            $row['ExpenseItem'],
            (float)$row['ExpenseCost'],
            $row['Currency'],
            $row['CategoryId'],
            $row['Notes']
          )
        );
        expense_close_statement($insertStmt);

        if ($frequency === 'weekly') {
          $nextRun = date('Y-m-d', strtotime($nextRun . ' +7 days'));
        } else {
          $nextRun = date('Y-m-d', strtotime($nextRun . ' +1 month'));
        }
      }

      $updateStmt = expense_prepare_and_execute(
        $con,
        "UPDATE tblrecurring SET LastRunDate=?, NextRunDate=? WHERE ID=? AND UserId=?",
        'ssii',
        array($today, $nextRun, $row['ID'], $userid)
      );
      expense_close_statement($updateStmt);
    }
  }
}
?>
