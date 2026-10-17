<?php
// Reports: spending per day, month or year, the category breakdown and the CSV export.
// A report period is 'day', 'month' or 'year'.
// Requires src/expense-helpers.php to be loaded first.

if (!function_exists('report_period_settings')) {
  function report_period_settings($period) {
    $settings = array(
      'day' => array(
        'fromKey' => 'fromdate', 'toKey' => 'todate', 'page' => 'expense-datewise-reports-detailed.php',
        'csvName' => 'daily-report', 'csvLabel' => 'Date',
        'missing' => 'Please choose both dates.', 'order' => 'From date cannot be after To date.'
      ),
      'month' => array(
        'fromKey' => 'frommonth', 'toKey' => 'tomonth', 'page' => 'expense-monthwise-reports-detailed.php',
        'csvName' => 'monthly-report', 'csvLabel' => 'Month',
        'missing' => 'Please choose both months.', 'order' => 'From month cannot be after To month.'
      ),
      'year' => array(
        'fromKey' => 'fromyear', 'toKey' => 'toyear', 'page' => 'expense-yearwise-reports-detailed.php',
        'csvName' => 'yearly-report', 'csvLabel' => 'Year',
        'missing' => 'Please choose both years.', 'order' => 'From year cannot be after To year.'
      )
    );

    return isset($settings[$period]) ? $settings[$period] : $settings['day'];
  }
}

if (!function_exists('report_read_input')) {
  // Reads the range from the submitted form (POST first, then GET). Years are numbers, the rest are text.
  function report_read_input($period, $post, $get) {
    $settings = report_period_settings($period);
    $values = array();

    foreach (array('from' => $settings['fromKey'], 'to' => $settings['toKey']) as $name => $key) {
      if (isset($post[$key])) {
        $raw = $post[$key];
      } elseif (isset($get[$key])) {
        $raw = $get[$key];
      } else {
        $raw = '';
      }

      $values[$name] = $period === 'year' ? intval($raw) : $raw;
    }

    return $values;
  }
}

if (!function_exists('report_range')) {
  // Checks the range and turns it into two dates. Returns array('message' => string, 'fromDate' => string, 'toDate' => string);
  // the message is '' when the range is valid.
  function report_range($period, $from, $to) {
    $settings = report_period_settings($period);
    $missing = $period === 'year' ? ($from == 0 || $to == 0) : ($from == '' || $to == '');

    if ($missing) {
      return array('message' => $settings['missing'], 'fromDate' => '', 'toDate' => '');
    }

    if ($from > $to) {
      return array('message' => $settings['order'], 'fromDate' => '', 'toDate' => '');
    }

    if ($period === 'month') {
      return array('message' => '', 'fromDate' => $from . '-01', 'toDate' => date('Y-m-t', strtotime($to . '-01')));
    }

    if ($period === 'year') {
      return array('message' => '', 'fromDate' => $from . '-01-01', 'toDate' => $to . '-12-31');
    }

    return array('message' => '', 'fromDate' => $from, 'toDate' => $to);
  }
}

if (!function_exists('report_period_rows')) {
  // One row per period with its total. Day rows have ExpenseDate, month rows reportyear and reportmonth,
  // year rows reportyear; every row has totalamount.
  function report_period_rows($con, $userid, $currency, $period, $fromDate, $toDate) {
    if ($period === 'month') {
      $sql = "SELECT YEAR(ExpenseDate) AS reportyear, MONTH(ExpenseDate) AS reportmonth, SUM(ExpenseCost) AS totalamount
              FROM tblexpense WHERE UserId=? AND Currency=? AND ExpenseDate BETWEEN ? AND ?
              GROUP BY YEAR(ExpenseDate), MONTH(ExpenseDate) ORDER BY YEAR(ExpenseDate), MONTH(ExpenseDate)";
    } elseif ($period === 'year') {
      $sql = "SELECT YEAR(ExpenseDate) AS reportyear, SUM(ExpenseCost) AS totalamount
              FROM tblexpense WHERE UserId=? AND Currency=? AND ExpenseDate BETWEEN ? AND ?
              GROUP BY YEAR(ExpenseDate) ORDER BY YEAR(ExpenseDate)";
    } else {
      $sql = "SELECT ExpenseDate, SUM(ExpenseCost) AS totalamount
              FROM tblexpense WHERE UserId=? AND Currency=? AND ExpenseDate BETWEEN ? AND ?
              GROUP BY ExpenseDate ORDER BY ExpenseDate ASC";
    }

    return expense_fetch_all_assoc(expense_prepare_and_execute($con, $sql, 'isss', array((int)$userid, $currency, $fromDate, $toDate)));
  }
}

if (!function_exists('report_period_date')) {
  // The first day of the period of a row, as a date string.
  function report_period_date($period, $row) {
    if ($period === 'month') {
      return $row['reportyear'] . '-' . str_pad($row['reportmonth'], 2, '0', STR_PAD_LEFT) . '-01';
    }

    if ($period === 'year') {
      return $row['reportyear'] . '-01-01';
    }

    return $row['ExpenseDate'];
  }
}

