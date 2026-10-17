<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the parts of the expense service that need no database:
 * form validation, filter normalisation, query building and CSV rows.
 */
final class ExpenseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/expense-service.php';
    }

    private function validForm(): array
    {
        return array('dateexpense' => '2026-05-16', 'item' => 'Coffee', 'costitem' => '3.50');
    }

    // expense_validate_form

    public function testValidFormHasNoError(): void
    {
        $this->assertSame('', expense_validate_form($this->validForm(), 5));
    }

    #[DataProvider('invalidFormProvider')]
    public function testInvalidFormReturnsTheMatchingMessage(array $changes, int $categoryId, string $expected): void
    {
        $form = array_merge($this->validForm(), $changes);

        $this->assertSame($expected, expense_validate_form($form, $categoryId));
    }

    public static function invalidFormProvider(): array
    {
        return array(
            'empty date' => array(array('dateexpense' => ''), 5, 'Please choose a valid expense date.'),
            'unparsable date' => array(array('dateexpense' => 'not a date'), 5, 'Please choose a valid expense date.'),
            'empty item' => array(array('item' => ''), 5, 'Please select an item.'),
            'empty cost' => array(array('costitem' => ''), 5, 'Please enter a valid cost greater than zero.'),
            'text cost' => array(array('costitem' => 'abc'), 5, 'Please enter a valid cost greater than zero.'),
            'zero cost' => array(array('costitem' => '0'), 5, 'Please enter a valid cost greater than zero.'),
            'negative cost' => array(array('costitem' => '-2'), 5, 'Please enter a valid cost greater than zero.'),
            'no category' => array(array(), 0, 'Please choose a category.'),
            'negative category' => array(array(), -1, 'Please choose a category.'),
        );
    }

    public function testValidationReportsTheFirstProblemOnly(): void
    {
        $form = array('dateexpense' => '', 'item' => '', 'costitem' => '');

        $this->assertSame('Please choose a valid expense date.', expense_validate_form($form, 0));
    }

    // expense_normalize_filters

    public function testNormalizeFiltersReturnsDefaultsForEmptyInput(): void
    {
        $this->assertSame(
            array('q' => '', 'currency' => '', 'categoryid' => 0, 'window' => '', 'fromdate' => '', 'todate' => '', 'minamount' => '', 'maxamount' => ''),
            expense_normalize_filters(array())
        );
    }

    public function testNormalizeFiltersCleansTheInput(): void
    {
        $filters = expense_normalize_filters(array(
            'q' => '  rent ', 'currency' => 'eur', 'categoryid' => '7abc', 'window' => 'today',
            'fromdate' => ' 2026-01-01 ', 'todate' => '2026-12-31', 'minamount' => ' 5 ', 'maxamount' => '20',
        ));

        $this->assertSame('rent', $filters['q']);
        $this->assertSame('EUR', $filters['currency']);
        $this->assertSame(7, $filters['categoryid']);
        $this->assertSame('today', $filters['window']);
        $this->assertSame('2026-01-01', $filters['fromdate']);
        $this->assertSame('5', $filters['minamount']);
    }

    public function testNormalizeFiltersRejectsUnknownWindowAndFallsBackToUsdForUnknownCurrency(): void
    {
        $filters = expense_normalize_filters(array('window' => 'last-century', 'currency' => 'xyz'));

        $this->assertSame('', $filters['window']);
        $this->assertSame('USD', $filters['currency']);
    }

    // expense_filter_query

    public function testFilterQueryWithoutFiltersOnlyScopesToTheUser(): void
    {
        $query = expense_filter_query(9, expense_normalize_filters(array()));

        $this->assertSame('i', $query['types']);
        $this->assertSame(array(9), $query['params']);
        $this->assertStringContainsString('WHERE e.UserId=?', $query['sql']);
        $this->assertStringEndsWith('ORDER BY e.CreatedAt DESC, e.ID DESC', $query['sql']);
    }

    public function testFilterQueryAddsOneConditionPerFilterInOrder(): void
    {
        $filters = expense_normalize_filters(array(
            'q' => 'tea', 'currency' => 'EUR', 'categoryid' => '3',
            'fromdate' => '2026-01-01', 'todate' => '2026-01-31', 'minamount' => '2', 'maxamount' => '9.5',
        ));

        $query = expense_filter_query(1, $filters);

        $this->assertSame('isssissdd', $query['types']);
        $this->assertSame(array(1, '%tea%', '%tea%', 'EUR', 3, '2026-01-01', '2026-01-31', 2.0, 9.5), $query['params']);
        $this->assertStringContainsString('e.ExpenseItem LIKE ? OR e.Notes LIKE ?', $query['sql']);
        $this->assertStringContainsString('e.ExpenseCost<=?', $query['sql']);
    }

    public function testFilterQueryUsesDateWindowsWithoutParameters(): void
    {
        $today = expense_filter_query(1, expense_normalize_filters(array('window' => 'today')));
        $yesterday = expense_filter_query(1, expense_normalize_filters(array('window' => 'yesterday')));
        $last24h = expense_filter_query(1, expense_normalize_filters(array('window' => 'last24h')));

        $this->assertStringContainsString('e.ExpenseDate=CURDATE()', $today['sql']);
        $this->assertStringContainsString('INTERVAL 1 DAY', $yesterday['sql']);
        $this->assertStringContainsString('INTERVAL 24 HOUR', $last24h['sql']);
        $this->assertSame('i', $today['types']);
    }

    public function testFilterQueryIgnoresInvalidDatesAndAmounts(): void
    {
        $filters = expense_normalize_filters(array('fromdate' => 'soon', 'todate' => 'later', 'minamount' => 'a lot', 'maxamount' => 'x'));

        $query = expense_filter_query(1, $filters);

        $this->assertSame('i', $query['types']);
        $this->assertSame(array(1), $query['params']);
    }

    // expense_csv_lines

    public function testCsvLinesStartWithTheHeader(): void
    {
        $this->assertSame(
            array(array('Date', 'Recorded At', 'Item', 'Category', 'Amount', 'Currency', 'Notes', 'Receipt')),
            expense_csv_lines(array())
        );
    }

    public function testCsvLinesFormatEachExpense(): void
    {
        $rows = array(
            array('ExpenseDate' => '2026-05-16', 'CreatedAt' => '2026-05-16 10:00:00', 'ExpenseItem' => 'Rent',
                'CategoryName' => 'Bills', 'ExpenseCost' => '1234.5', 'Currency' => 'EUR',
                'Notes' => "  first line\n second   line ", 'ReceiptPath' => 'uploads/receipts/a.pdf'),
            array('ExpenseDate' => '2026-05-17', 'CreatedAt' => '2026-05-17 09:00:00', 'ExpenseItem' => 'Gift',
                'CategoryName' => null, 'ExpenseCost' => '10', 'Currency' => 'USD', 'Notes' => null, 'ReceiptPath' => ''),
        );

        $lines = expense_csv_lines($rows);

        $this->assertCount(3, $lines);
        $this->assertSame(
            array('2026-05-16', '2026-05-16 10:00:00', 'Rent', 'Bills', '1234.50', 'EUR', 'first line second line', 'uploads/receipts/a.pdf'),
            $lines[1]
        );
        $this->assertSame('Uncategorized', $lines[2][3]);
        $this->assertSame('10.00', $lines[2][4]);
        $this->assertSame('', $lines[2][6]);
    }
}
