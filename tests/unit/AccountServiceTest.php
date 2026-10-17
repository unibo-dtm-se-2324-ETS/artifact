<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the password handling and the profile form, which need no database.
 */
final class AccountServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Loaded here, inside the running test, so that the code coverage includes the files.
        require_once __DIR__ . '/../../src/expense-helpers.php';
        require_once __DIR__ . '/../../src/account-service.php';
    }

    public function testHashedPasswordsAreVerifiedAndNeverStoredAsPlainText(): void
    {
        $hash = auth_hash_password('Secret123!');

        $this->assertNotSame('Secret123!', $hash);
        $this->assertFalse(auth_is_legacy_hash($hash));
        $this->assertTrue(auth_verify_password('Secret123!', $hash));
        $this->assertFalse(auth_verify_password('secret123!', $hash));
    }

    public function testTheSamePasswordGetsADifferentHashEachTime(): void
    {
        $this->assertNotSame(auth_hash_password('same'), auth_hash_password('same'));
    }

    public function testLegacyMd5HashesStillVerifyAndAreMarkedForUpgrade(): void
    {
        $legacy = md5('OldPassword');

        $this->assertTrue(auth_is_legacy_hash($legacy));
        $this->assertTrue(auth_is_legacy_hash(strtoupper($legacy)));
        $this->assertTrue(auth_verify_password('OldPassword', $legacy));
        $this->assertTrue(auth_verify_password('OldPassword', strtoupper($legacy)));
        $this->assertFalse(auth_verify_password('WrongPassword', $legacy));
        $this->assertTrue(auth_needs_rehash($legacy));
    }

    public function testCurrentHashesDoNotNeedAnUpgrade(): void
    {
        $this->assertFalse(auth_needs_rehash(auth_hash_password('x')));
    }

    public function testEmptyAndUnknownStoredValuesNeverVerify(): void
    {
        $this->assertFalse(auth_verify_password('anything', ''));
        $this->assertFalse(auth_verify_password('', ''));
        $this->assertFalse(auth_verify_password('anything', 'not-a-hash'));
        $this->assertFalse(auth_is_legacy_hash('not-a-hash'));
        $this->assertFalse(auth_is_legacy_hash(substr(md5('x'), 0, 31)));
    }

    public function testProfileFormDefaultsForAStoredProfile(): void
    {
        $form = profile_form_defaults(array('FullName' => 'Ada', 'MobileNumber' => '123', 'DefaultCurrency' => 'eur', 'DefaultCategoryId' => '4'));

        $this->assertSame(array('fullname' => 'Ada', 'contactnumber' => '123', 'defaultcurrency' => 'EUR', 'defaultcategoryid' => '4'), $form);
    }

    public function testProfileFormDefaultsWithoutAProfileOrDefaultCategory(): void
    {
        $this->assertSame(
            array('fullname' => '', 'contactnumber' => '', 'defaultcurrency' => 'USD', 'defaultcategoryid' => ''),
            profile_form_defaults(null)
        );
        $this->assertSame('', profile_form_defaults(array('FullName' => 'A', 'MobileNumber' => '', 'DefaultCurrency' => 'USD', 'DefaultCategoryId' => null))['defaultcategoryid']);
    }

    public function testProfileFormFromInputTrimsAndValidatesTheCurrency(): void
    {
        $form = profile_form_from_input(array('fullname' => '  Ada ', 'contactnumber' => ' 555 ', 'defaultcurrency' => 'xyz', 'defaultcategoryid' => ' 3 '));

        $this->assertSame(array('fullname' => 'Ada', 'contactnumber' => '555', 'defaultcurrency' => 'USD', 'defaultcategoryid' => '3'), $form);
    }

    public function testProfileFormFromEmptyInputUsesSafeDefaults(): void
    {
        $this->assertSame(
            array('fullname' => '', 'contactnumber' => '', 'defaultcurrency' => 'USD', 'defaultcategoryid' => ''),
            profile_form_from_input(array())
        );
    }
}
