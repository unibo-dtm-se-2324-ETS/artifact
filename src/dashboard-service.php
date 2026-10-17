<?php
// Dashboard: the totals, charts and budget progress shown on the dashboard page.
// Requires src/expense-helpers.php to be loaded first.

if (!function_exists('dashboard_scalar')) {
  function dashboard_scalar($con, $sql, $types, $params, $field) {
    $row = expense_fetch_one_assoc(expense_prepare_and_execute($con, $sql, $types, $params));
    return $row && isset($row[$field]) ? (float)$row[$field] : 0;
  }
}

if (!function_exists('dashboard_change_text')) {
  // The change between two periods as text, for example '+25%' or '-10%'.
  function dashboard_change_text($current, $previous) {
    if ((float)$previous <= 0) {
      return (float)$current > 0 ? 'New activity' : 'No change';
    }

    $percent = (($current - $previous) / $previous) * 100;
    $prefix = $percent >= 0 ? '+' : '';

    return $prefix . number_format($percent, 0) . '%';
  }
}

if (!function_exists('dashboard_delta_class')) {
  // Direction of a change label, used to colour the trend chips.
  // For spending, less than before is "good" (green), more is "up" (red).
  function dashboard_delta_class($text) {
    if ($text === '' || $text === 'No change') return 'flat';
    if ($text === 'New activity') return 'up';
    return strpos($text, '-') === 0 ? 'down' : 'up';
  }
}

if (!function_exists('dashboard_expense_total')) {
  // Sum of the user's expenses in one currency, optionally between two dates.
  function dashboard_expense_total($con, $userid, $currency, $from = null, $to = null) {
    if ($from === null) {
      return dashboard_scalar($con, "SELECT SUM(ExpenseCost) AS total FROM tblexpense WHERE UserId=? AND Currency=?", 'is', array((int)$userid, $currency), 'total');
    }

    return dashboard_scalar(
      $con,
      "SELECT SUM(ExpenseCost) AS total FROM tblexpense WHERE UserId=? AND Currency=? AND ExpenseDate BETWEEN ? AND ?",
      'isss',
      array((int)$userid, $currency, $from, $to),
      'total'
    );
  }
}

