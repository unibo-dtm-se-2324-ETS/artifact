-- Demo data seed for the account zagbaban@gmail.com (password: 123456)
-- Run once against the detsdb database (phpMyAdmin > SQL tab, or the mysql CLI).
-- Safe to run on an empty database. Re-running will add duplicate rows.

USE `detsdb`;

-- 1) Create the login account if it does not already exist (password 123456 -> md5).
INSERT INTO `tbluser` (`FullName`, `MobileNumber`, `Email`, `Password`, `DefaultCurrency`)
SELECT 'Zag Baban', '07500000000', 'zagbaban@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', 'USD'
WHERE NOT EXISTS (SELECT 1 FROM `tbluser` WHERE `Email` = 'zagbaban@gmail.com');

SET @user_id := (SELECT `ID` FROM `tbluser` WHERE `Email` = 'zagbaban@gmail.com' ORDER BY `ID` DESC LIMIT 1);

-- 2) Categories
INSERT INTO `tblcategories` (`UserId`, `CategoryName`) VALUES
(@user_id, 'Food'),
(@user_id, 'Transport'),
(@user_id, 'Bills'),
(@user_id, 'Shopping'),
(@user_id, 'Health'),
(@user_id, 'Entertainment');

-- 3) Reusable items
INSERT INTO `tblitems` (`UserId`, `ItemName`) VALUES
(@user_id, 'Breakfast'),
(@user_id, 'Lunch'),
(@user_id, 'Coffee'),
(@user_id, 'Taxi'),
(@user_id, 'Bus Ticket'),
(@user_id, 'Groceries'),
(@user_id, 'Internet Bill'),
(@user_id, 'Electricity Bill'),
(@user_id, 'Phone Recharge'),
(@user_id, 'Medicine'),
(@user_id, 'Cinema'),
(@user_id, 'Gym');

-- Resolve category IDs for this user
SET @food := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Food' ORDER BY `ID` DESC LIMIT 1);
SET @transport := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Transport' ORDER BY `ID` DESC LIMIT 1);
SET @bills := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Bills' ORDER BY `ID` DESC LIMIT 1);
SET @shopping := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Shopping' ORDER BY `ID` DESC LIMIT 1);
SET @health := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Health' ORDER BY `ID` DESC LIMIT 1);
SET @entertainment := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Entertainment' ORDER BY `ID` DESC LIMIT 1);

-- 4) Monthly budgets (a few recent months)
INSERT INTO `tblbudgets` (`UserId`, `CategoryId`, `BudgetMonth`, `Currency`, `BudgetAmount`) VALUES
(@user_id, @food,          '2026-06', 'USD', 300.00),
(@user_id, @transport,     '2026-06', 'USD', 120.00),
(@user_id, @bills,         '2026-06', 'USD', 200.00),
(@user_id, @shopping,      '2026-06', 'USD', 180.00),
(@user_id, @health,        '2026-06', 'USD', 100.00),
(@user_id, @entertainment, '2026-06', 'USD', 90.00),
(@user_id, @food,          '2026-07', 'USD', 300.00),
(@user_id, @transport,     '2026-07', 'USD', 120.00),
(@user_id, @bills,         '2026-07', 'USD', 200.00),
(@user_id, @shopping,      '2026-07', 'USD', 180.00),
(@user_id, @health,        '2026-07', 'USD', 100.00),
(@user_id, @entertainment, '2026-07', 'USD', 90.00);

