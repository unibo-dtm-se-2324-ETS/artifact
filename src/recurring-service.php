<?php
// Recurring expenses: the rules that create an expense every week or month.
// The processing of due rules is expense_process_recurring() in expense-helpers.php.
// Requires src/expense-helpers.php to be loaded first.

if (!function_exists('recurring_find')) {
  function recurring_find($con, $userid, $id) {
    return expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT * FROM tblrecurring WHERE ID=? AND UserId=? LIMIT 1", 'ii', array((int)$id, (int)$userid))
    );
  }
}

if (!function_exists('recurring_list')) {
  // Active rules first, then by the next run date.
  function recurring_list($con, $userid) {
    return expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT r.*, c.CategoryName
         FROM tblrecurring r
         LEFT JOIN tblcategories c ON c.ID=r.CategoryId AND c.UserId=r.UserId
         WHERE r.UserId=?
         ORDER BY r.IsActive DESC, r.NextRunDate ASC, r.ID DESC",
        'i',
        array((int)$userid)
      )
    );
  }
}

if (!function_exists('recurring_form_defaults')) {
  // The values shown in the form for a stored rule (or for a new one).
  function recurring_form_defaults($row) {
    return array(
      'item' => $row ? $row['ExpenseItem'] : '',
      'cost' => $row ? $row['ExpenseCost'] : '',
      'currency' => $row ? expense_selected_currency($row['Currency']) : 'USD',
      'categoryid' => $row ? (string)$row['CategoryId'] : '',
      'frequency' => $row ? $row['Frequency'] : 'monthly',
      'startdate' => $row ? $row['StartDate'] : date('Y-m-d'),
      'notes' => $row ? $row['Notes'] : ''
    );
  }
}

if (!function_exists('recurring_form_from_input')) {
  // Cleans the submitted form.
  function recurring_form_from_input($input) {
    return array(
      'item' => isset($input['item']) ? trim($input['item']) : '',
      'cost' => isset($input['cost']) ? trim($input['cost']) : '',
      'currency' => expense_selected_currency(isset($input['currency']) ? $input['currency'] : 'USD'),
      'categoryid' => isset($input['categoryid']) ? trim($input['categoryid']) : '',
      'frequency' => isset($input['frequency']) && $input['frequency'] === 'weekly' ? 'weekly' : 'monthly',
      'startdate' => isset($input['startdate']) ? trim($input['startdate']) : date('Y-m-d'),
      'notes' => isset($input['notes']) ? trim($input['notes']) : ''
    );
  }
}

if (!function_exists('recurring_validate_form')) {
  // Returns the first validation message, or '' when the form is valid.
  function recurring_validate_form($form) {
    if ($form['item'] == '' || $form['cost'] == '' || !is_numeric($form['cost']) || (float)$form['cost'] <= 0) {
      return 'Please fill in a valid item and amount.';
    }

    if ((int)$form['categoryid'] <= 0) {
      return 'Please choose a category.';
    }

    if ($form['startdate'] == '' || !strtotime($form['startdate'])) {
      return 'Please choose a valid start date.';
    }

    return '';
  }
}

if (!function_exists('recurring_save')) {
  // Creates a rule, or updates the rule with the given id. Returns array('ok' => bool, 'message' => string).
  function recurring_save($con, $userid, $id, $form) {
    $message = recurring_validate_form($form);
    if ($message !== '') {
      return array('ok' => false, 'message' => $message);
    }

    $categoryId = (int)$form['categoryid'];

    if ((int)$id > 0) {
      $stmt = expense_prepare_and_execute(
        $con,
        "UPDATE tblrecurring
         SET ExpenseItem=?, ExpenseCost=?, Currency=?, CategoryId=?, Notes=?, Frequency=?, StartDate=?, NextRunDate=?
         WHERE ID=? AND UserId=?",
        'sdsissssii',
        array($form['item'], (float)$form['cost'], $form['currency'], $categoryId, $form['notes'], $form['frequency'], $form['startdate'], $form['startdate'], (int)$id, (int)$userid)
      );
      $failure = 'Unable to update recurring expense.';
      $success = 'Recurring expense updated.';
    } else {
      $stmt = expense_prepare_and_execute(
        $con,
        "INSERT INTO tblrecurring (UserId, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, Frequency, StartDate, NextRunDate, IsActive)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
        'isdsissss',
        array((int)$userid, $form['item'], (float)$form['cost'], $form['currency'], $categoryId, $form['notes'], $form['frequency'], $form['startdate'], $form['startdate'])
      );
      $failure = 'Unable to create recurring expense.';
      $success = 'Recurring expense created.';
    }

    if (!$stmt) {
      return array('ok' => false, 'message' => $failure);
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => $success);
  }
}

if (!function_exists('recurring_delete')) {
  function recurring_delete($con, $userid, $id) {
    $stmt = expense_prepare_and_execute($con, "DELETE FROM tblrecurring WHERE ID=? AND UserId=?", 'ii', array((int)$id, (int)$userid));
    if (!$stmt || mysqli_stmt_affected_rows($stmt) < 1) {
      expense_close_statement($stmt);
      return array('ok' => false, 'message' => 'Unable to delete recurring expense.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Recurring expense deleted.');
  }
}

if (!function_exists('recurring_toggle')) {
  // Pauses an active rule and activates a paused one.
  function recurring_toggle($con, $userid, $id, $currentlyActive) {
    $isActive = (int)$currentlyActive === 1 ? 0 : 1;
    $stmt = expense_prepare_and_execute($con, "UPDATE tblrecurring SET IsActive=? WHERE ID=? AND UserId=?", 'iii', array($isActive, (int)$id, (int)$userid));
    if (!$stmt) {
      return array('ok' => false, 'message' => 'Unable to update recurring expense.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => $isActive ? 'Recurring expense activated.' : 'Recurring expense paused.');
  }
}