if (!function_exists('report_period_label')) {
  // The short label for a chart, or the long label for the "highest period" card.
  function report_period_label($period, $row, $long = false) {
    $date = strtotime(report_period_date($period, $row));

    if ($period === 'month') {
      return date($long ? 'F Y' : 'M Y', $date);
    }

    if ($period === 'year') {
      return (string)$row['reportyear'];
    }

    return date($long ? 'F j, Y' : 'M j', $date);
  }
}

if (!function_exists('report_category_rows')) {
  // The spending per category in the range, highest first.
  function report_category_rows($con, $userid, $currency, $fromDate, $toDate) {
    return expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT COALESCE(c.CategoryName, 'Uncategorized') AS catname, SUM(e.ExpenseCost) AS totalamount
         FROM tblexpense e
         LEFT JOIN tblcategories c ON e.CategoryId = c.ID
         WHERE e.UserId=? AND e.Currency=? AND e.ExpenseDate BETWEEN ? AND ?
         GROUP BY e.CategoryId, c.CategoryName
         ORDER BY totalamount DESC",
        'isss',
        array((int)$userid, $currency, $fromDate, $toDate)
      )
    );
  }
}

if (!function_exists('report_export_link')) {
  function report_export_link($period, $from, $to, $currency) {
    $settings = report_period_settings($period);

    return $settings['page'] . '?' . http_build_query(array(
      $settings['fromKey'] => $from,
      $settings['toKey'] => $to,
      'currency' => $currency,
      'export' => 'csv'
    ));
  }
}

if (!function_exists('report_build')) {
  // Everything a detailed report page shows. On an invalid range only 'msg' is filled in.
  function report_build($con, $userid, $currency, $period, $from, $to) {
    $range = report_range($period, $from, $to);

    $report = array(
      'msg' => $range['message'],
      'fromDate' => $range['fromDate'],
      'toDate' => $range['toDate'],
      'rows' => array(),
      'labels' => array(),
      'values' => array(),
      'totalExpense' => 0,
      'recordCount' => 0,
      'avgPeriodValue' => 0,
      'topPeriodLabel' => 'N/A',
      'topPeriodValue' => 0,
      'exportLink' => '',
      'categoryRows' => array(),
      'categoryLabels' => array(),
      'categoryValues' => array()
    );

    if ($range['message'] !== '') {
      return $report;
    }

    $report['rows'] = report_period_rows($con, $userid, $currency, $period, $range['fromDate'], $range['toDate']);
    foreach ($report['rows'] as $row) {
      $report['labels'][] = report_period_label($period, $row);
      $report['values'][] = (float)$row['totalamount'];
      $report['totalExpense'] += (float)$row['totalamount'];
    }

    $report['recordCount'] = count($report['rows']);
    $report['avgPeriodValue'] = $report['recordCount'] > 0 ? $report['totalExpense'] / $report['recordCount'] : 0;

    if ($report['recordCount'] > 0) {
      $report['topPeriodValue'] = max($report['values']);
      foreach ($report['rows'] as $row) {
        if ((float)$row['totalamount'] === (float)$report['topPeriodValue']) {
          $report['topPeriodLabel'] = report_period_label($period, $row, true);
          break;
        }
      }
    }

    $report['exportLink'] = report_export_link($period, $from, $to, $currency);

    $report['categoryRows'] = report_category_rows($con, $userid, $currency, $range['fromDate'], $range['toDate']);
    foreach ($report['categoryRows'] as $categoryRow) {
      $report['categoryLabels'][] = $categoryRow['catname'] ? $categoryRow['catname'] : 'Uncategorized';
      $report['categoryValues'][] = (float)$categoryRow['totalamount'];
    }

    return $report;
  }
}

if (!function_exists('report_csv_name')) {
  function report_csv_name($period) {
    $settings = report_period_settings($period);

    return $settings['csvName'];
  }
}

if (!function_exists('report_csv_lines')) {
  // The rows of the CSV export: a header, one line per period and a grand total.
  function report_csv_lines($period, $rows, $currency, $totalExpense) {
    $settings = report_period_settings($period);
    $lines = array(array($settings['csvLabel'], 'Total', 'Currency'));

    foreach ($rows as $row) {
      $first = $period === 'month' ? report_period_label('month', $row, true) : ($period === 'year' ? $row['reportyear'] : $row['ExpenseDate']);
      $lines[] = array($first, number_format((float)$row['totalamount'], 2, '.', ''), $currency);
    }

    $lines[] = array('Grand Total', number_format((float)$totalExpense, 2, '.', ''), $currency);

    return $lines;
  }
}

if (!function_exists('report_legacy_daily_totals')) {
  // The older date-range report: the total per day between two dates, in every currency.
  function report_legacy_daily_totals($con, $userid, $fromDate, $toDate) {
    return expense_fetch_all_assoc(
      expense_prepare_and_execute(
        $con,
        "SELECT ExpenseDate, SUM(ExpenseCost) AS totaldaily FROM tblexpense WHERE ExpenseDate BETWEEN ? AND ? AND UserId=? GROUP BY ExpenseDate",
        'ssi',
        array((string)$fromDate, (string)$toDate, (int)$userid)
      )
    );
  }
}
