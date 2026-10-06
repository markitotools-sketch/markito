<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

/*
|--------------------------------------------------------------------------
| Companies
|--------------------------------------------------------------------------
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_companies')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_companies` (\n            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `uuid` VARCHAR(36) NOT NULL,\n            `name` VARCHAR(150) NOT NULL,\n            `code` VARCHAR(50) NOT NULL,\n            `type` ENUM('master','child') NOT NULL DEFAULT 'child',\n            `base_url` VARCHAR(255) DEFAULT NULL,\n            `active` TINYINT(1) NOT NULL DEFAULT 1,\n            `created_at` DATETIME NOT NULL,\n            PRIMARY KEY (`id`),\n            UNIQUE KEY `uuid` (`uuid`),\n            UNIQUE KEY `code` (`code`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}

/*
|--------------------------------------------------------------------------
| Branches
|--------------------------------------------------------------------------
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_branches')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_branches` (\n            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `uuid` VARCHAR(36) NOT NULL,\n            `company_id` INT UNSIGNED NOT NULL,\n            `name` VARCHAR(150) NOT NULL,\n            `code` VARCHAR(50) NOT NULL,\n            `active` TINYINT(1) NOT NULL DEFAULT 1,\n            `created_at` DATETIME NOT NULL,\n            PRIMARY KEY (`id`),\n            UNIQUE KEY `uuid` (`uuid`),\n            KEY `company_id` (`company_id`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}

/*
|--------------------------------------------------------------------------
| Connections (backend only - not exposed in the SyncHub menu)
|--------------------------------------------------------------------------
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_connections')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_connections` (\n            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `company_id` INT UNSIGNED NOT NULL,\n            `remote_url` VARCHAR(255) NOT NULL,\n            `api_key` VARCHAR(255) NOT NULL,\n            `api_secret` VARCHAR(255) NOT NULL,\n            `active` TINYINT(1) NOT NULL DEFAULT 1,\n            `created_at` DATETIME NOT NULL,\n            PRIMARY KEY (`id`),\n            KEY `company_id` (`company_id`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}

/*
|--------------------------------------------------------------------------
| Entity Mapping
|--------------------------------------------------------------------------
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_entity_map')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_entity_map` (\n            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `entity_type` VARCHAR(50) NOT NULL,\n            `local_id` BIGINT UNSIGNED NOT NULL,\n            `remote_id` BIGINT UNSIGNED DEFAULT NULL,\n            `company_id` INT UNSIGNED NOT NULL,\n            `branch_id` INT UNSIGNED DEFAULT NULL,\n            `remote_company_id` INT UNSIGNED DEFAULT NULL,\n            `sync_uuid` VARCHAR(36) NOT NULL,\n            `created_at` DATETIME NOT NULL,\n            `updated_at` DATETIME DEFAULT NULL,\n            PRIMARY KEY (`id`),\n            UNIQUE KEY `sync_uuid` (`sync_uuid`),\n            KEY `entity_local` (`entity_type`,`local_id`),\n            KEY `remote_id` (`remote_id`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}

/*
|--------------------------------------------------------------------------
| Queue
|--------------------------------------------------------------------------
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_queue')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_queue` (\n            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `event_id` VARCHAR(36) NOT NULL,\n            `entity_type` VARCHAR(50) NOT NULL,\n            `entity_id` BIGINT UNSIGNED NOT NULL,\n            `action` ENUM('create','update','delete') NOT NULL,\n            `company_id` INT UNSIGNED NOT NULL,\n            `branch_id` INT UNSIGNED DEFAULT NULL,\n            `payload` LONGTEXT DEFAULT NULL,\n            `status` ENUM('pending','processing','success','failed') NOT NULL DEFAULT 'pending',\n            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,\n            `last_error` TEXT DEFAULT NULL,\n            `created_at` DATETIME NOT NULL,\n            `updated_at` DATETIME DEFAULT NULL,\n            PRIMARY KEY (`id`),\n            UNIQUE KEY `event_id` (`event_id`),\n            KEY `entity_lookup` (`entity_type`, `entity_id`),\n            KEY `status` (`status`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}

/*
|--------------------------------------------------------------------------
| Entity Origin / Ownership Metadata
|--------------------------------------------------------------------------
|
| Keeps business origin separate from remote mapping. This is intentionally
| generic so the same mechanism can later be reused by tasks, notes,
| milestones, discussions, timers and files.
|
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_entity_origin')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_entity_origin` (\n            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `entity_type` VARCHAR(50) NOT NULL,\n            `local_id` BIGINT UNSIGNED NOT NULL,\n            `source_company_code` VARCHAR(50) NOT NULL,\n            `source_branch_id` INT UNSIGNED DEFAULT NULL,\n            `sync_uuid` VARCHAR(36) NOT NULL,\n            `created_at` DATETIME NOT NULL,\n            `updated_at` DATETIME DEFAULT NULL,\n            PRIMARY KEY (`id`),\n            UNIQUE KEY `entity_local_unique` (`entity_type`, `local_id`),\n            UNIQUE KEY `sync_uuid_unique` (`sync_uuid`),\n            KEY `source_company_code` (`source_company_code`),\n            KEY `source_branch_id` (`source_branch_id`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}


/*
|--------------------------------------------------------------------------
| Project Assignment Metadata (Master-created projects assigned to children)
|--------------------------------------------------------------------------
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_project_assignment')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_project_assignment` (\n            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `project_id` BIGINT UNSIGNED NOT NULL,\n            `sync_uuid` VARCHAR(36) NOT NULL,\n            `assigned_company_code` VARCHAR(50) NOT NULL,\n            `created_from_instance_code` VARCHAR(50) NOT NULL,\n            `created_at` DATETIME NOT NULL,\n            `updated_at` DATETIME DEFAULT NULL,\n            PRIMARY KEY (`id`),\n            UNIQUE KEY `project_id_unique` (`project_id`),\n            UNIQUE KEY `sync_uuid_unique` (`sync_uuid`),\n            KEY `assigned_company_code` (`assigned_company_code`),\n            KEY `created_from_instance_code` (`created_from_instance_code`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}


/*
|--------------------------------------------------------------------------
| Schema Upgrade: Generic Entity Types
|--------------------------------------------------------------------------
| Queue and mapping originally used ENUM(project,task). Convert them once to
| VARCHAR so milestones, notes, timers, files and discussions can reuse the
| same SyncHub engine without repeated ALTER TABLE changes.
*/

