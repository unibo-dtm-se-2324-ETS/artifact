<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

/**
 * Integration tests for creating, reading, updating, deleting and searching expenses.
 */
final class ExpenseServiceDatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/expense-service.php';
    }

    private function form(array $changes = array()): array
    {
        return array_merge(array(
            'dateexpense' => '2026-05-16', 'item' => 'Coffee', 'costitem' => '3.50',
            'currency' => 'EUR', 'notes' => 'with milk',
        ), $changes);
    }

    private function insertExpense(int $userId, string $item, float $cost, string $date, string $createdAt, string $currency = 'USD', int $categoryId = 0, string $notes = ''): int
    {
        mysqli_query(
            self::$con,
            sprintf(
                "INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, ReceiptPath, CreatedAt) VALUES (%d, '%s', '%s', %F, '%s', %d, '%s', '', '%s')",
                $userId, $date, mysqli_real_escape_string(self::$con, $item), $cost, $currency, $categoryId, mysqli_real_escape_string(self::$con, $notes), $createdAt
            )
        );

        return (int)mysqli_insert_id(self::$con);
    }

    // expense_create / expense_find

    public function testCreateStoresTheExpenseAndFindReturnsIt(): void
    {
        $this->assertTrue(expense_create(self::$con, 1, $this->form(), 4, 'uploads/receipts/r.pdf'));

        $id = (int)$this->query('SELECT ID FROM tblexpense')[0]['ID'];
        $expense = expense_find(self::$con, 1, $id);

        $this->assertSame('Coffee', $expense['ExpenseItem']);
        $this->assertSame('3.50', $expense['ExpenseCost']);
        $this->assertSame('EUR', $expense['Currency']);
        $this->assertSame(4, $expense['CategoryId']);
        $this->assertSame('with milk', $expense['Notes']);
        $this->assertSame('uploads/receipts/r.pdf', $expense['ReceiptPath']);
        $this->assertSame('2026-05-16', $expense['ExpenseDate']);
    }

    public function testFindIsScopedToTheOwnerAndReturnsNullForUnknownIds(): void
    {
        $id = $this->insertExpense(1, 'Mine', 5, '2026-05-01', '2026-05-01 08:00:00');

        $this->assertNull(expense_find(self::$con, 2, $id));
        $this->assertNull(expense_find(self::$con, 1, $id + 100));
    }

    // expense_update

    public function testUpdateChangesTheOwnersExpense(): void
    {
        $id = $this->insertExpense(1, 'Old', 5, '2026-05-01', '2026-05-01 08:00:00');

        $this->assertTrue(expense_update(self::$con, 1, $id, $this->form(array('item' => 'New', 'costitem' => '12')), 6, ''));

        $expense = expense_find(self::$con, 1, $id);
        $this->assertSame('New', $expense['ExpenseItem']);
        $this->assertSame('12.00', $expense['ExpenseCost']);
        $this->assertSame(6, $expense['CategoryId']);
    }

    public function testUpdateDoesNotChangeAnotherUsersExpense(): void
    {
        $id = $this->insertExpense(1, 'Mine', 5, '2026-05-01', '2026-05-01 08:00:00');

        expense_update(self::$con, 2, $id, $this->form(array('item' => 'Hacked')), 1, '');

        $this->assertSame('Mine', expense_find(self::$con, 1, $id)['ExpenseItem']);
    }

    // expense_delete

    public function testDeleteRemovesTheExpenseAndItsReceiptFile(): void
    {
        $receiptDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
        if (!is_dir($receiptDir)) {
            mkdir($receiptDir, 0777, true);
        }
        $file = $receiptDir . DIRECTORY_SEPARATOR . 'unit-test-service-receipt.txt';
        file_put_contents($file, 'temporary');

        expense_create(self::$con, 1, $this->form(), 1, 'uploads/receipts/unit-test-service-receipt.txt');
        $id = (int)$this->query('SELECT ID FROM tblexpense')[0]['ID'];

        $this->assertTrue(expense_delete(self::$con, 1, $id));

        $this->assertNull(expense_find(self::$con, 1, $id));
        $this->assertFileDoesNotExist($file);
    }

    public function testDeleteRefusesAnotherUsersExpenseAndKeepsIt(): void
    {
        $id = $this->insertExpense(1, 'Mine', 5, '2026-05-01', '2026-05-01 08:00:00');

        $this->assertFalse(expense_delete(self::$con, 2, $id));
        $this->assertNotNull(expense_find(self::$con, 1, $id));
    }

    public function testDeleteReportsFailureAndKeepsTheExpenseWhenTheDatabaseRefusesTheDelete(): void
    {
        $id = $this->insertExpense(1, 'Mine', 5, '2026-05-01', '2026-05-01 08:00:00');
        mysqli_query(self::$con, "CREATE TRIGGER refuse_expense_delete BEFORE DELETE ON tblexpense FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'refused'");

        try {
            $this->assertFalse(expense_delete(self::$con, 1, $id));
        } finally {
            mysqli_query(self::$con, 'DROP TRIGGER IF EXISTS refuse_expense_delete');
        }

        $this->assertNotNull(expense_find(self::$con, 1, $id));
    }

    public function testDeleteReturnsFalseForAnUnknownExpense(): void
    {
        $this->assertFalse(expense_delete(self::$con, 1, 999));
    }

    // expense_search

    public function testSearchReturnsOnlyTheUsersExpensesNewestFirst(): void
    {
        $this->insertExpense(1, 'First', 1, '2026-05-01', '2026-05-01 08:00:00');
        $this->insertExpense(1, 'Second', 2, '2026-05-02', '2026-05-02 08:00:00');
        $this->insertExpense(2, 'Other user', 3, '2026-05-03', '2026-05-03 08:00:00');

        $rows = expense_search(self::$con, 1, expense_normalize_filters(array()));

        $this->assertSame(array('Second', 'First'), array_column($rows, 'ExpenseItem'));
    }

    public function testSearchFiltersByTextInItemAndNotes(): void
    {
        $this->insertExpense(1, 'Pizza', 10, '2026-05-01', '2026-05-01 08:00:00', 'USD', 0, 'friday');
        $this->insertExpense(1, 'Bus', 2, '2026-05-02', '2026-05-02 08:00:00', 'USD', 0, 'pizza place');
        $this->insertExpense(1, 'Rent', 500, '2026-05-03', '2026-05-03 08:00:00');

        $rows = expense_search(self::$con, 1, expense_normalize_filters(array('q' => 'pizza')));

        $this->assertEqualsCanonicalizing(array('Pizza', 'Bus'), array_column($rows, 'ExpenseItem'));
    }

    public function testSearchFiltersByCurrencyCategoryAmountAndDate(): void
    {
        $this->insertExpense(1, 'A', 5, '2026-05-01', '2026-05-01 08:00:00', 'USD', 1);
        $this->insertExpense(1, 'B', 50, '2026-05-10', '2026-05-10 08:00:00', 'EUR', 2);
        $this->insertExpense(1, 'C', 500, '2026-05-20', '2026-05-20 08:00:00', 'EUR', 2);

        $this->assertSame(array('C', 'B'), array_column(expense_search(self::$con, 1, expense_normalize_filters(array('currency' => 'EUR'))), 'ExpenseItem'));
        $this->assertSame(array('A'), array_column(expense_search(self::$con, 1, expense_normalize_filters(array('categoryid' => '1'))), 'ExpenseItem'));
        $this->assertSame(array('B'), array_column(expense_search(self::$con, 1, expense_normalize_filters(array('minamount' => '10', 'maxamount' => '100'))), 'ExpenseItem'));
        $this->assertSame(array('B'), array_column(expense_search(self::$con, 1, expense_normalize_filters(array('fromdate' => '2026-05-05', 'todate' => '2026-05-15'))), 'ExpenseItem'));
    }

    public function testSearchFiltersByTodayAndYesterday(): void
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->insertExpense(1, 'Now', 1, $today, $today . ' 08:00:00');
        $this->insertExpense(1, 'Before', 1, $yesterday, $yesterday . ' 08:00:00');
        $this->insertExpense(1, 'Old', 1, '2020-01-01', '2020-01-01 08:00:00');

        $this->assertSame(array('Now'), array_column(expense_search(self::$con, 1, expense_normalize_filters(array('window' => 'today'))), 'ExpenseItem'));
        $this->assertSame(array('Before'), array_column(expense_search(self::$con, 1, expense_normalize_filters(array('window' => 'yesterday'))), 'ExpenseItem'));
    }

    public function testSearchIncludesTheCategoryName(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (ID, UserId, CategoryName) VALUES (3, 1, 'Food')");
        $this->insertExpense(1, 'Lunch', 8, '2026-05-01', '2026-05-01 08:00:00', 'USD', 3);
        $this->insertExpense(1, 'Mystery', 8, '2026-05-02', '2026-05-02 08:00:00');

        $rows = expense_search(self::$con, 1, expense_normalize_filters(array()));

        $this->assertSame('Mystery', $rows[0]['ExpenseItem']);
        $this->assertNull($rows[0]['CategoryName']);
        $this->assertSame('Food', $rows[1]['CategoryName']);
    }
}
