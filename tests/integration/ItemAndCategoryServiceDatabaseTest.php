<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

/**
 * Integration tests for items, categories and monthly budgets.
 */
final class ItemAndCategoryServiceDatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/item-service.php';
        require_once __DIR__ . '/../../src/category-service.php';
    }

    private function insertCategory(int $userId, string $name): int
    {
        mysqli_query(self::$con, sprintf("INSERT INTO tblcategories (UserId, CategoryName) VALUES (%d, '%s')", $userId, mysqli_real_escape_string(self::$con, $name)));

        return (int)mysqli_insert_id(self::$con);
    }

    private function insertExpense(int $userId, int $categoryId, float $cost, string $date, string $currency = 'USD'): void
    {
        mysqli_query(self::$con, sprintf("INSERT INTO tblexpense (UserId, ExpenseDate, ExpenseItem, ExpenseCost, Currency, CategoryId) VALUES (%d, '%s', 'x', %F, '%s', %d)", $userId, $date, $cost, $currency, $categoryId));
    }

    // items

    public function testAddItemStoresTrimmedNamesPerUser(): void
    {
        $this->assertSame(array('ok' => true, 'message' => 'Item added successfully'), item_add(self::$con, 1, '  Groceries '));
        $this->assertTrue(item_add(self::$con, 2, 'Groceries')['ok']);

        $this->assertSame('Groceries', item_list(self::$con, 1)[0]['ItemName']);
        $this->assertCount(1, item_list(self::$con, 1));
        $this->assertCount(1, item_list(self::$con, 2));
    }

    public function testAddItemRejectsEmptyAndDuplicateNames(): void
    {
        item_add(self::$con, 1, 'Rent');

        $this->assertSame('Item name is required.', item_add(self::$con, 1, '   ')['message']);
        $this->assertSame('This item already exists.', item_add(self::$con, 1, 'Rent')['message']);
        $this->assertCount(1, item_list(self::$con, 1));
    }

    public function testAddItemStoresQuotesAsPlainText(): void
    {
        $this->assertTrue(item_add(self::$con, 1, "Bob's \"special\"; DROP TABLE tblitems;--")['ok']);

        $this->assertSame("Bob's \"special\"; DROP TABLE tblitems;--", item_list(self::$con, 1)[0]['ItemName']);
    }

    public function testItemListIsSortedByName(): void
    {
        item_add(self::$con, 1, 'Zoo');
        item_add(self::$con, 1, 'Art');

        $this->assertSame(array('Art', 'Zoo'), array_column(item_list(self::$con, 1), 'ItemName'));
    }

    public function testFindItemIsScopedToTheOwner(): void
    {
        item_add(self::$con, 1, 'Mine');
        $id = (int)item_list(self::$con, 1)[0]['ID'];

        $this->assertSame('Mine', item_find(self::$con, 1, $id)['ItemName']);
        $this->assertNull(item_find(self::$con, 2, $id));
    }

    public function testRenameItemChangesTheNameAndChecksForDuplicates(): void
    {
        item_add(self::$con, 1, 'Old');
        item_add(self::$con, 1, 'Taken');
        $id = (int)item_list(self::$con, 1)[0]['ID'];

        $this->assertSame('Item name is required.', item_rename(self::$con, 1, $id, '')['message']);
        $this->assertSame('This item already exists.', item_rename(self::$con, 1, $id, 'Taken')['message']);
        $this->assertSame(array('ok' => true, 'message' => 'Item updated successfully'), item_rename(self::$con, 1, $id, 'Renamed'));
        $this->assertSame('Renamed', item_find(self::$con, 1, $id)['ItemName']);
        $this->assertTrue(item_rename(self::$con, 1, $id, 'Renamed')['ok']);
    }

    public function testRenameItemDoesNotChangeAnotherUsersItem(): void
    {
        item_add(self::$con, 1, 'Mine');
        $id = (int)item_list(self::$con, 1)[0]['ID'];

        item_rename(self::$con, 2, $id, 'Hacked');

        $this->assertSame('Mine', item_find(self::$con, 1, $id)['ItemName']);
    }

    public function testDeleteItemOnlyDeletesTheOwnersItem(): void
    {
        item_add(self::$con, 1, 'Mine');
        $id = (int)item_list(self::$con, 1)[0]['ID'];

        $this->assertFalse(item_delete(self::$con, 2, $id));
        $this->assertFalse(item_delete(self::$con, 1, 999));
        $this->assertNotNull(item_find(self::$con, 1, $id));
        $this->assertTrue(item_delete(self::$con, 1, $id));
        $this->assertNull(item_find(self::$con, 1, $id));
    }

    // categories

    public function testAddCategoryValidatesAndStores(): void
    {
        $this->assertSame('Category name is required.', category_add(self::$con, 1, ' ')['message']);
        $this->assertSame(array('ok' => true, 'message' => 'Category added successfully.'), category_add(self::$con, 1, ' Food '));
        $this->assertSame('This category already exists.', category_add(self::$con, 1, 'Food')['message']);
        $this->assertTrue(category_add(self::$con, 2, 'Food')['ok']);

        $this->assertSame(array('Food'), array_column(expense_get_categories(self::$con, 1), 'CategoryName'));
    }

    public function testRenameCategoryValidatesAndKeepsNamesUnique(): void
    {
        $food = $this->insertCategory(1, 'Food');
        $this->insertCategory(1, 'Bills');

        $this->assertSame('A valid category is required.', category_rename(self::$con, 1, 0, 'X')['message']);
        $this->assertSame('A valid category is required.', category_rename(self::$con, 1, $food, ' ')['message']);
        $this->assertSame('This category already exists.', category_rename(self::$con, 1, $food, 'Bills')['message']);
        $this->assertSame(array('ok' => true, 'message' => 'Category updated successfully.'), category_rename(self::$con, 1, $food, 'Groceries'));
        $this->assertSame('Groceries', expense_find_category_by_id(self::$con, 1, $food)['CategoryName']);
    }

    public function testRenameCategoryDoesNotChangeAnotherUsersCategory(): void
    {
        $food = $this->insertCategory(1, 'Food');

        category_rename(self::$con, 2, $food, 'Hacked');

        $this->assertSame('Food', expense_find_category_by_id(self::$con, 1, $food)['CategoryName']);
    }

    public function testRemoveCategoryDeletesAnUnusedCategory(): void
    {
        $id = $this->insertCategory(1, 'Unused');

        $this->assertSame(array('ok' => true, 'message' => 'Category deleted successfully.'), category_remove(self::$con, 1, $id));
        $this->assertNull(expense_find_category_by_id(self::$con, 1, $id));
    }

    public function testRemoveCategoryRefusesACategoryUsedByAnExpenseOrABudget(): void
    {
        $withExpense = $this->insertCategory(1, 'With expense');
        $withBudget = $this->insertCategory(1, 'With budget');
        $this->insertExpense(1, $withExpense, 5, '2026-05-01');
        budget_save(self::$con, 1, $withBudget, '2026-05', 'USD', '100');
        $message = 'This category is already used by expenses or budgets, so it cannot be deleted.';

        $this->assertSame($message, category_remove(self::$con, 1, $withExpense)['message']);
        $this->assertSame($message, category_remove(self::$con, 1, $withBudget)['message']);
        $this->assertNotNull(expense_find_category_by_id(self::$con, 1, $withExpense));
    }

    public function testRemoveCategoryFailsForUnknownAndForeignCategories(): void
    {
        $theirs = $this->insertCategory(2, 'Theirs');

        $this->assertSame('Unable to delete this category.', category_remove(self::$con, 1, 999)['message']);
        $this->assertFalse(category_remove(self::$con, 1, $theirs)['ok']);
        $this->assertNotNull(expense_find_category_by_id(self::$con, 2, $theirs));
    }

    // budgets

    public function testBudgetSaveValidatesItsInput(): void
    {
        $food = $this->insertCategory(1, 'Food');
        $theirs = $this->insertCategory(2, 'Theirs');

        $this->assertSame('Please choose a category for the budget.', budget_save(self::$con, 1, 0, '2026-05', 'USD', '10')['message']);
        $this->assertSame('Please enter a valid budget amount greater than zero.', budget_save(self::$con, 1, $food, '2026-05', 'USD', '')['message']);
        $this->assertSame('Please enter a valid budget amount greater than zero.', budget_save(self::$con, 1, $food, '2026-05', 'USD', 'abc')['message']);
        $this->assertSame('Please enter a valid budget amount greater than zero.', budget_save(self::$con, 1, $food, '2026-05', 'USD', '0')['message']);
        $this->assertSame('The selected category is invalid.', budget_save(self::$con, 1, $theirs, '2026-05', 'USD', '10')['message']);
        $this->assertSame(array(), budget_list(self::$con, 1, '2026-05', 'USD'));
    }

    public function testBudgetSaveCreatesAndThenUpdatesTheSameBudget(): void
    {
        $food = $this->insertCategory(1, 'Food');

        $this->assertSame(array('ok' => true, 'message' => 'Budget saved successfully.'), budget_save(self::$con, 1, $food, '2026-05', 'USD', '100'));
        $this->assertTrue(budget_save(self::$con, 1, $food, '2026-05', 'USD', '250.5')['ok']);
        budget_save(self::$con, 1, $food, '2026-05', 'EUR', '30');
        budget_save(self::$con, 1, $food, '2026-06', 'USD', '40');

        $usd = budget_list(self::$con, 1, '2026-05', 'USD');
        $this->assertCount(1, $usd);
        $this->assertSame('250.50', $usd[0]['BudgetAmount']);
        $this->assertSame('Food', $usd[0]['CategoryName']);
        $this->assertCount(1, budget_list(self::$con, 1, '2026-05', 'EUR'));
        $this->assertCount(1, budget_list(self::$con, 1, '2026-06', 'USD'));
        $this->assertCount(0, budget_list(self::$con, 2, '2026-05', 'USD'));
    }

    public function testBudgetRemoveDeletesOnlyTheOwnersBudget(): void
    {
        $food = $this->insertCategory(1, 'Food');
        budget_save(self::$con, 1, $food, '2026-05', 'USD', '100');
        $id = (int)budget_list(self::$con, 1, '2026-05', 'USD')[0]['ID'];

        $this->assertSame('Unable to delete this budget.', budget_remove(self::$con, 2, $id)['message']);
        $this->assertSame('Unable to delete this budget.', budget_remove(self::$con, 1, 999)['message']);
        $this->assertSame(array('ok' => true, 'message' => 'Budget deleted successfully.'), budget_remove(self::$con, 1, $id));
        $this->assertCount(0, budget_list(self::$con, 1, '2026-05', 'USD'));
    }

    public function testCategoryOverviewShowsMonthlySpendingAndBudget(): void
    {
        $food = $this->insertCategory(1, 'Food');
        $bills = $this->insertCategory(1, 'Bills');
        $this->insertExpense(1, $food, 10, '2026-05-02');
        $this->insertExpense(1, $food, 15, '2026-05-30');
        $this->insertExpense(1, $food, 99, '2026-06-01');
        $this->insertExpense(1, $food, 77, '2026-05-10', 'EUR');
        $this->insertExpense(2, $food, 1000, '2026-05-10');
        budget_save(self::$con, 1, $food, '2026-05', 'USD', '60');

        $rows = category_overview(self::$con, 1, '2026-05', 'USD');

        $this->assertSame(array('Bills', 'Food'), array_column($rows, 'CategoryName'));
        $this->assertEquals(0, $rows[0]['month_spent']);
        $this->assertNull($rows[0]['month_budget']);
        $this->assertEquals(25, $rows[1]['month_spent']);
        $this->assertEquals(60, $rows[1]['month_budget']);
        $this->assertSame(array(), category_overview(self::$con, 3, '2026-05', 'USD'));
        $this->assertNotNull($bills);
    }
}
