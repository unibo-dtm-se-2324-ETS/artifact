<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/expense-helpers.php';

final class ExpenseHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = array();
    }

    public function testCurrencyOptionsContainSupportedCurrencies(): void
    {
        $this->assertSame(
            array('USD', 'EUR', 'IQD', 'GBP', 'AED', 'SAR'),
            expense_currency_options()
        );
    }

    public function testCurrencySymbolFallsBackToCurrencyCode(): void
    {
        $this->assertSame('$', expense_currency_symbol('USD'));
        $this->assertSame('IQD', expense_currency_symbol('IQD'));
        $this->assertSame('JPY', expense_currency_symbol('JPY'));
    }

    public function testMoneyFormattingUsesTwoDecimalsAndCurrencySymbol(): void
    {
        $this->assertSame('12.50 $', expense_money(12.5, 'USD'));
        $this->assertSame('1,200.00 IQD', expense_money(1200, 'IQD'));
    }

    public function testHtmlEscapingProtectsSpecialCharacters(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;',
            expense_h("<script>alert('x')</script>")
        );
    }

    public function testMonthKeyConvertsDateToYearMonth(): void
    {
        $this->assertSame('2026-05', expense_month_key('2026-05-16'));
    }

    public function testSelectedCurrencyNormalizesValidInputAndUsesDefaultForInvalidInput(): void
    {
        $this->assertSame('EUR', expense_selected_currency(' eur '));
        $this->assertSame('GBP', expense_selected_currency('not-valid', 'GBP'));
    }

    public function testBudgetProgressIsClampedBetweenZeroAndOneHundred(): void
    {
        $this->assertSame(0, expense_budget_progress(50, 0));
        $this->assertSame(50.0, expense_budget_progress(50, 100));
        $this->assertSame(100, expense_budget_progress(150, 100));
    }

    public function testCsrfTokenIsGeneratedAndVerified(): void
    {
        $token = expense_csrf_token();

        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));
        $this->assertTrue(expense_verify_csrf($token));
        $this->assertFalse(expense_verify_csrf('wrong-token'));
    }
}
