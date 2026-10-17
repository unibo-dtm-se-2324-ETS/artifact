<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';

/**
 * Integration tests for creating, listing, changing and deleting recurring expense rules.
 */
final class RecurringServiceDatabaseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/recurring-service.php';
    }

    private function form(array $changes = array()): array
    {
        return array_merge(array(
            'item' => 'Rent', 'cost' => '500', 'currency' => 'EUR', 'categoryid' => '3',
            'frequency' => 'monthly', 'startdate' => '2026-05-01', 'notes' => 'flat',
        ), $changes);
    }

    private function firstRuleId(int $userId = 1): int
    {
        return (int)recurring_list(self::$con, $userId)[0]['ID'];
    }

    public function testSaveCreatesAnActiveRuleThatStartsOnTheStartDate(): void
    {
        $this->assertSame(array('ok' => true, 'message' => 'Recurring expense created.'), recurring_save(self::$con, 1, 0, $this->form()));

        $rule = recurring_find(self::$con, 1, $this->firstRuleId());
        $this->assertSame('Rent', $rule['ExpenseItem']);
        $this->assertSame('500.00', $rule['ExpenseCost']);
        $this->assertSame('EUR', $rule['Currency']);
        $this->assertSame('monthly', $rule['Frequency']);
        $this->assertSame('2026-05-01', $rule['StartDate']);
        $this->assertSame('2026-05-01', $rule['NextRunDate']);
        $this->assertSame(1, $rule['IsActive']);
    }

    public function testSaveReturnsTheValidationMessageAndStoresNothing(): void
    {
        $result = recurring_save(self::$con, 1, 0, $this->form(array('cost' => '0')));

        $this->assertSame(array('ok' => false, 'message' => 'Please fill in a valid item and amount.'), $result);
        $this->assertSame(array(), recurring_list(self::$con, 1));
    }

    public function testSaveUpdatesAnExistingRule(): void
    {
        recurring_save(self::$con, 1, 0, $this->form());
        $id = $this->firstRuleId();

        $result = recurring_save(self::$con, 1, $id, $this->form(array('item' => 'Gym', 'cost' => '30', 'frequency' => 'weekly', 'startdate' => '2026-06-01')));

        $this->assertSame(array('ok' => true, 'message' => 'Recurring expense updated.'), $result);
        $rule = recurring_find(self::$con, 1, $id);
        $this->assertSame('Gym', $rule['ExpenseItem']);
        $this->assertSame('weekly', $rule['Frequency']);
        $this->assertSame('2026-06-01', $rule['NextRunDate']);
        $this->assertCount(1, recurring_list(self::$con, 1));
    }

    public function testFindAndUpdateAreScopedToTheOwner(): void
    {
        recurring_save(self::$con, 1, 0, $this->form());
        $id = $this->firstRuleId();

        $this->assertNull(recurring_find(self::$con, 2, $id));

        recurring_save(self::$con, 2, $id, $this->form(array('item' => 'Hacked')));
        $this->assertSame('Rent', recurring_find(self::$con, 1, $id)['ExpenseItem']);
    }

    public function testListShowsActiveRulesFirstThenByNextRunDateWithTheCategoryName(): void
    {
        mysqli_query(self::$con, "INSERT INTO tblcategories (ID, UserId, CategoryName) VALUES (3, 1, 'Housing')");
        recurring_save(self::$con, 1, 0, $this->form(array('item' => 'Later', 'startdate' => '2026-09-01')));
        recurring_save(self::$con, 1, 0, $this->form(array('item' => 'Sooner', 'startdate' => '2026-07-01')));
        recurring_save(self::$con, 1, 0, $this->form(array('item' => 'Paused', 'startdate' => '2026-01-01')));
        $pausedId = (int)$this->query("SELECT ID FROM tblrecurring WHERE ExpenseItem='Paused'")[0]['ID'];
        recurring_toggle(self::$con, 1, $pausedId, 1);
        recurring_save(self::$con, 2, 0, $this->form(array('item' => 'Other user')));

        $rows = recurring_list(self::$con, 1);

        $this->assertSame(array('Sooner', 'Later', 'Paused'), array_column($rows, 'ExpenseItem'));
        $this->assertSame('Housing', $rows[0]['CategoryName']);
    }

    public function testToggleSwitchesBetweenPausedAndActive(): void
    {
        recurring_save(self::$con, 1, 0, $this->form());
        $id = $this->firstRuleId();

        $this->assertSame(array('ok' => true, 'message' => 'Recurring expense paused.'), recurring_toggle(self::$con, 1, $id, 1));
        $this->assertSame(0, recurring_find(self::$con, 1, $id)['IsActive']);

        $this->assertSame(array('ok' => true, 'message' => 'Recurring expense activated.'), recurring_toggle(self::$con, 1, $id, 0));
        $this->assertSame(1, recurring_find(self::$con, 1, $id)['IsActive']);
    }

    public function testToggleDoesNotChangeAnotherUsersRule(): void
    {
        recurring_save(self::$con, 1, 0, $this->form());
        $id = $this->firstRuleId();

        recurring_toggle(self::$con, 2, $id, 1);

        $this->assertSame(1, recurring_find(self::$con, 1, $id)['IsActive']);
    }

    public function testDeleteRemovesOnlyTheOwnersRule(): void
    {
        recurring_save(self::$con, 1, 0, $this->form());
        $id = $this->firstRuleId();

        $this->assertSame('Unable to delete recurring expense.', recurring_delete(self::$con, 2, $id)['message']);
        $this->assertSame('Unable to delete recurring expense.', recurring_delete(self::$con, 1, 999)['message']);
        $this->assertSame(array('ok' => true, 'message' => 'Recurring expense deleted.'), recurring_delete(self::$con, 1, $id));
        $this->assertNull(recurring_find(self::$con, 1, $id));
    }
}
