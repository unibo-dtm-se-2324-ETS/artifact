<?php
// Categories and monthly budgets.
// Requires src/expense-helpers.php to be loaded first.

if (!function_exists('category_add')) {
  // Returns array('ok' => bool, 'message' => string).
  function category_add($con, $userid, $name) {
    $name = trim((string)$name);

    if ($name == '') {
      return array('ok' => false, 'message' => 'Category name is required.');
    }

    $exists = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID FROM tblcategories WHERE UserId=? AND CategoryName=? LIMIT 1", 'is', array((int)$userid, $name))
    );
    if ($exists) {
      return array('ok' => false, 'message' => 'This category already exists.');
    }

    $stmt = expense_prepare_and_execute($con, "INSERT INTO tblcategories (UserId, CategoryName) VALUES (?, ?)", 'is', array((int)$userid, $name));
    if (!$stmt) {
      return array('ok' => false, 'message' => 'Unable to add category right now.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Category added successfully.');
  }
}

if (!function_exists('category_rename')) {
  function category_rename($con, $userid, $id, $name) {
    $name = trim((string)$name);

    if ((int)$id <= 0 || $name == '') {
      return array('ok' => false, 'message' => 'A valid category is required.');
    }

    $exists = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID FROM tblcategories WHERE UserId=? AND CategoryName=? AND ID<>? LIMIT 1", 'isi', array((int)$userid, $name, (int)$id))
    );
    if ($exists) {
      return array('ok' => false, 'message' => 'This category already exists.');
    }

    $stmt = expense_prepare_and_execute($con, "UPDATE tblcategories SET CategoryName=? WHERE ID=? AND UserId=?", 'sii', array($name, (int)$id, (int)$userid));
    if (!$stmt) {
      return array('ok' => false, 'message' => 'Unable to update this category.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Category updated successfully.');
  }
}

if (!function_exists('category_remove')) {
  // A category that is used by an expense or a budget cannot be deleted.
  function category_remove($con, $userid, $id) {
    $expenseUse = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT COUNT(*) AS total FROM tblexpense WHERE UserId=? AND CategoryId=?", 'ii', array((int)$userid, (int)$id))
    );
    $budgetUse = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT COUNT(*) AS total FROM tblbudgets WHERE UserId=? AND CategoryId=?", 'ii', array((int)$userid, (int)$id))
    );

    if (($expenseUse && (int)$expenseUse['total'] > 0) || ($budgetUse && (int)$budgetUse['total'] > 0)) {
      return array('ok' => false, 'message' => 'This category is already used by expenses or budgets, so it cannot be deleted.');
    }

    $stmt = expense_prepare_and_execute($con, "DELETE FROM tblcategories WHERE ID=? AND UserId=?", 'ii', array((int)$id, (int)$userid));
    if (!$stmt || mysqli_stmt_affected_rows($stmt) < 1) {
      expense_close_statement($stmt);
      return array('ok' => false, 'message' => 'Unable to delete this category.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Category deleted successfully.');
  }
}

if (!function_exists('budget_save')) {
  // Creates the budget of a category for a month and currency, or updates it when it already exists.
  function budget_save($con, $userid, $categoryId, $month, $currency, $amount) {
    $amount = trim((string)$amount);

    if ((int)$categoryId <= 0) {
      return array('ok' => false, 'message' => 'Please choose a category for the budget.');
    }

    if ($amount == '' || !is_numeric($amount) || (float)$amount <= 0) {
      return array('ok' => false, 'message' => 'Please enter a valid budget amount greater than zero.');
    }

    if (!expense_find_category_by_id($con, $userid, (int)$categoryId)) {
      return array('ok' => false, 'message' => 'The selected category is invalid.');
    }

    $existing = expense_fetch_one_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT ID FROM tblbudgets WHERE UserId=? AND CategoryId=? AND BudgetMonth=? AND Currency=? LIMIT 1",
        'iiss',
        array((int)$userid, (int)$categoryId, $month, $currency)
      )
    );

    if ($existing) {
      $stmt = expense_prepare_and_execute($con, "UPDATE tblbudgets SET BudgetAmount=? WHERE ID=? AND UserId=?", 'dii', array((float)$amount, (int)$existing['ID'], (int)$userid));
    } else {
      $stmt = expense_prepare_and_execute(
        $con,
        "INSERT INTO tblbudgets (UserId, CategoryId, BudgetMonth, Currency, BudgetAmount) VALUES (?, ?, ?, ?, ?)",
        'iissd',
        array((int)$userid, (int)$categoryId, $month, $currency, (float)$amount)
      );
    }

    if (!$stmt) {
      return array('ok' => false, 'message' => 'Unable to save this budget.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Budget saved successfully.');
  }
}

if (!function_exists('budget_remove')) {
  function budget_remove($con, $userid, $budgetId) {
    $stmt = expense_prepare_and_execute($con, "DELETE FROM tblbudgets WHERE ID=? AND UserId=?", 'ii', array((int)$budgetId, (int)$userid));
    if (!$stmt || mysqli_stmt_affected_rows($stmt) < 1) {
      expense_close_statement($stmt);
      return array('ok' => false, 'message' => 'Unable to delete this budget.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Budget deleted successfully.');
  }
}

if (!function_exists('category_overview')) {
  // Every category of the user with what was spent in the month and the budget for it.
  function category_overview($con, $userid, $month, $currency) {
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    return expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT c.ID, c.CategoryName,
          COALESCE(SUM(CASE WHEN e.ExpenseDate BETWEEN ? AND ? AND e.Currency=? THEN e.ExpenseCost ELSE 0 END), 0) AS month_spent,
          (
            SELECT b.BudgetAmount
            FROM tblbudgets b
            WHERE b.UserId=c.UserId AND b.CategoryId=c.ID AND b.BudgetMonth=? AND b.Currency=?
            LIMIT 1
          ) AS month_budget
         FROM tblcategories c
         LEFT JOIN tblexpense e ON e.CategoryId=c.ID AND e.UserId=c.UserId
         WHERE c.UserId=?
         GROUP BY c.ID, c.CategoryName
         ORDER BY c.CategoryName ASC",
        'sssssi',
        array($monthStart, $monthEnd, $currency, $month, $currency, (int)$userid)
      )
    );
  }
}

if (!function_exists('budget_list')) {
  function budget_list($con, $userid, $month, $currency) {
    return expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT b.ID, b.BudgetMonth, b.Currency, b.BudgetAmount, c.CategoryName
         FROM tblbudgets b
         INNER JOIN tblcategories c ON c.ID=b.CategoryId AND c.UserId=b.UserId
         WHERE b.UserId=? AND b.BudgetMonth=? AND b.Currency=?
         ORDER BY c.CategoryName ASC",
        'iss',
        array((int)$userid, $month, $currency)
      )
    );
  }
}
