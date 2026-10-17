<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the report functions that need no database: periods, ranges, labels, links and CSV rows.
 */
final class ReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/report-service.php';
    }

    public function testEachPeriodHasItsOwnPageAndFormFields(): void
    {
        $this->assertSame('expense-datewise-reports-detailed.php', report_period_settings('day')['page']);
        $this->assertSame('expense-monthwise-reports-detailed.php', report_period_settings('month')['page']);
        $this->assertSame('expense-yearwise-reports-detailed.php', report_period_settings('year')['page']);
        $this->assertSame('frommonth', report_period_settings('month')['fromKey']);
        $this->assertSame('toyear', report_period_settings('year')['toKey']);
    }

    public function testAnUnknownPeriodFallsBackToDays(): void
    {
        $this->assertSame(report_period_settings('day'), report_period_settings('decade'));
    }

    public function testInputIsReadFromPostFirstThenGet(): void
    {
        $this->assertSame(
            array('from' => '2026-01-01', 'to' => '2026-02-01'),
            report_read_input('day', array('fromdate' => '2026-01-01'), array('fromdate' => '2000-01-01', 'todate' => '2026-02-01'))
        );
        $this->assertSame(array('from' => '2026-03', 'to' => '2026-04'), report_read_input('month', array(), array('frommonth' => '2026-03', 'tomonth' => '2026-04')));
    }

    public function testMissingInputGivesEmptyValuesAndZeroYears(): void
    {
        $this->assertSame(array('from' => '', 'to' => ''), report_read_input('day', array(), array()));
        $this->assertSame(array('from' => 0, 'to' => 0), report_read_input('year', array(), array()));
    }

    public function testYearsAreReadAsNumbers(): void
    {
        $this->assertSame(array('from' => 2024, 'to' => 2026), report_read_input('year', array('fromyear' => '2024'), array('toyear' => '2026abc')));
    }

    #[DataProvider('rangeProvider')]
    public function testRangeValidationAndDates(string $period, $from, $to, string $message, string $fromDate, string $toDate): void
    {
        $this->assertSame(
            array('message' => $message, 'fromDate' => $fromDate, 'toDate' => $toDate),
            report_range($period, $from, $to)
        );
    }

    public static function rangeProvider(): array
    {
        return array(
            'day, valid' => array('day', '2026-05-01', '2026-05-31', '', '2026-05-01', '2026-05-31'),
            'day, same day' => array('day', '2026-05-01', '2026-05-01', '', '2026-05-01', '2026-05-01'),
            'day, missing from' => array('day', '', '2026-05-31', 'Please choose both dates.', '', ''),
            'day, reversed' => array('day', '2026-06-01', '2026-05-31', 'From date cannot be after To date.', '', ''),
            'month, valid' => array('month', '2026-02', '2026-04', '', '2026-02-01', '2026-04-30'),
            'month, leap february' => array('month', '2028-02', '2028-02', '', '2028-02-01', '2028-02-29'),
            'month, missing to' => array('month', '2026-02', '', 'Please choose both months.', '', ''),
            'month, reversed' => array('month', '2026-05', '2026-02', 'From month cannot be after To month.', '', ''),
            'year, valid' => array('year', 2024, 2026, '', '2024-01-01', '2026-12-31'),
            'year, missing' => array('year', 0, 2026, 'Please choose both years.', '', ''),
            'year, reversed' => array('year', 2027, 2026, 'From year cannot be after To year.', '', ''),
        );
    }

    public function testPeriodDatesAndLabels(): void
    {
        $day = array('ExpenseDate' => '2026-05-09');
        $month = array('reportyear' => 2026, 'reportmonth' => 5);
        $year = array('reportyear' => 2026);

        $this->assertSame('2026-05-09', report_period_date('day', $day));
        $this->assertSame('2026-05-01', report_period_date('month', $month));
        $this->assertSame('2026-01-01', report_period_date('year', $year));

        $this->assertSame('May 9', report_period_label('day', $day));
        $this->assertSame('May 9, 2026', report_period_label('day', $day, true));
        $this->assertSame('May 2026', report_period_label('month', $month));
        $this->assertSame('May 2026', report_period_label('month', $month, true));
        $this->assertSame('2026', report_period_label('year', $year));
        $this->assertSame('2026', report_period_label('year', $year, true));
    }

    public function testExportLinkKeepsTheRangeAndCurrency(): void
    {
        $this->assertSame(
            'expense-datewise-reports-detailed.php?fromdate=2026-05-01&todate=2026-05-31&currency=EUR&export=csv',
            report_export_link('day', '2026-05-01', '2026-05-31', 'EUR')
        );
        $this->assertSame(
            'expense-yearwise-reports-detailed.php?fromyear=2024&toyear=2026&currency=USD&export=csv',
            report_export_link('year', 2024, 2026, 'USD')
        );
    }

    public function testCsvFileNamePrefixes(): void
    {
        $this->assertSame('daily-report', report_csv_name('day'));
        $this->assertSame('monthly-report', report_csv_name('month'));
        $this->assertSame('yearly-report', report_csv_name('year'));
    }

    public function testCsvLinesForEachPeriod(): void
    {
        $this->assertSame(
            array(array('Date', 'Total', 'Currency'), array('2026-05-09', '12.50', 'USD'), array('Grand Total', '12.50', 'USD')),
            report_csv_lines('day', array(array('ExpenseDate' => '2026-05-09', 'totalamount' => '12.5')), 'USD', 12.5)
        );
        $this->assertSame(
            array(array('Month', 'Total', 'Currency'), array('May 2026', '1200.00', 'EUR'), array('Grand Total', '1200.00', 'EUR')),
            report_csv_lines('month', array(array('reportyear' => 2026, 'reportmonth' => 5, 'totalamount' => '1200')), 'EUR', 1200)
        );
        $this->assertSame(
            array(array('Year', 'Total', 'Currency'), array(2026, '3.00', 'USD'), array('Grand Total', '3.00', 'USD')),
            report_csv_lines('year', array(array('reportyear' => 2026, 'totalamount' => '3')), 'USD', 3)
        );
    }

    public function testCsvWithoutRowsHasOnlyTheHeaderAndTheTotal(): void
    {
        $this->assertSame(
            array(array('Date', 'Total', 'Currency'), array('Grand Total', '0.00', 'USD')),
            report_csv_lines('day', array(), 'USD', 0)
        );
    }
}