foreach ([db_prefix() . 'synchub_entity_map', db_prefix() . 'synchub_queue'] as $synchubTable) {
    if ($CI->db->table_exists($synchubTable)) {
        $field = $CI->db->query("SHOW COLUMNS FROM `{$synchubTable}` LIKE 'entity_type'")->row_array();
        if ($field && isset($field['Type']) && stripos((string)$field['Type'], 'enum(') === 0) {
            $CI->db->query("ALTER TABLE `{$synchubTable}` MODIFY `entity_type` VARCHAR(50) NOT NULL");
        }
    }
}


/*
|--------------------------------------------------------------------------
| Staff Mapping
|--------------------------------------------------------------------------
|
| Maps staff identities between this CRM and a remote SyncHub company.
| Mapping is matched by email and then persisted by local/remote staff IDs.
|
*/

if (!$CI->db->table_exists(db_prefix() . 'synchub_staff_map')) {
    $CI->db->query("\n        CREATE TABLE `" . db_prefix() . "synchub_staff_map` (\n            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n            `remote_company_code` VARCHAR(50) NOT NULL,\n            `local_staff_id` INT UNSIGNED NOT NULL,\n            `remote_staff_id` INT UNSIGNED NOT NULL,\n            `staff_email` VARCHAR(191) NOT NULL,\n            `created_at` DATETIME NOT NULL,\n            `updated_at` DATETIME DEFAULT NULL,\n            PRIMARY KEY (`id`),\n            UNIQUE KEY `remote_local_unique` (`remote_company_code`, `local_staff_id`),\n            UNIQUE KEY `remote_staff_unique` (`remote_company_code`, `remote_staff_id`),\n            KEY `staff_email` (`staff_email`)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n    ");
}
