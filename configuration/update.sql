/**
 * qualification module
 * SQL updates
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/contacts
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */

/* 2024-04-15-1 */	UPDATE webpages SET content = REPLACE(content, '%%% request kontingente_kopieren ', '%%% make kontingente_kopieren ') WHERE content LIKE '%\%\%\% request kontingente_kopieren %';
