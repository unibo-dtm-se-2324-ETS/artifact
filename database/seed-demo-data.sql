USE `detsdb`;

SET @user_id := 1;

INSERT INTO `tblcategories` (`UserId`, `CategoryName`) VALUES
(@user_id, 'Food'),
(@user_id, 'Transport'),
(@user_id, 'Bills'),
(@user_id, 'Shopping'),
(@user_id, 'Health'),
(@user_id, 'Entertainment');

INSERT INTO `tblitems` (`UserId`, `ItemName`) VALUES
(@user_id, 'Breakfast'),
(@user_id, 'Lunch'),
(@user_id, 'Taxi'),
(@user_id, 'Internet Bill'),
(@user_id, 'Groceries'),
(@user_id, 'Medicine'),
(@user_id, 'Cinema'),
(@user_id, 'Phone Recharge');

SET @food := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Food' ORDER BY `ID` DESC LIMIT 1);
SET @transport := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Transport' ORDER BY `ID` DESC LIMIT 1);
SET @bills := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Bills' ORDER BY `ID` DESC LIMIT 1);
SET @shopping := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Shopping' ORDER BY `ID` DESC LIMIT 1);
SET @health := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Health' ORDER BY `ID` DESC LIMIT 1);
SET @entertainment := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Entertainment' ORDER BY `ID` DESC LIMIT 1);

INSERT INTO `tblbudgets` (`UserId`, `CategoryId`, `BudgetMonth`, `Currency`, `BudgetAmount`) VALUES
(@user_id, @food, '2026-04', 'USD', 250.00),
(@user_id, @transport, '2026-04', 'USD', 120.00),
(@user_id, @bills, '2026-04', 'USD', 180.00),
(@user_id, @shopping, '2026-04', 'USD', 200.00),
(@user_id, @health, '2026-04', 'USD', 100.00),
(@user_id, @entertainment, '2026-04', 'USD', 80.00);

INSERT INTO `tblexpense`
(`UserId`, `ExpenseDate`, `ExpenseItem`, `ExpenseCost`, `Currency`, `CategoryId`, `Notes`, `ReceiptPath`, `CreatedAt`) VALUES
(@user_id, '2026-04-01', 'Breakfast', 6.50, 'USD', @food, 'Breakfast before class', '', '2026-04-01 08:30:00'),
(@user_id, '2026-04-02', 'Taxi', 12.00, 'USD', @transport, 'Taxi to university', '', '2026-04-02 09:10:00'),
(@user_id, '2026-04-03', 'Groceries', 38.75, 'USD', @food, 'Weekly groceries', '', '2026-04-03 18:20:00'),
(@user_id, '2026-04-05', 'Internet Bill', 45.00, 'USD', @bills, 'Monthly internet payment', '', '2026-04-05 11:00:00'),
(@user_id, '2026-04-07', 'Lunch', 9.25, 'USD', @food, 'Lunch at campus', '', '2026-04-07 13:15:00'),
(@user_id, '2026-04-09', 'Phone Recharge', 15.00, 'USD', @bills, 'Mobile balance recharge', '', '2026-04-09 16:35:00'),
(@user_id, '2026-04-11', 'Medicine', 22.40, 'USD', @health, 'Pharmacy purchase', '', '2026-04-11 12:05:00'),
(@user_id, '2026-04-13', 'Cinema', 18.00, 'USD', @entertainment, 'Weekend movie ticket', '', '2026-04-13 20:30:00'),
(@user_id, '2026-04-15', 'Taxi', 10.00, 'USD', @transport, 'Return trip', '', '2026-04-15 17:45:00'),
(@user_id, '2026-04-17', 'Shopping', 64.90, 'USD', @shopping, 'Clothes purchase', '', '2026-04-17 19:10:00'),
(@user_id, '2026-04-20', 'Lunch', 8.75, 'USD', @food, 'Lunch with friends', '', '2026-04-20 14:00:00'),
(@user_id, '2026-04-22', 'Groceries', 41.20, 'USD', @food, 'Home supplies', '', '2026-04-22 18:45:00'),
(@user_id, '2026-04-24', 'Taxi', 11.50, 'USD', @transport, 'Appointment transport', '', '2026-04-24 10:25:00'),
(@user_id, '2026-04-26', 'Breakfast', 5.80, 'USD', @food, 'Morning meal', '', '2026-04-26 08:40:00'),
(@user_id, '2026-04-28', 'Lunch', 10.50, 'USD', @food, 'Today lunch', '', '2026-04-28 13:25:00');

INSERT INTO `tblrecurring`
(`UserId`, `ExpenseItem`, `ExpenseCost`, `Currency`, `CategoryId`, `Notes`, `Frequency`, `StartDate`, `NextRunDate`, `IsActive`) VALUES
(@user_id, 'Internet Bill', 45.00, 'USD', @bills, 'Monthly internet subscription', 'monthly', '2026-05-05', '2026-05-05', 1),
(@user_id, 'Phone Recharge', 15.00, 'USD', @bills, 'Monthly mobile recharge', 'monthly', '2026-05-09', '2026-05-09', 1);

UPDATE `tbluser`
SET `DefaultCurrency`='USD',
    `DefaultCategoryId`=@food
WHERE `ID`=@user_id;
