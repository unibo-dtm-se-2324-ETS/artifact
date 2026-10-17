<?php
// Business logic for expenses: validation, filtering, CSV rows and the
// create, read, update, delete and search operations. The pages call these
// functions so that the rules live in one place and can be tested.
// Requires src/expense-helpers.php to be loaded first.

if (!function_exists('expense_validate_form')) {
  // Returns the first validation message for an expense form, or '' when it is valid.
  function expense_validate_form($form, $categoryId) {
    if (!isset($form['dateexpense']) || $form['dateexpense'] == '' || !strtotime($form['dateexpense'])) {
      return 'Please choose a valid expense date.';
    }

    if (!isset($form['item']) || $form['item'] == '') {
      return 'Please select an item.';
    }

    if (!isset($form['costitem']) || $form['costitem'] == '' || !is_numeric($form['costitem']) || (float)$form['costitem'] <= 0) {
      return 'Please enter a valid cost greater than zero.';
    }

    if ((int)$categoryId <= 0) {
      return 'Please choose a category.';
    }

    return '';
  }
}

if (!function_exists('expense_find')) {
  // Returns one expense of the user, or null when it does not exist or belongs to someone else.
  function expense_find($con, $userid, $id) {
    return expense_fetch_one_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT ID, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, ReceiptPath FROM tblexpense WHERE ID=? AND UserId=? LIMIT 1",
        'ii',
        array((int)$id, (int)$userid)
      )
    );
  }
}

if (!function_exists('expense_create')) {
  function expense_create($con, $userid, $form, $categoryId, $receiptPath) {
    $stmt = expense_prepare_and_execute(
      $con,
      "INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, ReceiptPath) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
      'issdsiss',
      array((int)$userid, $form['dateexpense'], $form['item'], (float)$form['costitem'], $form['currency'], (int)$categoryId, $form['notes'], $receiptPath)
    );

    if (!$stmt) {
      return false;
    }

    expense_close_statement($stmt);
    return true;
  }
}

if (!function_exists('expense_update')) {
  // Updates an expense of the user. Another user's expense is never changed.
  function expense_update($con, $userid, $id, $form, $categoryId, $receiptPath) {
    $stmt = expense_prepare_and_execute(
      $con,
      "UPDATE tblexpense SET ExpenseDate=?, ExpenseItem=?, ExpenseCost=?, Currency=?, CategoryId=?, Notes=?, ReceiptPath=? WHERE ID=? AND UserId=?",
      'ssdsissii',
      array($form['dateexpense'], $form['item'], (float)$form['costitem'], $form['currency'], (int)$categoryId, $form['notes'], $receiptPath, (int)$id, (int)$userid)
    );

    if (!$stmt) {
      return false;
    }

    expense_close_statement($stmt);
    return true;
  }
}

if (!function_exists('expense_delete')) {
  // Deletes an expense of the user together with its receipt file.
  // Returns false when nothing was deleted, for example for an unknown id or another user's expense.
  function expense_delete($con, $userid, $id) {
    $expense = expense_find($con, $userid, $id);
    if (!$expense) {
      return false;
    }

    $stmt = expense_prepare_and_execute($con, "DELETE FROM tblexpense WHERE ID=? AND UserId=?", 'ii', array((int)$id, (int)$userid));
    if (!$stmt) {
      return false;
    }

    $deleted = mysqli_stmt_affected_rows($stmt) > 0;
    expense_close_statement($stmt);

    if ($deleted && !empty($expense['ReceiptPath'])) {
      expense_delete_receipt_file($expense['ReceiptPath']);
    }

    return $deleted;
  }
}

if (!function_exists('expense_normalize_filters')) {
  // Cleans the search filters taken from a request (usually $_GET).
  function expense_normalize_filters($input) {
    $filters = array(
      'q' => isset($input['q']) ? trim($input['q']) : '',
      'currency' => isset($input['currency']) && $input['currency'] !== '' ? expense_selected_currency($input['currency']) : '',
      'categoryid' => isset($input['categoryid']) ? (int)$input['categoryid'] : 0,
      'window' => isset($input['window']) ? trim($input['window']) : '',
      'fromdate' => isset($input['fromdate']) ? trim($input['fromdate']) : '',
      'todate' => isset($input['todate']) ? trim($input['todate']) : '',
      'minamount' => isset($input['minamount']) ? trim($input['minamount']) : '',
      'maxamount' => isset($input['maxamount']) ? trim($input['maxamount']) : ''
    );

    if (!in_array($filters['window'], array('', 'today', 'yesterday', 'last24h'), true)) {
      $filters['window'] = '';
    }

    return $filters;
  }
}

