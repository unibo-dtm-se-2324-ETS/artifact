<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

/**
 * Integration tests for the detailed reports per day, month and year.
 */
final class ReportServiceDatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/report-service.php';
    }

    private function expense(int $userId, float $cost, string $date, int $categoryId = 0, string $currency = 'USD'): void
    {
        mysqli_query(
            self::$con,
            sprintf("INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId) VALUES (%d, '%s', 'x', %F, '%s', %d)", $userId, $date, $cost, $currency, $categoryId)
        );
    }

    private function seed(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (ID, UserId, CategoryName) VALUES (1, 1, 'Food'), (2, 1, 'Bills')");
        $this->expense(1, 10, '2026-01-05', 1);
        $this->expense(1, 20, '2026-01-05', 2);
        $this->expense(1, 5, '2026-01-20', 1);
        $this->expense(1, 50, '2026-02-10', 2);
        $this->expense(1, 7, '2026-03-01', 0);
        $this->expense(1, 100, '2027-06-15', 1);
        $this->expense(1, 999, '2026-01-05', 1, 'EUR');
        $this->expense(2, 888, '2026-01-05', 1);
    }

    // the daily report

    public function testDailyReportListsEveryDayWithItsTotal(): void
    {
        $this->seed();

        $report = report_build(self::$con, 1, 'USD', 'day', '2026-01-01', '2026-03-31');

        $this->assertSame('', $report['msg']);
        $this->assertSame(array('Jan 5', 'Jan 20', 'Feb 10', 'Mar 1'), $report['labels']);
        $this->assertSame(array(30.0, 5.0, 50.0, 7.0), $report['values']);
        $this->assertEquals(92, $report['totalExpense']);
        $this->assertSame(4, $report['recordCount']);
        $this->assertEquals(23, $report['avgPeriodValue']);
        $this->assertSame('2026-01-05', $report['rows'][0]['ExpenseDate']);
    }

    public function testDailyReportFindsTheHighestDay(): void
    {
        $this->seed();

        $report = report_build(self::$con, 1, 'USD', 'day', '2026-01-01', '2026-03-31');

        $this->assertEquals(50, $report['topPeriodValue']);
        $this->assertSame('February 10, 2026', $report['topPeriodLabel']);
    }

    public function testDailyReportBuildsTheCategoryBreakdownAndTheExportLink(): void
    {
        $this->seed();

        $report = report_build(self::$con, 1, 'USD', 'day', '2026-01-01', '2026-03-31');

        $this->assertSame(array('Bills', 'Food', 'Uncategorized'), $report['categoryLabels']);
        $this->assertSame(array(70.0, 15.0, 7.0), $report['categoryValues']);
        $this->assertSame('Bills', $report['categoryRows'][0]['catname']);
        $this->assertSame('expense-datewise-reports-detailed.php?fromdate=2026-01-01&todate=2026-03-31&currency=USD&export=csv', $report['exportLink']);
    }

    public function testReportsOnlyIncludeTheUsersExpensesInTheChosenCurrency(): void
    {
        $this->seed();

        $eur = report_build(self::$con, 1, 'EUR', 'day', '2026-01-01', '2026-12-31');
        $other = report_build(self::$con, 2, 'USD', 'day', '2026-01-01', '2026-12-31');

        $this->assertEquals(999, $eur['totalExpense']);
        $this->assertEquals(888, $other['totalExpense']);
    }

    public function testAnEmptyRangeGivesAnEmptyButCompleteReport(): void
    {
        $this->seed();

        $report = report_build(self::$con, 1, 'USD', 'day', '2030-01-01', '2030-12-31');

        $this->assertSame('', $report['msg']);
        $this->assertSame(0, $report['recordCount']);
        $this->assertSame('N/A', $report['topPeriodLabel']);
        $this->assertSame(0, $report['avgPeriodValue']);
        $this->assertSame(array(), $report['categoryRows']);
        $this->assertStringContainsString('export=csv', $report['exportLink']);
    }

    public function testAnInvalidRangeOnlyReturnsTheMessage(): void
    {
        $this->seed();

        $missing = report_build(self::$con, 1, 'USD', 'day', '', '2026-03-31');
        $reversed = report_build(self::$con, 1, 'USD', 'day', '2026-03-31', '2026-01-01');

        $this->assertSame('Please choose both dates.', $missing['msg']);
        $this->assertSame('From date cannot be after To date.', $reversed['msg']);
        $this->assertSame(array(), $missing['rows']);
        $this->assertSame('', $missing['exportLink']);
    }

    public function testDatesAreNeverPartOfTheSql(): void
    {
        $this->seed();

        $report = report_build(self::$con, 1, 'USD', 'day', "2026-01-01' OR '1'='1", "2026-12-31' OR '1'='1");

        // The text after the date is not executed: nothing of another user or currency is returned.
        $this->assertSame('', $report['msg']);
        $this->assertEquals(92, $report['totalExpense']);
        $this->assertSame(array('Jan 5', 'Jan 20', 'Feb 10', 'Mar 1'), $report['labels']);
    }

    // the monthly report

    public function testMonthlyReportGroupsByMonth(): void
    {
        $this->seed();

        $report = report_build(self::$con, 1, 'USD', 'month', '2026-01', '2026-03');

        $this->assertSame(array('Jan 2026', 'Feb 2026', 'Mar 2026'), $report['labels']);
        $this->assertSame(array(35.0, 50.0, 7.0), $report['values']);
        $this->assertSame('February 2026', $report['topPeriodLabel']);
        $this->assertSame('2026-01-01', $report['fromDate']);
        $this->assertSame('2026-03-31', $report['toDate']);
        $this->assertSame('expense-monthwise-reports-detailed.php?frommonth=2026-01&tomonth=2026-03&currency=USD&export=csv', $report['exportLink']);
    }

    public function testMonthlyReportIncludesTheWholeLastMonth(): void
    {
        $this->expense(1, 5, '2026-02-28');
        $this->expense(1, 6, '2026-03-01');

        $report = report_build(self::$con, 1, 'USD', 'month', '2026-02', '2026-02');

        $this->assertEquals(5, $report['totalExpense']);
        $this->assertSame(1, $report['recordCount']);
    }

    public function testMonthlyReportValidatesTheRange(): void
    {
        $this->assertSame('Please choose both months.', report_build(self::$con, 1, 'USD', 'month', '', '')['msg']);
        $this->assertSame('From month cannot be after To month.', report_build(self::$con, 1, 'USD', 'month', '2026-05', '2026-01')['msg']);
    }

    // the yearly report

    public function testYearlyReportGroupsByYear(): void
    {
        $this->seed();

        $report = report_build(self::$con, 1, 'USD', 'year', 2026, 2027);

        $this->assertSame(array('2026', '2027'), $report['labels']);
        $this->assertSame(array(92.0, 100.0), $report['values']);
        $this->assertSame('2027', $report['topPeriodLabel']);
        $this->assertEquals(96, $report['avgPeriodValue']);
        $this->assertSame('2026-01-01', $report['fromDate']);
        $this->assertSame('2027-12-31', $report['toDate']);
        $this->assertSame('expense-yearwise-reports-detailed.php?fromyear=2026&toyear=2027&currency=USD&export=csv', $report['exportLink']);
    }

    public function testYearlyReportValidatesTheRange(): void
    {
        $this->assertSame('Please choose both years.', report_build(self::$con, 1, 'USD', 'year', 0, 0)['msg']);
        $this->assertSame('From year cannot be after To year.', report_build(self::$con, 1, 'USD', 'year', 2027, 2026)['msg']);
    }

    // CSV and the older report

    public function testTheCsvLinesMatchTheReportRows(): void
    {
        $this->seed();
        $report = report_build(self::$con, 1, 'USD', 'month', '2026-01', '2026-03');

        $lines = report_csv_lines('month', $report['rows'], 'USD', $report['totalExpense']);

        $this->assertSame(array('Month', 'Total', 'Currency'), $lines[0]);
        $this->assertSame(array('January 2026', '35.00', 'USD'), $lines[1]);
        $this->assertSame(array('Grand Total', '92.00', 'USD'), $lines[4]);
    }

    public function testLegacyDailyTotalsUseOnlyTheUsersExpenses(): void
    {
        $this->seed();

        $rows = report_legacy_daily_totals(self::$con, 1, '2026-01-01', '2026-01-31');

        $this->assertSame(array('2026-01-05', '2026-01-20'), array_column($rows, 'ExpenseDate'));
        $this->assertEquals(1029, $rows[0]['totaldaily']);
        $this->assertSame(array(), report_legacy_daily_totals(self::$con, 3, '2026-01-01', '2026-12-31'));
    }
}
