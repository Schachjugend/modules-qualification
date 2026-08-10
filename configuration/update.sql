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
/* 2026-08-10-5 */	UPDATE categories SET parameters = REPLACE(parameters, '&alternate=', '&qualification_alternate_series=') WHERE parameters LIKE '%&alternate=%';
/* 2026-08-10-6 */	UPDATE categories SET parameters = REPLACE(parameters, '&younger_alternate=', '&qualification_younger_series_alternate=') WHERE parameters LIKE '%&younger_alternate=%';
/* 2026-08-10-7 */	UPDATE categories SET parameters = REPLACE(parameters, '&younger=', '&qualification_younger_series=') WHERE parameters LIKE '%&younger=%';
/* 2026-08-10-8 */	UPDATE categories SET parameters = REPLACE(parameters, '&lvmeldung=', '&qualification_federation_registration=') WHERE parameters LIKE '%&lvmeldung=%';
/* 2026-08-10-9 */	UPDATE categories SET parameters = REPLACE(parameters, '&distribution=', '&qualification_place_distribution=') WHERE parameters LIKE '%&distribution=%';
/* 2026-08-10-10 */	UPDATE categories SET parameters = REPLACE(parameters, '&kontingent=', '&qualification_quota=') WHERE parameters LIKE '%&kontingent=%';
/* 2026-08-10-11 */	UPDATE categories SET parameters = REPLACE(parameters, '&quotadem=', '&qualification_quota_dem=') WHERE parameters LIKE '%&quotadem=%';
/* 2026-08-10-12 */	UPDATE categories SET parameters = REPLACE(parameters, '&quotadvm=', '&qualification_quota_dvm=') WHERE parameters LIKE '%&quotadvm=%';
/* 2026-08-10-13 */	UPDATE contacts SET parameters = REPLACE(parameters, '&regional_groups=', '&qualification_regional_groups=') WHERE parameters LIKE '%&regional_groups=%';
/* 2026-08-10-14 */	UPDATE webpages SET parameters = REPLACE(parameters, '&access=qualification_quotacalc_dem', '&access=qualification_quota_dem') WHERE parameters LIKE '%&access=qualification_quotacalc_dem%';
/* 2026-08-10-15 */	UPDATE webpages SET parameters = REPLACE(parameters, '&access=qualification_quotacalc_dvm', '&access=qualification_quota_dvm') WHERE parameters LIKE '%&access=qualification_quotacalc_dvm%';