if (!function_exists('expense_filter_query')) {
  // Builds the SQL, the parameter types and the parameters for a filtered expense list.
  function expense_filter_query($userid, $filters) {
    $sql = "SELECT e.ID, e.ExpenseItem, e.ExpenseCost, e.ExpenseDate, e.Currency, e.CategoryId, e.Notes, e.ReceiptPath, e.CreatedAt, c.CategoryName
            FROM tblexpense e
            LEFT JOIN tblcategories c ON c.ID=e.CategoryId AND c.UserId=e.UserId
            WHERE e.UserId=?";
    $types = 'i';
    $params = array((int)$userid);

    if ($filters['q'] !== '') {
      $sql .= " AND (e.ExpenseItem LIKE ? OR e.Notes LIKE ?)";
      $types .= 'ss';
      $params[] = '%' . $filters['q'] . '%';
      $params[] = '%' . $filters['q'] . '%';
    }
    if ($filters['currency'] !== '') {
      $sql .= " AND e.Currency=?";
      $types .= 's';
      $params[] = $filters['currency'];
    }
    if ($filters['categoryid'] > 0) {
      $sql .= " AND e.CategoryId=?";
      $types .= 'i';
      $params[] = $filters['categoryid'];
    }
    if ($filters['window'] === 'today') {
      $sql .= " AND e.ExpenseDate=CURDATE()";
    } elseif ($filters['window'] === 'yesterday') {
      $sql .= " AND e.ExpenseDate=DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($filters['window'] === 'last24h') {
      $sql .= " AND e.CreatedAt BETWEEN DATE_SUB(NOW(), INTERVAL 24 HOUR) AND NOW()";
    }
    if ($filters['fromdate'] !== '' && strtotime($filters['fromdate'])) {
      $sql .= " AND e.ExpenseDate>=?";
      $types .= 's';
      $params[] = $filters['fromdate'];
    }
    if ($filters['todate'] !== '' && strtotime($filters['todate'])) {
      $sql .= " AND e.ExpenseDate<=?";
      $types .= 's';
      $params[] = $filters['todate'];
    }
    if ($filters['minamount'] !== '' && is_numeric($filters['minamount'])) {
      $sql .= " AND e.ExpenseCost>=?";
      $types .= 'd';
      $params[] = (float)$filters['minamount'];
    }
    if ($filters['maxamount'] !== '' && is_numeric($filters['maxamount'])) {
      $sql .= " AND e.ExpenseCost<=?";
      $types .= 'd';
      $params[] = (float)$filters['maxamount'];
    }

    $sql .= " ORDER BY e.CreatedAt DESC, e.ID DESC";

    return array('sql' => $sql, 'types' => $types, 'params' => $params);
  }
}

if (!function_exists('expense_search')) {
  // Returns the user's expenses that match the filters, newest first.
  function expense_search($con, $userid, $filters) {
    $query = expense_filter_query($userid, $filters);

    return expense_fetch_all_assoc(expense_prepare_and_execute($con, $query['sql'], $query['types'], $query['params']));
  }
}

if (!function_exists('expense_csv_lines')) {
  // Returns the rows of the CSV export: a header line followed by one line per expense.
  function expense_csv_lines($rows) {
    $lines = array(array('Date', 'Recorded At', 'Item', 'Category', 'Amount', 'Currency', 'Notes', 'Receipt'));

    foreach ($rows as $row) {
      $lines[] = array(
        $row['ExpenseDate'],
        $row['CreatedAt'],
        $row['ExpenseItem'],
        $row['CategoryName'] ? $row['CategoryName'] : 'Uncategorized',
        number_format((float)$row['ExpenseCost'], 2, '.', ''),
        $row['Currency'],
        preg_replace('/\s+/', ' ', trim((string)$row['Notes'])),
        $row['ReceiptPath']
      );
    }

    return $lines;
  }
}
