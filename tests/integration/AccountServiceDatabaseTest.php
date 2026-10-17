<?php

declare(strict_types=1);

require_once __DIR__ . '/DatabaseTestCase.php';
require_once __DIR__ . '/../../src/expense-helpers.php';
require_once __DIR__ . '/../../src/account-service.php';

/**
 * Integration tests for registration, login, password handling and the profile.
 */
final class AccountServiceDatabaseTest extends DatabaseTestCase
{
    private function insertUser(string $email, string $password, string $mobile = '555', string $name = 'Ada', bool $legacy = false): int
    {
        $hash = $legacy ? md5($password) : auth_hash_password($password);
        mysqli_query(
            self::$con,
            sprintf(
                "INSERT INTO tbluser (FullName, MobileNumber, Email, Password) VALUES ('%s', '%s', '%s', '%s')",
                mysqli_real_escape_string(self::$con, $name), $mobile, mysqli_real_escape_string(self::$con, $email), $hash
            )
        );

        return (int)mysqli_insert_id(self::$con);
    }

    private function storedPassword(int $id): string
    {
        return $this->query("SELECT Password FROM tbluser WHERE ID=$id")[0]['Password'];
    }

    // auth_register

    public function testRegisterCreatesAnAccountWithAHashedPassword(): void
    {
        $result = auth_register(self::$con, 'Ada', '555', 'ada@example.com', 'Secret123!');

        $this->assertSame(array('ok' => true, 'message' => 'You have successfully registered'), $result);

        $row = $this->query("SELECT FullName, MobileNumber, Email, Password FROM tbluser")[0];
        $this->assertSame('Ada', $row['FullName']);
        $this->assertSame('ada@example.com', $row['Email']);
        $this->assertNotSame('Secret123!', $row['Password']);
        $this->assertNotSame(md5('Secret123!'), $row['Password']);
        $this->assertTrue(password_verify('Secret123!', $row['Password']));
    }

    public function testRegisterRejectsAnEmailThatIsAlreadyUsed(): void
    {
        $this->insertUser('ada@example.com', 'first');

        $result = auth_register(self::$con, 'Other', '999', 'ada@example.com', 'second');

        $this->assertFalse($result['ok']);
        $this->assertSame('This email is already associated with another account', $result['message']);
        $this->assertCount(1, $this->query('SELECT ID FROM tbluser'));
    }

    public function testRegisterStoresQuotesAndSqlTextAsPlainData(): void
    {
        $name = "Robert'); DROP TABLE tbluser;--";

        $this->assertTrue(auth_register(self::$con, $name, '1', "o'brien@example.com", 'pw')['ok']);

        $this->assertSame($name, $this->query('SELECT FullName FROM tbluser')[0]['FullName']);
    }

    // auth_login

    public function testLoginReturnsTheUserIdForTheRightPassword(): void
    {
        $id = $this->insertUser('ada@example.com', 'Secret123!');

        $this->assertSame($id, auth_login(self::$con, 'ada@example.com', 'Secret123!'));
    }

    public function testLoginRejectsAWrongPasswordAndAnUnknownEmail(): void
    {
        $this->insertUser('ada@example.com', 'Secret123!');

        $this->assertSame(0, auth_login(self::$con, 'ada@example.com', 'wrong'));
        $this->assertSame(0, auth_login(self::$con, 'nobody@example.com', 'Secret123!'));
        $this->assertSame(0, auth_login(self::$con, 'ada@example.com', ''));
    }

    public function testLoginIsNotFooledByAnSqlInjectionAttempt(): void
    {
        $this->insertUser('ada@example.com', 'Secret123!');

        $this->assertSame(0, auth_login(self::$con, "' OR '1'='1", 'anything'));
        $this->assertSame(0, auth_login(self::$con, "ada@example.com' -- ", 'anything'));
        $this->assertSame(0, auth_login(self::$con, "' OR 1=1 #", "' OR '1'='1"));
    }

    public function testLoginUpgradesAnOldMd5HashToTheCurrentFormat(): void
    {
        $id = $this->insertUser('old@example.com', 'OldPassword', '555', 'Old', true);
        $this->assertSame(md5('OldPassword'), $this->storedPassword($id));

        $this->assertSame($id, auth_login(self::$con, 'old@example.com', 'OldPassword'));

        $stored = $this->storedPassword($id);
        $this->assertFalse(auth_is_legacy_hash($stored));
        $this->assertTrue(password_verify('OldPassword', $stored));
        $this->assertSame($id, auth_login(self::$con, 'old@example.com', 'OldPassword'));
    }

    public function testLoginKeepsTheHashWhenNoUpgradeIsNeeded(): void
    {
        $id = $this->insertUser('ada@example.com', 'Secret123!');
        $before = $this->storedPassword($id);

        auth_login(self::$con, 'ada@example.com', 'Secret123!');

        $this->assertSame($before, $this->storedPassword($id));
    }

    // password recovery

    public function testFindUserByContactNeedsBothTheEmailAndTheMobileNumber(): void
    {
        $id = $this->insertUser('ada@example.com', 'x', '555');

        $this->assertSame($id, auth_find_user_by_contact(self::$con, 'ada@example.com', '555'));
        $this->assertSame(0, auth_find_user_by_contact(self::$con, 'ada@example.com', '000'));
        $this->assertSame(0, auth_find_user_by_contact(self::$con, 'other@example.com', '555'));
    }

