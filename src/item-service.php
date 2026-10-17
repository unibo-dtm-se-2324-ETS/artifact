<?php
// Items: the names a user picks from when adding an expense.
// Requires src/expense-helpers.php to be loaded first.

if (!function_exists('item_list')) {
  function item_list($con, $userid) {
    return expense_fetch_all_assoc(
      expense_prepare_and_execute($con, "SELECT ID, ItemName, CreatedAt FROM tblitems WHERE UserId=? ORDER BY ItemName ASC", 'i', array((int)$userid))
    );
  }
}

if (!function_exists('item_find')) {
  function item_find($con, $userid, $id) {
    return expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID, ItemName FROM tblitems WHERE ID=? AND UserId=? LIMIT 1", 'ii', array((int)$id, (int)$userid))
    );
  }
}

if (!function_exists('item_add')) {
  // Returns array('ok' => bool, 'message' => string).
  function item_add($con, $userid, $name) {
    $name = trim((string)$name);

    if ($name == '') {
      return array('ok' => false, 'message' => 'Item name is required.');
    }

    $exists = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID FROM tblitems WHERE UserId=? AND ItemName=? LIMIT 1", 'is', array((int)$userid, $name))
    );
    if ($exists) {
      return array('ok' => false, 'message' => 'This item already exists.');
    }

    $stmt = expense_prepare_and_execute($con, "INSERT INTO tblitems (UserId, ItemName) VALUES (?, ?)", 'is', array((int)$userid, $name));
    if (!$stmt) {
      return array('ok' => false, 'message' => 'Something went wrong. Please try again.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Item added successfully');
  }
}

if (!function_exists('item_rename')) {
  function item_rename($con, $userid, $id, $name) {
    $name = trim((string)$name);

    if ($name == '') {
      return array('ok' => false, 'message' => 'Item name is required.');
    }

    $exists = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID FROM tblitems WHERE UserId=? AND ItemName=? AND ID<>? LIMIT 1", 'isi', array((int)$userid, $name, (int)$id))
    );
    if ($exists) {
      return array('ok' => false, 'message' => 'This item already exists.');
    }

    $stmt = expense_prepare_and_execute($con, "UPDATE tblitems SET ItemName=? WHERE ID=? AND UserId=?", 'sii', array($name, (int)$id, (int)$userid));
    if (!$stmt) {
      return array('ok' => false, 'message' => 'Something went wrong. Please try again.');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'Item updated successfully');
  }
}

if (!function_exists('item_delete')) {
  // Returns false when nothing was deleted, for example for an unknown id or another user's item.
  function item_delete($con, $userid, $id) {
    $stmt = expense_prepare_and_execute($con, "DELETE FROM tblitems WHERE ID=? AND UserId=?", 'ii', array((int)$id, (int)$userid));
    if (!$stmt) {
      return false;
    }

    $deleted = mysqli_stmt_affected_rows($stmt) > 0;
    expense_close_statement($stmt);

    return $deleted;
  }
}
