<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the recurring-expense form handling, which needs no database.
 */
final class RecurringFormTest extends TestCase
{
    protected function setUp(): void
    {
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/recurring-service.php';
    }

    private function validForm(): array
    {
        return array('item' => 'Rent', 'cost' => '500', 'currency' => 'USD', 'categoryid' => '3', 'frequency' => 'monthly', 'startdate' => '2026-05-01', 'notes' => '');
    }

    public function testDefaultsForANewRule(): void
    {
        $form = recurring_form_defaults(null);

        $this->assertSame('', $form['item']);
        $this->assertSame('USD', $form['currency']);
        $this->assertSame('monthly', $form['frequency']);
        $this->assertSame(date('Y-m-d'), $form['startdate']);
    }

    public function testDefaultsForAStoredRule(): void
    {
        $form = recurring_form_defaults(array(
            'ExpenseItem' => 'Gym', 'ExpenseCost' => '25.00', 'Currency' => 'eur', 'CategoryId' => 4,
            'Frequency' => 'weekly', 'StartDate' => '2026-02-03', 'Notes' => 'monthly pass',
        ));

        $this->assertSame(
            array('item' => 'Gym', 'cost' => '25.00', 'currency' => 'EUR', 'categoryid' => '4', 'frequency' => 'weekly', 'startdate' => '2026-02-03', 'notes' => 'monthly pass'),
            $form
        );
    }

    public function testFormFromInputTrimsAndNormalises(): void
    {
        $form = recurring_form_from_input(array(
            'item' => ' Rent ', 'cost' => ' 500 ', 'currency' => 'xyz', 'categoryid' => ' 3 ',
            'frequency' => 'weekly', 'startdate' => ' 2026-05-01 ', 'notes' => ' note ',
        ));

        $this->assertSame(array('item' => 'Rent', 'cost' => '500', 'currency' => 'USD', 'categoryid' => '3', 'frequency' => 'weekly', 'startdate' => '2026-05-01', 'notes' => 'note'), $form);
    }

    public function testAnyFrequencyExceptWeeklyBecomesMonthly(): void
    {
        $this->assertSame('monthly', recurring_form_from_input(array('frequency' => 'daily'))['frequency']);
        $this->assertSame('monthly', recurring_form_from_input(array())['frequency']);
    }

    public function testFormFromEmptyInputUsesSafeDefaults(): void
    {
        $form = recurring_form_from_input(array());

        $this->assertSame('', $form['item']);
        $this->assertSame('USD', $form['currency']);
        $this->assertSame(date('Y-m-d'), $form['startdate']);
    }

    public function testValidFormHasNoError(): void
    {
        $this->assertSame('', recurring_validate_form($this->validForm()));
    }

    #[DataProvider('invalidFormProvider')]
    public function testInvalidFormReturnsTheMatchingMessage(array $changes, string $expected): void
    {
        $this->assertSame($expected, recurring_validate_form(array_merge($this->validForm(), $changes)));
    }

    public static function invalidFormProvider(): array
    {
        return array(
            'no item' => array(array('item' => ''), 'Please fill in a valid item and amount.'),
            'no cost' => array(array('cost' => ''), 'Please fill in a valid item and amount.'),
            'text cost' => array(array('cost' => 'abc'), 'Please fill in a valid item and amount.'),
            'zero cost' => array(array('cost' => '0'), 'Please fill in a valid item and amount.'),
            'negative cost' => array(array('cost' => '-5'), 'Please fill in a valid item and amount.'),
            'no category' => array(array('categoryid' => ''), 'Please choose a category.'),
            'zero category' => array(array('categoryid' => '0'), 'Please choose a category.'),
            'no start date' => array(array('startdate' => ''), 'Please choose a valid start date.'),
            'bad start date' => array(array('startdate' => 'whenever'), 'Please choose a valid start date.'),
        );
    }
}
