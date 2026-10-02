CREATE DATABASE IF NOT EXISTS `detsdb`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `detsdb`;

CREATE TABLE IF NOT EXISTS `tbluser` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FullName` varchar(120) NOT NULL,
  `MobileNumber` varchar(20) NOT NULL,
  `Email` varchar(120) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `DefaultCurrency` varchar(10) NOT NULL DEFAULT 'USD',
  `DefaultCategoryId` int(11) DEFAULT NULL,
  `RegDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uk_email` (`Email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tblexpense` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `ExpenseDate` date NOT NULL,
  `ExpenseItem` varchar(150) NOT NULL,
  `ExpenseCost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Currency` varchar(10) NOT NULL DEFAULT 'USD',
  `CategoryId` int(11) DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `ReceiptPath` varchar(255) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_userid` (`UserId`),
  KEY `idx_userid_date` (`UserId`, `ExpenseDate`),
  KEY `idx_userid_currency` (`UserId`, `Currency`),
  KEY `idx_categoryid` (`CategoryId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tblitems` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `ItemName` varchar(150) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_userid` (`UserId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tblcategories` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `CategoryName` varchar(100) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_userid` (`UserId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tblbudgets` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `CategoryId` int(11) NOT NULL,
  `BudgetMonth` char(7) NOT NULL,
  `Currency` varchar(10) NOT NULL DEFAULT 'USD',
  `BudgetAmount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_userid_month_currency` (`UserId`, `BudgetMonth`, `Currency`),
  KEY `idx_categoryid` (`CategoryId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tblrecurring` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) NOT NULL,
  `ExpenseItem` varchar(150) NOT NULL,
  `ExpenseCost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `Currency` varchar(10) NOT NULL DEFAULT 'USD',
  `CategoryId` int(11) NOT NULL,
  `Notes` text DEFAULT NULL,
  `Frequency` varchar(20) NOT NULL DEFAULT 'monthly',
  `StartDate` date NOT NULL,
  `NextRunDate` date NOT NULL,
  `LastRunDate` date DEFAULT NULL,
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_userid_nextrun` (`UserId`, `NextRunDate`),
  KEY `idx_categoryid` (`CategoryId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