    public function testResetPasswordChangesThePasswordOfTheMatchingAccountOnly(): void
    {
        $ada = $this->insertUser('ada@example.com', 'old', '555');
        $bob = $this->insertUser('bob@example.com', 'bobs', '777');

        $this->assertTrue(auth_reset_password(self::$con, 'ada@example.com', '555', 'NewPassword1'));

        $this->assertSame($ada, auth_login(self::$con, 'ada@example.com', 'NewPassword1'));
        $this->assertSame(0, auth_login(self::$con, 'ada@example.com', 'old'));
        $this->assertSame($bob, auth_login(self::$con, 'bob@example.com', 'bobs'));
    }

    public function testResetPasswordFailsWhenTheContactDetailsDoNotMatch(): void
    {
        $this->insertUser('ada@example.com', 'old', '555');

        $this->assertFalse(auth_reset_password(self::$con, 'ada@example.com', '000', 'NewPassword1'));
        $this->assertFalse(auth_reset_password(self::$con, '', '', 'NewPassword1'));
        $this->assertSame(1, auth_login(self::$con, 'ada@example.com', 'old'));
    }

    // auth_change_password

    public function testChangePasswordNeedsTheCurrentPassword(): void
    {
        $id = $this->insertUser('ada@example.com', 'current');

        $this->assertFalse(auth_change_password(self::$con, $id, 'wrong', 'NewPassword1'));
        $this->assertSame($id, auth_login(self::$con, 'ada@example.com', 'current'));

        $this->assertTrue(auth_change_password(self::$con, $id, 'current', 'NewPassword1'));
        $this->assertSame($id, auth_login(self::$con, 'ada@example.com', 'NewPassword1'));
        $this->assertSame(0, auth_login(self::$con, 'ada@example.com', 'current'));
    }

    public function testChangePasswordAcceptsAnOldMd5CurrentPassword(): void
    {
        $id = $this->insertUser('old@example.com', 'OldPassword', '555', 'Old', true);

        $this->assertTrue(auth_change_password(self::$con, $id, 'OldPassword', 'Brand1New'));
        $this->assertTrue(password_verify('Brand1New', $this->storedPassword($id)));
    }

    public function testChangePasswordFailsForAnUnknownUser(): void
    {
        $this->assertFalse(auth_change_password(self::$con, 999, 'a', 'b'));
    }

    // profile

    public function testProfileGetAndFullName(): void
    {
        $id = $this->insertUser('ada@example.com', 'x', '555', 'Ada Lovelace');

        $profile = profile_get(self::$con, $id);

        $this->assertSame('Ada Lovelace', $profile['FullName']);
        $this->assertSame('ada@example.com', $profile['Email']);
        $this->assertSame('USD', $profile['DefaultCurrency']);
        $this->assertSame('Ada Lovelace', profile_full_name(self::$con, $id));
        $this->assertNull(profile_get(self::$con, 999));
        $this->assertSame('', profile_full_name(self::$con, 999));
    }

    public function testProfileUpdateSavesTheNameContactAndDefaults(): void
    {
        $id = $this->insertUser('ada@example.com', 'x');
        mysqli_query(self::$con, "INSERT INTO tblcategories (ID, UserId, CategoryName) VALUES (8, $id, 'Food')");
        $form = profile_form_from_input(array('fullname' => 'New Name', 'contactnumber' => '999', 'defaultcurrency' => 'EUR', 'defaultcategoryid' => '8'));

        $this->assertSame('', profile_update(self::$con, $id, $form));

        $profile = profile_get(self::$con, $id);
        $this->assertSame('New Name', $profile['FullName']);
        $this->assertSame('999', $profile['MobileNumber']);
        $this->assertSame('EUR', $profile['DefaultCurrency']);
        $this->assertSame(8, $profile['DefaultCategoryId']);
    }

    public function testProfileUpdateRequiresAName(): void
    {
        $id = $this->insertUser('ada@example.com', 'x');

        $this->assertSame('Full name is required.', profile_update(self::$con, $id, profile_form_from_input(array('fullname' => '  '))));
        $this->assertSame('Ada', profile_full_name(self::$con, $id));
    }

    public function testProfileUpdateRejectsAnotherUsersCategoryAsDefault(): void
    {
        $id = $this->insertUser('ada@example.com', 'x');
        mysqli_query(self::$con, "INSERT INTO tblcategories (ID, UserId, CategoryName) VALUES (9, 999, 'Not mine')");

        $form = profile_form_from_input(array('fullname' => 'Ada', 'defaultcategoryid' => '9'));

        $this->assertSame('The selected default category is invalid.', profile_update(self::$con, $id, $form));
    }

    public function testProfileUpdateWithoutADefaultCategoryClearsIt(): void
    {
        $id = $this->insertUser('ada@example.com', 'x');

        $this->assertSame('', profile_update(self::$con, $id, profile_form_from_input(array('fullname' => 'Ada'))));
        $this->assertNull(profile_get(self::$con, $id)['DefaultCategoryId'] ?: null);
    }
}
