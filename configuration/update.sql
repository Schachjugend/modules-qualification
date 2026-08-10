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
/* 2026-08-10-1 */	UPDATE usergroups SET parameters = REPLACE(parameters, '&reihenfolge=1', '') WHERE parameters LIKE '%&reihenfolge=1%';
/* 2026-08-10-2 */	UPDATE usergroups SET parameters = REPLACE(parameters, '&reihenfolge=2', '') WHERE parameters LIKE '%&reihenfolge=2%';
/* 2026-08-10-3 */	UPDATE usergroups SET parameters = REPLACE(parameters, '&reihenfolge=3', '') WHERE parameters LIKE '%&reihenfolge=3%';
/* 2026-08-10-4 */	UPDATE usergroups SET parameters = REPLACE(parameters, '&reihenfolge=4', '') WHERE parameters LIKE '%&reihenfolge=4%';
