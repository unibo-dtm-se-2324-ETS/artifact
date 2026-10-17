<?php
// Accounts: registration, login, password handling and the user profile.
// Requires src/expense-helpers.php to be loaded first.
//
// Passwords are stored with password_hash(). Accounts created before that
// change still have an MD5 hash; they keep working, and the hash is replaced
// with a password_hash() hash the next time the user logs in.

if (!function_exists('auth_hash_password')) {
  function auth_hash_password($plain) {
    return password_hash((string)$plain, PASSWORD_DEFAULT);
  }
}

if (!function_exists('auth_is_legacy_hash')) {
  // True for the 32 hexadecimal characters of an old MD5 password hash.
  function auth_is_legacy_hash($stored) {
    return (bool)preg_match('/^[a-f0-9]{32}$/i', (string)$stored);
  }
}

if (!function_exists('auth_verify_password')) {
  function auth_verify_password($plain, $stored) {
    $stored = (string)$stored;

    if ($stored === '') {
      return false;
    }

    if (auth_is_legacy_hash($stored)) {
      return hash_equals(strtolower($stored), md5((string)$plain));
    }

    return password_verify((string)$plain, $stored);
  }
}

if (!function_exists('auth_needs_rehash')) {
  function auth_needs_rehash($stored) {
    return auth_is_legacy_hash($stored) || password_needs_rehash((string)$stored, PASSWORD_DEFAULT);
  }
}

if (!function_exists('auth_store_password')) {
  function auth_store_password($con, $userid, $plain) {
    $stmt = expense_prepare_and_execute($con, "UPDATE tbluser SET Password=? WHERE ID=?", 'si', array(auth_hash_password($plain), (int)$userid));

    if (!$stmt) {
      return false;
    }

    expense_close_statement($stmt);
    return true;
  }
}

if (!function_exists('auth_login')) {
  // Returns the user id when the email and password match, otherwise 0.
  function auth_login($con, $email, $password) {
    $user = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID, Password FROM tbluser WHERE Email=? LIMIT 1", 's', array((string)$email))
    );

    if (!$user || !auth_verify_password($password, $user['Password'])) {
      return 0;
    }

    if (auth_needs_rehash($user['Password'])) {
      auth_store_password($con, $user['ID'], $password);
    }

    return (int)$user['ID'];
  }
}

if (!function_exists('auth_register')) {
  // Creates an account. Returns array('ok' => bool, 'message' => string).
  function auth_register($con, $name, $mobile, $email, $password) {
    $existing = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID FROM tbluser WHERE Email=? LIMIT 1", 's', array((string)$email))
    );

    if ($existing) {
      return array('ok' => false, 'message' => 'This email is already associated with another account');
    }

    $stmt = expense_prepare_and_execute(
      $con,
      "INSERT INTO tbluser (FullName, MobileNumber, Email, Password) VALUES (?, ?, ?, ?)",
      'ssss',
      array((string)$name, (string)$mobile, (string)$email, auth_hash_password($password))
    );

    if (!$stmt) {
      return array('ok' => false, 'message' => 'Something Went Wrong. Please try again');
    }

    expense_close_statement($stmt);
    return array('ok' => true, 'message' => 'You have successfully registered');
  }
}

if (!function_exists('auth_find_user_by_contact')) {
  // Used by the password recovery: returns the user id for a matching email and mobile number, otherwise 0.
  function auth_find_user_by_contact($con, $email, $contact) {
    $user = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT ID FROM tbluser WHERE Email=? AND MobileNumber=? LIMIT 1", 'ss', array((string)$email, (string)$contact))
    );

    return $user ? (int)$user['ID'] : 0;
  }
}

if (!function_exists('auth_reset_password')) {
  // Sets a new password for the account that matches the email and mobile number.
  function auth_reset_password($con, $email, $contact, $newPassword) {
    $userId = auth_find_user_by_contact($con, $email, $contact);

    return $userId > 0 && auth_store_password($con, $userId, $newPassword);
  }
}

if (!function_exists('auth_change_password')) {
  // Changes the password after checking the current one.
  function auth_change_password($con, $userid, $currentPassword, $newPassword) {
    $user = expense_fetch_one_assoc(
      expense_prepare_and_execute($con, "SELECT Password FROM tbluser WHERE ID=? LIMIT 1", 'i', array((int)$userid))
    );

    if (!$user || !auth_verify_password($currentPassword, $user['Password'])) {
      return false;
    }

    return auth_store_password($con, $userid, $newPassword);
  }
}

if (!function_exists('profile_get')) {
  function profile_get($con, $userid) {
    return expense_fetch_one_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT FullName, Email, MobileNumber, RegDate, DefaultCurrency, DefaultCategoryId FROM tbluser WHERE ID=? LIMIT 1",
        'i',
        array((int)$userid)
      )
    );
  }
}

if (!function_exists('profile_full_name')) {
  function profile_full_name($con, $userid) {
    $profile = profile_get($con, $userid);

    return $profile ? $profile['FullName'] : '';
  }
}

if (!function_exists('profile_form_defaults')) {
  // The values shown in the profile form for a stored profile (or for no profile).
  function profile_form_defaults($profile) {
    return array(
      'fullname' => $profile ? $profile['FullName'] : '',
      'contactnumber' => $profile ? $profile['MobileNumber'] : '',
      'defaultcurrency' => $profile ? expense_selected_currency($profile['DefaultCurrency']) : 'USD',
      'defaultcategoryid' => $profile && $profile['DefaultCategoryId'] ? (string)$profile['DefaultCategoryId'] : ''
    );
  }
}

if (!function_exists('profile_form_from_input')) {
  // Cleans the submitted profile form.
  function profile_form_from_input($input) {
    return array(
      'fullname' => isset($input['fullname']) ? trim($input['fullname']) : '',
      'contactnumber' => isset($input['contactnumber']) ? trim($input['contactnumber']) : '',
      'defaultcurrency' => expense_selected_currency(isset($input['defaultcurrency']) ? $input['defaultcurrency'] : 'USD'),
      'defaultcategoryid' => isset($input['defaultcategoryid']) ? trim($input['defaultcategoryid']) : ''
    );
  }
}

if (!function_exists('profile_update')) {
  // Saves the profile and the defaults. Returns '' on success, otherwise the message to show.
  function profile_update($con, $userid, $form) {
    $defaultCategoryId = (int)$form['defaultcategoryid'];

    if ($form['fullname'] == '') {
      return 'Full name is required.';
    }

    if ($defaultCategoryId > 0 && !expense_find_category_by_id($con, $userid, $defaultCategoryId)) {
      return 'The selected default category is invalid.';
    }

    $stmt = expense_prepare_and_execute(
      $con,
      "UPDATE tbluser SET FullName=?, MobileNumber=?, DefaultCurrency=?, DefaultCategoryId=? WHERE ID=?",
      'sssii',
      array($form['fullname'], $form['contactnumber'], $form['defaultcurrency'], $defaultCategoryId, (int)$userid)
    );

    if (!$stmt) {
      return 'Something went wrong. Please try again.';
    }

    expense_close_statement($stmt);
    return '';
  }
}