if (!function_exists('dashboard_build')) {
  // Collects everything the dashboard page shows. $now is a timestamp (default: the current time),
  // so that the periods can be fixed in tests.
  function dashboard_build($con, $userid, $selectedCurrency, $now = null) {
    $userid = (int)$userid;
    $now = $now === null ? time() : (int)$now;

    $today = date('Y-m-d', $now);
    $yesterday = date('Y-m-d', strtotime('-1 day', $now));
    $weekStart = date('Y-m-d', strtotime('-6 days', $now));
    $previousWeekStart = date('Y-m-d', strtotime('-13 days', $now));
    $previousWeekEnd = date('Y-m-d', strtotime('-7 days', $now));
    $monthStart = date('Y-m-01', $now);
    $monthEnd = date('Y-m-t', $now);
    $previousMonthStart = date('Y-m-01', strtotime('first day of last month', $now));
    $previousMonthEnd = date('Y-m-t', strtotime('first day of last month', $now));
    $yearStart = date('Y-01-01', $now);
    $selectedMonthLabel = date('F Y', strtotime($monthStart));
    $last24HoursLabel = date('M j, g:i A', strtotime('-24 hours', $now)) . ' - ' . date('M j, g:i A', $now);

    $sums = array(
      'last24h' => dashboard_scalar($con, "SELECT SUM(ExpenseCost) AS total FROM tblexpense WHERE UserId=? AND Currency=? AND CreatedAt BETWEEN DATE_SUB(NOW(), INTERVAL 24 HOUR) AND NOW()", 'is', array($userid, $selectedCurrency), 'total'),
      'today' => dashboard_expense_total($con, $userid, $selectedCurrency, $today, $today),
      'yesterday' => dashboard_expense_total($con, $userid, $selectedCurrency, $yesterday, $yesterday),
      'week' => dashboard_expense_total($con, $userid, $selectedCurrency, $weekStart, $today),
      'previousWeek' => dashboard_expense_total($con, $userid, $selectedCurrency, $previousWeekStart, $previousWeekEnd),
      'month' => dashboard_expense_total($con, $userid, $selectedCurrency, $monthStart, $monthEnd),
      'previousMonth' => dashboard_expense_total($con, $userid, $selectedCurrency, $previousMonthStart, $previousMonthEnd),
      'year' => dashboard_expense_total($con, $userid, $selectedCurrency, $yearStart, $today),
      'total' => dashboard_expense_total($con, $userid, $selectedCurrency)
    );

    $currencyTotals = array();
    foreach (expense_currency_options() as $currency) {
      $currencyTotals[$currency] = 0;
    }
    $currencyRows = expense_fetch_all_assoc(expense_prepare_and_execute($con, "SELECT Currency, SUM(ExpenseCost) AS total FROM tblexpense WHERE UserId=? GROUP BY Currency", 'i', array($userid)));
    foreach ($currencyRows as $currencyRow) {
      $currencyTotals[strtoupper(trim($currencyRow['Currency']))] = (float)$currencyRow['total'];
    }
    // Only show currencies that actually have spending, so the table is not padded with zeroes.
    $currencyTotalsUsed = array_filter($currencyTotals, function ($total) { return (float)$total > 0; });

    $dayRows = expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT DAY(ExpenseDate) AS day_number, SUM(ExpenseCost) AS total
         FROM tblexpense
         WHERE UserId=? AND Currency=? AND ExpenseDate BETWEEN ? AND ?
         GROUP BY DAY(ExpenseDate)
         ORDER BY DAY(ExpenseDate) ASC",
        'isss',
        array($userid, $selectedCurrency, $monthStart, $monthEnd)
      )
    );
    $dayMap = array();
    foreach ($dayRows as $row) {
      $dayMap[(int)$row['day_number']] = (float)$row['total'];
    }
    $dayLabels = array();
    $dayValues = array();
    $daysInMonth = (int)date('t', strtotime($monthStart));
    for ($day = 1; $day <= $daysInMonth; $day++) {
      $dayLabels[] = (string)$day;
      $dayValues[] = isset($dayMap[$day]) ? $dayMap[$day] : 0;
    }

    $categoryRows = expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT
          COALESCE(c.CategoryName, 'Uncategorized') AS label,
          SUM(e.ExpenseCost) AS total
         FROM tblexpense e
         LEFT JOIN tblcategories c ON c.ID=e.CategoryId AND c.UserId=e.UserId
         WHERE e.UserId=? AND e.Currency=? AND e.ExpenseDate BETWEEN ? AND ?
         GROUP BY COALESCE(c.CategoryName, 'Uncategorized')
         ORDER BY total DESC
         LIMIT 5",
        'isss',
        array($userid, $selectedCurrency, $monthStart, $monthEnd)
      )
    );
    $topCategoryLabels = array();
    $topCategoryValues = array();
    foreach ($categoryRows as $row) {
      $topCategoryLabels[] = $row['label'];
      $topCategoryValues[] = (float)$row['total'];
    }
    $topChartColors = array('#18c7b8', '#2563eb', '#f59e0b', '#fb7185', '#0f172a');
    $topCategoryTotal = array_sum($topCategoryValues);
    $topCategorySegments = array();
    if ($topCategoryTotal > 0) {
      $circumference = 2 * pi() * 84;
      $offset = 0;
      foreach ($topCategoryValues as $index => $value) {
        $segmentLength = ($value / $topCategoryTotal) * $circumference;
        $topCategorySegments[] = array(
          'label' => $topCategoryLabels[$index],
          'value' => $value,
          'color' => $topChartColors[$index % count($topChartColors)],
          'dash' => $segmentLength,
          'gap' => $circumference - $segmentLength,
          'offset' => -$offset
        );
        $offset += $segmentLength;
      }
    }

    $activeDays = (int)dashboard_scalar($con, "SELECT COUNT(DISTINCT ExpenseDate) AS total FROM tblexpense WHERE UserId=? AND Currency=? AND ExpenseDate BETWEEN ? AND ?", 'isss', array($userid, $selectedCurrency, $monthStart, $monthEnd), 'total');
    $avgPerActiveDay = $activeDays > 0 ? ($sums['month'] / $activeDays) : 0;

    $latestExpense = expense_fetch_one_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT e.ExpenseItem, e.ExpenseCost, e.ExpenseDate, e.CreatedAt, COALESCE(c.CategoryName, 'Uncategorized') AS CategoryName
         FROM tblexpense e
         LEFT JOIN tblcategories c ON c.ID=e.CategoryId AND c.UserId=e.UserId
         WHERE e.UserId=? AND e.Currency=?
         ORDER BY e.CreatedAt DESC, e.ID DESC
         LIMIT 1",
        'is',
        array($userid, $selectedCurrency)
      )
    );

    $budgetRows = expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT c.ID, c.CategoryName,
          COALESCE(SUM(e.ExpenseCost), 0) AS spent,
          COALESCE(MAX(b.BudgetAmount), 0) AS budget
         FROM tblcategories c
         LEFT JOIN tblexpense e
           ON e.CategoryId=c.ID
          AND e.UserId=c.UserId
          AND e.Currency=?
          AND e.ExpenseDate BETWEEN ? AND ?
         LEFT JOIN tblbudgets b
           ON b.CategoryId=c.ID
          AND b.UserId=c.UserId
          AND b.BudgetMonth=?
          AND b.Currency=?
         WHERE c.UserId=?
         GROUP BY c.ID, c.CategoryName
         HAVING spent > 0 OR budget > 0
         ORDER BY budget DESC, spent DESC, c.CategoryName ASC",
        'sssssi',
        array($selectedCurrency, $monthStart, $monthEnd, expense_month_key($monthStart), $selectedCurrency, $userid)
      )
    );
    $budgetTotal = 0;
    $budgetSpent = 0;
    $overBudgetCount = 0;
    foreach ($budgetRows as $index => $budgetRow) {
      $budgetRows[$index]['spent'] = (float)$budgetRow['spent'];
      $budgetRows[$index]['budget'] = (float)$budgetRow['budget'];
      $budgetRows[$index]['progress'] = expense_budget_progress($budgetRows[$index]['spent'], $budgetRows[$index]['budget']);
      $budgetRows[$index]['remaining'] = $budgetRows[$index]['budget'] - $budgetRows[$index]['spent'];
      $budgetRows[$index]['status_class'] = 'safe';
      $budgetRows[$index]['status_text'] = $budgetRows[$index]['budget'] > 0 ? number_format($budgetRows[$index]['progress'], 0) . '% used' : 'No budget';

      if ($budgetRows[$index]['budget'] > 0 && $budgetRows[$index]['spent'] > $budgetRows[$index]['budget']) {
        $budgetRows[$index]['status_class'] = 'danger';
        $budgetRows[$index]['status_text'] = 'Over budget';
        $overBudgetCount++;
      } elseif ($budgetRows[$index]['budget'] > 0 && $budgetRows[$index]['progress'] >= 80) {
        $budgetRows[$index]['status_class'] = 'warn';
      }

      $budgetTotal += $budgetRows[$index]['budget'];
      $budgetSpent += $budgetRows[$index]['spent'];
    }
    $budgetRemaining = $budgetTotal - $budgetSpent;
    $budgetProgress = expense_budget_progress($budgetSpent, $budgetTotal);
    $weeklyChangeText = dashboard_change_text($sums['week'], $sums['previousWeek']);
    $monthlyChangeText = dashboard_change_text($sums['month'], $sums['previousMonth']);

    // Reusable data for the repeated UI blocks so the markup stays a simple loop.
    $metricCards = array(
      array('label' => 'Last 24 Hours', 'value' => $sums['last24h'],  'note' => $last24HoursLabel,      'icon' => 'fa-clock-o',    'accent' => 'accent-blue',   'chip' => null),
      array('label' => 'Last 7 Days',   'value' => $sums['week'],     'note' => 'vs previous 7 days',    'icon' => 'fa-calendar-o', 'accent' => 'accent-teal',   'chip' => $weeklyChangeText),
      array('label' => 'This Month',    'value' => $sums['month'],    'note' => 'vs previous month',     'icon' => 'fa-line-chart', 'accent' => 'accent-indigo', 'chip' => $monthlyChangeText),
      array('label' => 'Total',         'value' => $sums['total'],    'note' => 'All records in ' . $selectedCurrency, 'icon' => 'fa-money', 'accent' => 'accent-slate', 'chip' => null),
    );

    $quickStats = array(
      array('label' => 'Today',            'value' => expense_money($sums['today'], $selectedCurrency)),
      array('label' => 'Yesterday',        'value' => expense_money($sums['yesterday'], $selectedCurrency)),
      array('label' => 'This Year',        'value' => expense_money($sums['year'], $selectedCurrency)),
      array('label' => 'Active Days',      'value' => (string)(int)$activeDays),
      array('label' => 'Avg / Active Day', 'value' => expense_money($avgPerActiveDay, $selectedCurrency)),
    );

    return compact(
      'sums', 'metricCards', 'quickStats', 'currencyTotalsUsed', 'dayLabels', 'dayValues',
      'topCategoryTotal', 'topCategorySegments', 'latestExpense', 'budgetRows', 'budgetTotal',
      'budgetSpent', 'budgetRemaining', 'budgetProgress', 'overBudgetCount', 'activeDays',
      'avgPerActiveDay', 'weeklyChangeText', 'monthlyChangeText', 'selectedMonthLabel'
    );
  }
}
