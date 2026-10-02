<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/expense-helpers.php';

/**
 * Integration tests for the database helper functions.
 *
 * They run against a separate throw-away database (default: detsdb_test) that
 * is created from database/database.sql. The real application database is
 * never touched. When no MySQL server is reachable the tests are skipped.
 *
 * Environment variables: TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS, TEST_DB_NAME.
 */
final class DatabaseHelpersTest extends TestCase
{
    private static ?mysqli $con = null;

    public static function setUpBeforeClass(): void
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $user = getenv('TEST_DB_USER') ?: 'root';
        $pass = getenv('TEST_DB_PASS') ?: '';
        $name = getenv('TEST_DB_NAME') ?: 'detsdb_test';

        if ($name === 'detsdb') {
            self::fail('Refusing to run the tests against the real database.');
        }

        $con = @mysqli_connect($host, $user, $pass);
        if (!$con) {
            return;
        }

        mysqli_query($con, "CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARACTER SET utf8mb4");
        mysqli_select_db($con, $name);

        $statements = explode(';', (string)file_get_contents(__DIR__ . '/../../database/database.sql'));
        foreach ($statements as $statement) {
            if (stripos(ltrim($statement), 'CREATE TABLE') === 0) {
                mysqli_query($con, $statement);
            }
        }

