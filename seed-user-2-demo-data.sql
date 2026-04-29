USE `detsdb`;

SET @user_id := 2;

INSERT INTO `tblitems` (`UserId`, `ItemName`)
SELECT @user_id, item_name
FROM (
  SELECT 'Coffee' AS item_name UNION ALL
  SELECT 'Lunch' UNION ALL
  SELECT 'Fuel' UNION ALL
  SELECT 'Internet Bill' UNION ALL
  SELECT 'Groceries' UNION ALL
  SELECT 'Gym Fee' UNION ALL
  SELECT 'Books' UNION ALL
  SELECT 'Family Dinner'
) AS items
WHERE NOT EXISTS (
  SELECT 1
  FROM `tblitems`
  WHERE `UserId`=@user_id AND `ItemName`=items.item_name
);

SET @food := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Food' ORDER BY `ID` LIMIT 1);
SET @transport := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Transport' ORDER BY `ID` LIMIT 1);
SET @bills := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Bills' ORDER BY `ID` LIMIT 1);
SET @shopping := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Shopping' ORDER BY `ID` LIMIT 1);
SET @health := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Health' ORDER BY `ID` LIMIT 1);
SET @entertainment := (SELECT `ID` FROM `tblcategories` WHERE `UserId`=@user_id AND `CategoryName`='Entertainment' ORDER BY `ID` LIMIT 1);

INSERT INTO `tblbudgets` (`UserId`, `CategoryId`, `BudgetMonth`, `Currency`, `BudgetAmount`)
SELECT @user_id, category_id, '2026-04', 'USD', amount
FROM (
  SELECT @food AS category_id, 220.00 AS amount UNION ALL
  SELECT @transport, 150.00 UNION ALL
  SELECT @bills, 130.00 UNION ALL
  SELECT @shopping, 180.00 UNION ALL
  SELECT @health, 90.00 UNION ALL
  SELECT @entertainment, 120.00
) AS budgets
WHERE category_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `tblbudgets`
    WHERE `UserId`=@user_id
      AND `CategoryId`=budgets.category_id
      AND `BudgetMonth`='2026-04'
      AND `Currency`='USD'
  );

INSERT INTO `tblexpense`
(`UserId`, `ExpenseDate`, `ExpenseItem`, `ExpenseCost`, `Currency`, `CategoryId`, `Notes`, `ReceiptPath`, `CreatedAt`) VALUES
(@user_id, '2026-04-02', 'Coffee', 4.25, 'USD', @food, 'Morning coffee', '', '2026-04-02 08:15:00'),
(@user_id, '2026-04-03', 'Fuel', 28.00, 'USD', @transport, 'Car fuel', '', '2026-04-03 17:20:00'),
(@user_id, '2026-04-04', 'Groceries', 52.30, 'USD', @food, 'Weekly food shopping', '', '2026-04-04 18:05:00'),
(@user_id, '2026-04-06', 'Internet Bill', 35.00, 'USD', @bills, 'Home internet', '', '2026-04-06 10:00:00'),
(@user_id, '2026-04-08', 'Lunch', 11.75, 'USD', @food, 'Lunch at work', '', '2026-04-08 13:30:00'),
(@user_id, '2026-04-10', 'Books', 24.00, 'USD', @shopping, 'Project reference book', '', '2026-04-10 16:45:00'),
(@user_id, '2026-04-12', 'Gym Fee', 30.00, 'USD', @health, 'Monthly gym payment', '', '2026-04-12 09:25:00'),
(@user_id, '2026-04-14', 'Family Dinner', 46.50, 'USD', @entertainment, 'Dinner with family', '', '2026-04-14 21:00:00'),
(@user_id, '2026-04-16', 'Fuel', 25.00, 'USD', @transport, 'Second fuel payment', '', '2026-04-16 18:15:00'),
(@user_id, '2026-04-18', 'Lunch', 9.90, 'USD', @food, 'Quick lunch', '', '2026-04-18 12:55:00'),
(@user_id, '2026-04-21', 'Groceries', 47.80, 'USD', @food, 'Household groceries', '', '2026-04-21 19:05:00'),
(@user_id, '2026-04-23', 'Coffee', 5.00, 'USD', @food, 'Study session coffee', '', '2026-04-23 15:40:00'),
(@user_id, '2026-04-25', 'Books', 18.50, 'USD', @shopping, 'Notebook and pens', '', '2026-04-25 11:10:00'),
(@user_id, '2026-04-27', 'Lunch', 12.40, 'USD', @food, 'Lunch before project work', '', '2026-04-27 14:05:00'),
(@user_id, '2026-04-28', 'Fuel', 22.00, 'USD', @transport, 'Fuel before university trip', '', '2026-04-28 09:35:00');

INSERT INTO `tblrecurring`
(`UserId`, `ExpenseItem`, `ExpenseCost`, `Currency`, `CategoryId`, `Notes`, `Frequency`, `StartDate`, `NextRunDate`, `IsActive`)
SELECT @user_id, item_name, cost, 'USD', category_id, notes, frequency, start_date, start_date, 1
FROM (
  SELECT 'Internet Bill' AS item_name, 35.00 AS cost, @bills AS category_id, 'Monthly internet subscription' AS notes, 'monthly' AS frequency, '2026-05-06' AS start_date UNION ALL
  SELECT 'Gym Fee', 30.00, @health, 'Monthly gym payment', 'monthly', '2026-05-12'
) AS recurring
WHERE category_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM `tblrecurring`
    WHERE `UserId`=@user_id
      AND `ExpenseItem`=recurring.item_name
      AND `StartDate`=recurring.start_date
  );

UPDATE `tbluser`
SET `DefaultCurrency`='USD',
    `DefaultCategoryId`=@food
WHERE `ID`=@user_id;
