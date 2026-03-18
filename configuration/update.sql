/**
 * qualification module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/contacts
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024, 2026 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/* 2024-04-15-1 */	UPDATE webpages SET content = REPLACE(content, '%%% request kontingente_kopieren ', '%%% make kontingente_kopieren ') WHERE content LIKE '%\%\%\% request kontingente_kopieren %';
/* 2026-03-18-1 */	DELETE FROM _settings WHERE setting_key = 'qualification_regional_groups_path';
/* 2026-03-18-2 */	DELETE FROM _settings WHERE setting_key = 'qualification_quota_copy_path';