        self::$con = $con;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$con) {
            mysqli_close(self::$con);
            self::$con = null;
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }

    protected function setUp(): void
    {
        if (!self::$con) {
            $this->markTestSkipped('No MySQL test database is available.');
        }

        foreach (array('tbluser', 'tblexpense', 'tblcategories', 'tblrecurring', 'tblbudgets', 'tblitems') as $table) {
            mysqli_query(self::$con, "TRUNCATE TABLE $table");
        }
    }

    private function query(string $sql): array
    {
        $result = mysqli_query(self::$con, $sql);
        $this->assertNotFalse($result, mysqli_error(self::$con));

        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    // expense_prepare_and_execute / fetch helpers

    public function testPrepareAndExecuteReturnsFalseForInvalidSql(): void
    {
        $this->assertFalse(expense_prepare_and_execute(self::$con, 'SELECT * FROM table_that_does_not_exist'));
    }

    public function testPrepareAndExecuteReturnsFalseWhenExecutionFails(): void
    {
        mysqli_query(self::$con, "INSERT INTO tbluser (FullName, MobileNumber, Email, Password) VALUES ('A', '1', 'dup@example.com', 'x')");

        $stmt = expense_prepare_and_execute(
            self::$con,
            "INSERT INTO tbluser (FullName, MobileNumber, Email, Password) VALUES (?, ?, ?, ?)",
            'ssss',
            array('B', '2', 'dup@example.com', 'y')
        );

        $this->assertFalse($stmt);
    }

    public function testPrepareAndExecuteBindsParametersAndFetchAllReturnsRows(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (UserId, CategoryName) VALUES (1, 'A'), (1, 'B'), (2, 'C')");

        $stmt = expense_prepare_and_execute(self::$con, 'SELECT CategoryName FROM tblcategories WHERE UserId=? ORDER BY ID', 'i', array(1));

        $this->assertSame(
            array(array('CategoryName' => 'A'), array('CategoryName' => 'B')),
            expense_fetch_all_assoc($stmt)
        );
    }

    public function testFetchOneReturnsFirstRowOrNull(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (UserId, CategoryName) VALUES (1, 'A'), (1, 'B')");

        $row = expense_fetch_one_assoc(expense_prepare_and_execute(self::$con, 'SELECT CategoryName FROM tblcategories ORDER BY ID'));
        $this->assertSame(array('CategoryName' => 'A'), $row);

        $none = expense_fetch_one_assoc(expense_prepare_and_execute(self::$con, 'SELECT ID FROM tblcategories WHERE UserId=?', 'i', array(99)));
        $this->assertNull($none);
    }

    public function testFetchHelpersHandleFailedStatements(): void
    {
        $this->assertSame(array(), expense_fetch_all_assoc(false));
        $this->assertNull(expense_fetch_one_assoc(false));
    }

    public function testCloseStatementAcceptsFalseAndRealStatements(): void
    {
        expense_close_statement(false);
        expense_close_statement(expense_prepare_and_execute(self::$con, 'SELECT 1'));

        $this->addToAssertionCount(1);
    }

    // categories

    public function testEnsureUserCategoriesCreatesDefaultsOnlyOnce(): void
    {
        expense_ensure_user_categories(self::$con, 7);
        expense_ensure_user_categories(self::$con, 7);

        $rows = $this->query('SELECT CategoryName FROM tblcategories WHERE UserId=7 ORDER BY CategoryName');

        $this->assertSame(
            array('Bills', 'Entertainment', 'Food', 'Health', 'Shopping', 'Transport'),
            array_column($rows, 'CategoryName')
        );
    }

    public function testGetCategoriesReturnsOnlyTheUsersCategoriesSortedByName(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (UserId, CategoryName) VALUES (1, 'Zoo'), (1, 'Art'), (2, 'Other')");

        $categories = expense_get_categories(self::$con, 1);

        $this->assertSame(array('Art', 'Zoo'), array_column($categories, 'CategoryName'));
    }

    public function testFindCategoryByIdIsScopedToTheOwner(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (UserId, CategoryName) VALUES (1, 'Mine')");
        $id = (int)mysqli_insert_id(self::$con);

        $this->assertSame('Mine', expense_find_category_by_id(self::$con, 1, $id)['CategoryName']);
        $this->assertNull(expense_find_category_by_id(self::$con, 2, $id));
    }

    // user settings

    public function testUserSettingsFallBackToDefaultsForUnknownUser(): void
    {
        $this->assertSame(
            array('DefaultCurrency' => 'USD', 'DefaultCategoryId' => null),
            expense_get_user_settings(self::$con, 999)
        );
    }

    public function testUserSettingsReturnStoredCurrencyAndCategory(): void
    {
        mysqli_query(self::$con, "INSERT INTO tbluser (ID, FullName, MobileNumber, Email, Password, DefaultCurrency, DefaultCategoryId) VALUES (5, 'A', '1', 'a@example.com', 'x', 'EUR', 3)");

        $this->assertSame(
            array('DefaultCurrency' => 'EUR', 'DefaultCategoryId' => 3),
            expense_get_user_settings(self::$con, 5)
        );
    }

    public function testUserSettingsReplaceAnInvalidStoredCurrency(): void
    {
        mysqli_query(self::$con, "INSERT INTO tbluser (ID, FullName, MobileNumber, Email, Password, DefaultCurrency) VALUES (6, 'A', '1', 'b@example.com', 'x', 'XXX')");

        $this->assertSame('USD', expense_get_user_settings(self::$con, 6)['DefaultCurrency']);
        $this->assertNull(expense_get_user_settings(self::$con, 6)['DefaultCategoryId']);
    }

    // schema

    public function testEnsureSchemaAddsMissingColumnsAndTables(): void
    {
        foreach (array('Currency', 'CategoryId', 'Notes', 'ReceiptPath', 'CreatedAt') as $column) {
            mysqli_query(self::$con, "ALTER TABLE tblexpense DROP COLUMN $column");
        }
        mysqli_query(self::$con, 'ALTER TABLE tbluser DROP COLUMN DefaultCurrency');
        mysqli_query(self::$con, 'ALTER TABLE tbluser DROP COLUMN DefaultCategoryId');
        mysqli_query(self::$con, 'DROP TABLE tblbudgets');

        expense_ensure_schema(self::$con);

        $expenseColumns = array_column($this->query('SHOW COLUMNS FROM tblexpense'), 'Field');
        foreach (array('Currency', 'CategoryId', 'Notes', 'ReceiptPath', 'CreatedAt') as $column) {
            $this->assertContains($column, $expenseColumns);
        }

        $userColumns = array_column($this->query('SHOW COLUMNS FROM tbluser'), 'Field');
        $this->assertContains('DefaultCurrency', $userColumns);
        $this->assertContains('DefaultCategoryId', $userColumns);
        $this->assertNotEmpty($this->query("SHOW TABLES LIKE 'tblbudgets'"));
    }

    public function testEnsureSchemaKeepsExistingRowsWhenAddingCreatedAt(): void
    {
        mysqli_query(self::$con, 'ALTER TABLE tblexpense DROP COLUMN CreatedAt');
        mysqli_query(self::$con, "INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost) VALUES (1, '2026-01-05', 'Old row', 10)");

        expense_ensure_schema(self::$con);

        $rows = $this->query('SELECT CreatedAt FROM tblexpense');
        $this->assertSame('2026-01-05 00:00:00', $rows[0]['CreatedAt']);
    }

    public function testEnsureSchemaIsSafeToRunTwice(): void
    {
        expense_ensure_schema(self::$con);
        expense_ensure_schema(self::$con);

        $this->assertCount(1, $this->query("SHOW TABLES LIKE 'tblrecurring'"));
    }

    // recurring expenses

    private function addRecurring(int $userId, string $frequency, string $nextRun, int $active = 1): void
    {
        mysqli_query(
            self::$con,
            "INSERT INTO tblrecurring (UserId, ExpenseItem, ExpenseCost, Currency, CategoryId, Notes, Frequency, StartDate, NextRunDate, IsActive)
             VALUES ($userId, 'Rent', 100, 'EUR', 1, 'note', '$frequency', '$nextRun', '$nextRun', $active)"
        );
    }

    public function testProcessRecurringCreatesAnExpenseAndSchedulesTheNextMonthlyRun(): void
    {
        $today = date('Y-m-d');
        $this->addRecurring(1, 'monthly', $today);

        expense_process_recurring(self::$con, 1);

        $expenses = $this->query('SELECT * FROM tblexpense');
        $this->assertCount(1, $expenses);
        $this->assertSame('Rent', $expenses[0]['ExpenseItem']);
        $this->assertSame('100.00', $expenses[0]['ExpenseCost']);
        $this->assertSame('EUR', $expenses[0]['Currency']);
        $this->assertSame($today, $expenses[0]['ExpenseDate']);

        $recurring = $this->query('SELECT NextRunDate, LastRunDate FROM tblrecurring')[0];
        $this->assertSame(date('Y-m-d', strtotime($today . ' +1 month')), $recurring['NextRunDate']);
        $this->assertSame($today, $recurring['LastRunDate']);
    }

    public function testProcessRecurringCatchesUpOnMissedWeeklyRuns(): void
    {
        $today = date('Y-m-d');
        $this->addRecurring(1, 'weekly', date('Y-m-d', strtotime($today . ' -14 days')));

        expense_process_recurring(self::$con, 1);

        $this->assertCount(3, $this->query('SELECT ID FROM tblexpense'));
        $this->assertSame(
            date('Y-m-d', strtotime($today . ' +7 days')),
            $this->query('SELECT NextRunDate FROM tblrecurring')[0]['NextRunDate']
        );
    }

    public function testProcessRecurringIgnoresFutureInactiveAndOtherUsersEntries(): void
    {
        $today = date('Y-m-d');
        $this->addRecurring(1, 'monthly', date('Y-m-d', strtotime($today . ' +1 day')));
        $this->addRecurring(1, 'monthly', $today, 0);
        $this->addRecurring(2, 'monthly', $today);

        expense_process_recurring(self::$con, 1);

        $this->assertCount(0, $this->query('SELECT ID FROM tblexpense'));
    }
}