-- 5) Expenses spread across several months and currencies
INSERT INTO `tblexpense`
(`UserId`, `ExpenseDate`, `ExpenseItem`, `ExpenseCost`, `Currency`, `CategoryId`, `Notes`, `ReceiptPath`, `CreatedAt`) VALUES
-- February 2026
(@user_id, '2026-02-03', 'Groceries',        42.10, 'USD', @food,          'Weekly groceries',        '', '2026-02-03 18:10:00'),
(@user_id, '2026-02-06', 'Bus Ticket',        2.50, 'USD', @transport,     'Commute',                 '', '2026-02-06 08:05:00'),
(@user_id, '2026-02-10', 'Electricity Bill', 63.40, 'USD', @bills,         'Monthly electricity',     '', '2026-02-10 11:20:00'),
(@user_id, '2026-02-14', 'Cinema',           16.00, 'USD', @entertainment, 'Valentine movie',         '', '2026-02-14 20:15:00'),
(@user_id, '2026-02-19', 'Coffee',            3.80, 'USD', @food,          'Morning coffee',          '', '2026-02-19 09:00:00'),
(@user_id, '2026-02-25', 'Medicine',         18.90, 'USD', @health,        'Pharmacy',                '', '2026-02-25 15:40:00'),
-- March 2026
(@user_id, '2026-03-02', 'Groceries',        39.75, 'USD', @food,          'Home supplies',           '', '2026-03-02 17:30:00'),
(@user_id, '2026-03-05', 'Internet Bill',    45.00, 'USD', @bills,         'Monthly internet',        '', '2026-03-05 10:00:00'),
(@user_id, '2026-03-09', 'Taxi',             13.20, 'USD', @transport,     'Airport run',             '', '2026-03-09 06:45:00'),
(@user_id, '2026-03-15', 'Shopping',         72.30, 'EUR', @shopping,      'Spring clothes',          '', '2026-03-15 14:10:00'),
(@user_id, '2026-03-21', 'Gym',              35.00, 'USD', @health,        'Monthly membership',      '', '2026-03-21 19:00:00'),
(@user_id, '2026-03-28', 'Lunch',             9.50, 'USD', @food,          'Lunch out',               '', '2026-03-28 13:05:00'),
-- April 2026
(@user_id, '2026-04-01', 'Breakfast',         6.50, 'USD', @food,          'Cafe breakfast',          '', '2026-04-01 08:30:00'),
(@user_id, '2026-04-05', 'Internet Bill',    45.00, 'USD', @bills,         'Monthly internet',        '', '2026-04-05 11:00:00'),
(@user_id, '2026-04-09', 'Phone Recharge',   15.00, 'USD', @bills,         'Mobile balance',          '', '2026-04-09 16:35:00'),
(@user_id, '2026-04-13', 'Cinema',           18.00, 'USD', @entertainment, 'Weekend movie',           '', '2026-04-13 20:30:00'),
(@user_id, '2026-04-18', 'Groceries',        44.60, 'USD', @food,          'Weekly groceries',        '', '2026-04-18 18:20:00'),
(@user_id, '2026-04-24', 'Taxi',             11.50, 'USD', @transport,     'Appointment',             '', '2026-04-24 10:25:00'),
-- May 2026
(@user_id, '2026-05-02', 'Groceries',        47.90, 'USD', @food,          'Groceries',               '', '2026-05-02 18:00:00'),
(@user_id, '2026-05-06', 'Electricity Bill', 58.20, 'USD', @bills,         'Monthly electricity',     '', '2026-05-06 11:10:00'),
(@user_id, '2026-05-11', 'Coffee',            4.20, 'USD', @food,          'Coffee with friend',      '', '2026-05-11 10:30:00'),
(@user_id, '2026-05-16', 'Shopping',         55.00, 'GBP', @shopping,      'Shoes',                   '', '2026-05-16 15:45:00'),
(@user_id, '2026-05-20', 'Bus Ticket',        2.50, 'USD', @transport,     'Commute',                 '', '2026-05-20 08:10:00'),
(@user_id, '2026-05-27', 'Medicine',         21.30, 'USD', @health,        'Pharmacy',                '', '2026-05-27 12:15:00'),
-- June 2026
(@user_id, '2026-06-03', 'Groceries',        43.15, 'USD', @food,          'Weekly groceries',        '', '2026-06-03 18:25:00'),
(@user_id, '2026-06-07', 'Internet Bill',    45.00, 'USD', @bills,         'Monthly internet',        '', '2026-06-07 10:05:00'),
(@user_id, '2026-06-12', 'Cinema',           17.50, 'USD', @entertainment, 'New release',             '', '2026-06-12 21:00:00'),
(@user_id, '2026-06-17', 'Taxi',             12.75, 'USD', @transport,     'Late night ride',         '', '2026-06-17 23:20:00'),
(@user_id, '2026-06-22', 'Gym',              35.00, 'USD', @health,        'Monthly membership',      '', '2026-06-22 19:30:00'),
(@user_id, '2026-06-28', 'Lunch',            10.80, 'USD', @food,          'Team lunch',              '', '2026-06-28 13:40:00'),
-- July 2026
(@user_id, '2026-07-02', 'Groceries',        46.40, 'USD', @food,          'Groceries',               '', '2026-07-02 18:15:00'),
(@user_id, '2026-07-05', 'Electricity Bill', 61.10, 'USD', @bills,         'Monthly electricity',     '', '2026-07-05 11:25:00'),
(@user_id, '2026-07-09', 'Coffee',            3.90, 'USD', @food,          'Iced coffee',             '', '2026-07-09 09:15:00'),
(@user_id, '2026-07-13', 'Shopping',         88.20, 'USD', @shopping,      'Summer sale',             '', '2026-07-13 16:00:00'),
(@user_id, '2026-07-18', 'Phone Recharge',   15.00, 'USD', @bills,         'Mobile balance',          '', '2026-07-18 16:50:00'),
(@user_id, '2026-07-22', 'Lunch',             9.75, 'USD', @food,          'Lunch out',               '', '2026-07-22 13:20:00'),
(@user_id, '2026-07-24', 'Taxi',             10.40, 'USD', @transport,     'Return trip',             '', '2026-07-24 17:35:00');

-- 6) Recurring rules
INSERT INTO `tblrecurring`
(`UserId`, `ExpenseItem`, `ExpenseCost`, `Currency`, `CategoryId`, `Notes`, `Frequency`, `StartDate`, `NextRunDate`, `IsActive`) VALUES
(@user_id, 'Internet Bill',    45.00, 'USD', @bills,  'Monthly internet subscription', 'monthly', '2026-08-05', '2026-08-05', 1),
(@user_id, 'Phone Recharge',   15.00, 'USD', @bills,  'Monthly mobile recharge',       'monthly', '2026-08-09', '2026-08-09', 1),
(@user_id, 'Gym',              35.00, 'USD', @health, 'Monthly gym membership',        'monthly', '2026-08-22', '2026-08-22', 1),
(@user_id, 'Bus Pass',         30.00, 'USD', @transport, 'Weekly commute pass',        'weekly',  '2026-08-01', '2026-08-01', 1);

-- 7) Default preferences
UPDATE `tbluser`
SET `DefaultCurrency`='USD',
    `DefaultCategoryId`=@food
WHERE `ID`=@user_id;
