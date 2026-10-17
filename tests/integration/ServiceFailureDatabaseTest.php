<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

/**
 * What the services do when the database fails: they must report a failure and never crash or
 * pretend that something worked. A table is made unavailable inside the test database to cause it.
 */
final class ServiceFailureDatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/account-service.php';
        require_once __DIR__ . '/../../src/item-service.php';
        require_once __DIR__ . '/../../src/category-service.php';
        require_once __DIR__ . '/../../src/recurring-service.php';
        require_once __DIR__ . '/../../src/expense-service.php';
        require_once __DIR__ . '/../../src/dashboard-service.php';
        require_once __DIR__ . '/../../src/report-service.php';
    }

    private function form(): array
    {
        return array('dateexpense' => '2026-05-16', 'item' => 'Coffee', 'costitem' => '3.50', 'currency' => 'EUR', 'notes' => '');
    }

    // accounts

    public function testAccountFunctionsFailGracefullyWithoutTheUserTable(): void
    {
        $this->breakTable('tbluser');

        $this->assertSame(0, auth_login(self::$con, 'a@example.com', 'x'));
        $this->assertSame(array('ok' => false, 'message' => 'Something Went Wrong. Please try again'), auth_register(self::$con, 'A', '1', 'a@example.com', 'x'));
        $this->assertFalse(auth_store_password(self::$con, 1, 'x'));
        $this->assertSame(0, auth_find_user_by_contact(self::$con, 'a@example.com', '1'));
        $this->assertFalse(auth_reset_password(self::$con, 'a@example.com', '1', 'x'));
        $this->assertFalse(auth_change_password(self::$con, 1, 'a', 'b'));
        $this->assertNull(profile_get(self::$con, 1));
        $this->assertSame('', profile_full_name(self::$con, 1));
        $this->assertSame(
            'Something went wrong. Please try again.',
            profile_update(self::$con, 1, profile_form_from_input(array('fullname' => 'Ada')))
        );
    }

    // items

    public function testItemFunctionsFailGracefullyWithoutTheItemTable(): void
    {
        $this->breakTable('tblitems');

        $this->assertSame('Something went wrong. Please try again.', item_add(self::$con, 1, 'Rent')['message']);
        $this->assertSame('Something went wrong. Please try again.', item_rename(self::$con, 1, 1, 'Rent')['message']);
        $this->assertFalse(item_delete(self::$con, 1, 1));
        $this->assertSame(array(), item_list(self::$con, 1));
        $this->assertNull(item_find(self::$con, 1, 1));
    }

    // categories and budgets

    public function testCategoryFunctionsFailGracefullyWithoutTheCategoryTable(): void
    {
        $this->breakTable('tblcategories');

        $this->assertSame('Unable to add category right now.', category_add(self::$con, 1, 'Food')['message']);
        $this->assertSame('Unable to update this category.', category_rename(self::$con, 1, 1, 'Food')['message']);
        $this->assertSame('Unable to delete this category.', category_remove(self::$con, 1, 1)['message']);
        $this->assertSame(array(), category_overview(self::$con, 1, '2026-05', 'USD'));
    }

    public function testBudgetFunctionsFailGracefullyWithoutTheBudgetTable(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (ID, UserId, CategoryName) VALUES (1, 1, 'Food')");
        $this->breakTable('tblbudgets');

        $this->assertSame('Unable to save this budget.', budget_save(self::$con, 1, 1, '2026-05', 'USD', '10')['message']);
        $this->assertSame('Unable to delete this budget.', budget_remove(self::$con, 1, 1)['message']);
        $this->assertSame(array(), budget_list(self::$con, 1, '2026-05', 'USD'));
    }

    // recurring expenses

    public function testRecurringFunctionsFailGracefullyWithoutTheRecurringTable(): void
    {
        $this->breakTable('tblrecurring');
        $form = array('item' => 'Rent', 'cost' => '500', 'currency' => 'USD', 'categoryid' => '3', 'frequency' => 'monthly', 'startdate' => '2026-05-01', 'notes' => '');

        $this->assertSame('Unable to create recurring expense.', recurring_save(self::$con, 1, 0, $form)['message']);
        $this->assertSame('Unable to update recurring expense.', recurring_save(self::$con, 1, 5, $form)['message']);
        $this->assertSame('Unable to delete recurring expense.', recurring_delete(self::$con, 1, 1)['message']);
        $this->assertSame('Unable to update recurring expense.', recurring_toggle(self::$con, 1, 1, 1)['message']);
        $this->assertSame(array(), recurring_list(self::$con, 1));
        $this->assertNull(recurring_find(self::$con, 1, 1));
    }

    // expenses

    public function testExpenseFunctionsFailGracefullyWithoutTheExpenseTable(): void
    {
        $this->breakTable('tblexpense');

        $this->assertFalse(expense_create(self::$con, 1, $this->form(), 1, ''));
        $this->assertFalse(expense_update(self::$con, 1, 1, $this->form(), 1, ''));
        $this->assertFalse(expense_delete(self::$con, 1, 1));
        $this->assertNull(expense_find(self::$con, 1, 1));
        $this->assertSame(array(), expense_search(self::$con, 1, expense_normalize_filters(array())));
    }

    // dashboard and reports

    public function testTheDashboardIsEmptyButCompleteWithoutTheExpenseTable(): void
    {
        $this->breakTable('tblexpense');

        $data = dashboard_build(self::$con, 1, 'USD', strtotime('2026-05-15 12:00:00'));

        $this->assertSame(0, $data['activeDays']);
        $this->assertNull($data['latestExpense']);
        $this->assertSame(array(), $data['topCategorySegments']);
        $this->assertCount(31, $data['dayValues']);
    }

    public function testReportsAreEmptyWithoutTheExpenseTable(): void
    {
        $this->breakTable('tblexpense');

        $report = report_build(self::$con, 1, 'USD', 'month', '2026-01', '2026-03');

        $this->assertSame('', $report['msg']);
        $this->assertSame(0, $report['recordCount']);
        $this->assertSame(array(), $report['categoryRows']);
        $this->assertSame(array(), report_legacy_daily_totals(self::$con, 1, '2026-01-01', '2026-03-31'));
    }

    // helpers

    public function testTheSharedHelpersFailGracefullyWithoutTheirTables(): void
    {
        $this->breakTable('tblcategories');
        $this->breakTable('tbluser');

        $this->assertSame(array(), expense_get_categories(self::$con, 1));
        $this->assertNull(expense_find_category_by_id(self::$con, 1, 1));
        $this->assertSame(array('DefaultCurrency' => 'USD', 'DefaultCategoryId' => null), expense_get_user_settings(self::$con, 1));
        expense_ensure_user_categories(self::$con, 1);
        $this->addToAssertionCount(1);
    }

    public function testRecurringProcessingDoesNothingWithoutTheRecurringTable(): void
    {
        $this->breakTable('tblrecurring');

        expense_process_recurring(self::$con, 1);

        $this->assertSame(array(), $this->query('SELECT ID FROM tblexpense'));
    }
}
