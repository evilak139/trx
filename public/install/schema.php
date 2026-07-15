<?php

declare(strict_types=1);

/**
 * 建表 SQL 模板，{prefix} 会在安装向导执行时替换为用户填写的表前缀。
 * 表结构以需求设计文档第六章为准；admins 表额外增加 failed_attempts /
 * locked_until 两列，用于实现"5次失败锁定10分钟"的登录限流要求。
 *
 * @return array<string, string>
 */
return [
    'config' => "CREATE TABLE IF NOT EXISTS `{prefix}config` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `site_title` VARCHAR(100) NOT NULL DEFAULT 'TRX能量兑换',
  `logo_path` VARCHAR(255) DEFAULT NULL,
  `customer_service_url` VARCHAR(255) DEFAULT NULL,
  `receive_address` VARCHAR(64) NOT NULL,
  `service_html` TEXT COMMENT '服务说明',
  `steps_html` TEXT COMMENT '使用步骤',
  `notice_html` TEXT COMMENT '重要提示',
  `disclaimer_html` TEXT COMMENT '免责声明',
  `copy_tip_text` TEXT COMMENT '复制成功后弹窗提示文案，为空时前端使用默认文案',
  `trongrid_api_key` VARCHAR(255) DEFAULT NULL COMMENT 'TronGrid API Key，选填，用于提高查询额度',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'admins' => "CREATE TABLE IF NOT EXISTS `{prefix}admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'address_history' => "CREATE TABLE IF NOT EXISTS `{prefix}address_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(64) NOT NULL,
  `enabled_at` DATETIME NOT NULL,
  `disabled_at` DATETIME DEFAULT NULL,
  `operator` VARCHAR(50) DEFAULT NULL COMMENT '操作的管理员用户名',
  INDEX `idx_address` (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'copy_events' => "CREATE TABLE IF NOT EXISTS `{prefix}copy_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(64) NOT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_address_created` (`address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'transfer_records' => "CREATE TABLE IF NOT EXISTS `{prefix}transfer_records` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `address` VARCHAR(64) NOT NULL,
  `tx_hash` VARCHAR(80) NOT NULL,
  `from_address` VARCHAR(64) NOT NULL,
  `amount` DECIMAL(20,6) NOT NULL,
  `tx_timestamp` DATETIME NOT NULL,
  `fetched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_tx_hash` (`tx_hash`),
  INDEX `idx_address_tx_time` (`address`, `tx_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'faqs' => "CREATE TABLE IF NOT EXISTS `{prefix}faqs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `question` VARCHAR(255) NOT NULL,
  `answer` TEXT NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
