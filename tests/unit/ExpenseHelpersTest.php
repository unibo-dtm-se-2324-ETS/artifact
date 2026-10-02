<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/expense-helpers.php';

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

    public function testReceiptUploadReturnsEmptyResultWhenNoFileWasUploaded(): void
    {
        $result = expense_handle_receipt_upload(
            array('error' => UPLOAD_ERR_NO_FILE),
            1
        );

        $this->assertSame(array('path' => '', 'error' => ''), $result);
    }

    public function testReceiptUploadRejectsUnsupportedFileExtension(): void
    {
        $result = expense_handle_receipt_upload(
            array(
                'error' => UPLOAD_ERR_OK,
                'name' => 'receipt.exe',
                'tmp_name' => __FILE__,
            ),
            1
        );

        $this->assertSame('', $result['path']);
        $this->assertSame('Receipt must be a JPG, PNG, or PDF file.', $result['error']);
    }

    public function testDeleteReceiptFileRemovesExistingReceipt(): void
    {
        $receiptDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'receipts';
        if (!is_dir($receiptDir)) {
            mkdir($receiptDir, 0777, true);
        }

        $receiptPath = $receiptDir . DIRECTORY_SEPARATOR . 'unit-test-receipt.txt';
        file_put_contents($receiptPath, 'temporary test receipt');

        $this->assertFileExists($receiptPath);

        expense_delete_receipt_file('uploads/receipts/unit-test-receipt.txt');

        $this->assertFileDoesNotExist($receiptPath);
    }

    public function testDeleteReceiptFileIgnoresEmptyAndMissingPaths(): void
    {
        expense_delete_receipt_file('');
        expense_delete_receipt_file('   ');
        expense_delete_receipt_file('uploads/receipts/does-not-exist.pdf');

        $this->addToAssertionCount(1);
    }

    public function testEverySupportedCurrencyHasASymbol(): void
    {
        $expected = array('USD' => '$', 'EUR' => '€', 'IQD' => 'IQD', 'GBP' => '£', 'AED' => 'AED', 'SAR' => 'SAR');

        foreach ($expected as $code => $symbol) {
            $this->assertSame($symbol, expense_currency_symbol($code));
        }
    }

    public function testMoneyFormattingAcceptsStringsAndRoundsToTwoDecimals(): void
    {
        $this->assertSame('0.00 €', expense_money('abc', 'EUR'));
        $this->assertSame('3.14 £', expense_money('3.14159', 'GBP'));
        $this->assertSame('1,234,567.80 JPY', expense_money(1234567.8, 'JPY'));
    }

    public function testHtmlEscapingHandlesNullNumbersAndQuotes(): void
    {
        $this->assertSame('', expense_h(null));
        $this->assertSame('42', expense_h(42));
        $this->assertSame('&quot;a&quot; &amp; &#039;b&#039;', expense_h('"a" & \'b\''));
    }

    public function testMonthKeyDefaultsToTheCurrentMonth(): void
    {
        $this->assertSame(date('Y-m'), expense_month_key());
        $this->assertSame(date('Y-m'), expense_month_key(null));
    }

    public function testSelectedCurrencyUsesUsdByDefaultAndAcceptsEmptyInput(): void
    {
        $this->assertSame('USD', expense_selected_currency('nonsense'));
        $this->assertSame('USD', expense_selected_currency(''));
        $this->assertSame('USD', expense_selected_currency(null));
        $this->assertSame('SAR', expense_selected_currency('sar'));
    }

    public function testBudgetProgressHandlesNegativeAndStringValues(): void
    {
        $this->assertSame(0, expense_budget_progress(50, -10));
        $this->assertSame(0, expense_budget_progress(50, '0'));
        $this->assertEquals(0, expense_budget_progress(0, 100));
        $this->assertEquals(0, expense_budget_progress(-20, 100));
        $this->assertEquals(25, expense_budget_progress('25', '100'));
    }

    public function testCsrfTokenIsStableWithinASession(): void
    {
        $this->assertSame(expense_csrf_token(), expense_csrf_token());
    }

    public function testCsrfVerificationRejectsMissingNonStringAndEmptyTokens(): void
    {
        $this->assertFalse(expense_verify_csrf('anything'));

        expense_csrf_token();

        $this->assertFalse(expense_verify_csrf(null));
        $this->assertFalse(expense_verify_csrf(123));
        $this->assertFalse(expense_verify_csrf(''));
    }

    public function testReceiptUploadReportsUploadErrors(): void
    {
        $this->assertSame(
            array('path' => '', 'error' => 'Receipt upload failed.'),
            expense_handle_receipt_upload(array('error' => UPLOAD_ERR_INI_SIZE), 1)
        );
        $this->assertSame(
            array('path' => '', 'error' => ''),
            expense_handle_receipt_upload(array(), 1)
        );
    }

    public function testReceiptUploadAcceptsAllowedExtensionsCaseInsensitively(): void
    {
        foreach (array('scan.JPG', 'scan.jpeg', 'scan.Png', 'scan.pdf') as $name) {
            $result = expense_handle_receipt_upload(
                array('error' => UPLOAD_ERR_OK, 'name' => $name, 'tmp_name' => __FILE__),
                1
            );

            // The file was not a real HTTP upload, so saving is refused, but
            // the extension itself must not be the reason.
            $this->assertSame('Could not save the uploaded receipt.', $result['error'], $name);
            $this->assertSame('', $result['path']);
        }
    }
}
