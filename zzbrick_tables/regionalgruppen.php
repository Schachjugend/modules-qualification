<?php 

/**
 * qualification module
 * Skript: Regionalgruppen
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2012-2013, 2016, 2018-2021, 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Regionalgruppen für DVM-Qualifikationsturniere';
$zz['table'] = 'regionalgruppen';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'regionalgruppe_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][4]['title'] = 'Reihe';
$zz['fields'][4]['field_name'] = 'series_category_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = sprintf('SELECT category_id, category_short, path
	FROM categories
	WHERE main_category_id = %d
	AND NOT ISNULL(category_short)
	ORDER BY category_short', wrap_category_id('reihen'));
$zz['fields'][4]['sql_ignore'] = ['path'];
$zz['fields'][4]['key_field_name'] = 'category_id';
$zz['fields'][4]['display_field'] = 'category_short';
$zz['fields'][4]['search'] = 'category_short';

$zz['fields'][2]['field_name'] = 'regionalgruppe';

$zz['fields'][3]['title'] = 'Landesverband';
$zz['fields'][3]['field_name'] = 'federation_contact_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = sprintf('SELECT contacts.contact_id, contact
		, contacts_identifiers.identifier AS zps_code
	FROM contacts
	LEFT JOIN contacts_identifiers
		ON contacts_identifiers.contact_id = contacts.contact_id
		AND contacts_identifiers.current = "yes"
	WHERE mother_contact_id = %d
	AND contact_category_id = %d
	ORDER BY contacts_identifiers.identifier'
	, $zz_setting['contact_ids']['dsb']
	, wrap_category_id('contact/federation')
);
$zz['fields'][3]['id_field_name'] = 'contacts.contact_id';
$zz['fields'][3]['display_field'] = 'contact';
$zz['fields'][3]['sql_fieldnames_ignore'] = ['contacts.contact_id'];

$zz['fields'][20]['field_name'] = 'last_update';
$zz['fields'][20]['type'] = 'timestamp';
$zz['fields'][20]['hide_in_list'] = true;

$zz['sql'] = 'SELECT regionalgruppen.*
		, contact
		, category_short
	FROM regionalgruppen
	LEFT JOIN contacts
		ON regionalgruppen.federation_contact_id = contacts.contact_id
	LEFT JOIN categories
		ON regionalgruppen.series_category_id = categories.category_id
';
$zz['sqlorder'] = ' ORDER BY regionalgruppe, contact';

if (!wrap_access('qualification_regional_groups_edit')) $zz['access'] = 'show';
