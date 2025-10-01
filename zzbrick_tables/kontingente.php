<?php 

/**
 * qualification module
 * database table `kontingente`
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017, 2019-2021, 2023-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


$zz['title'] = 'Kontingente';
$zz['table'] = 'kontingente';

$zz['fields'][1]['title'] = 'ID';
$zz['fields'][1]['field_name'] = 'kontingent_id';
$zz['fields'][1]['type'] = 'id';

$zz['fields'][4]['field_name'] = 'event_id';
$zz['fields'][4]['type'] = 'select';
$zz['fields'][4]['sql'] = 'SELECT event_id, event, IFNULL(event_year, YEAR(date_begin))
	FROM events
	WHERE ISNULL(events.main_event_id)
	ORDER BY event';
$zz['fields'][4]['display_field'] = 'event';

$zz['fields'][3]['title'] = 'Verband';
$zz['fields'][3]['field_name'] = 'federation_contact_id';
$zz['fields'][3]['type'] = 'select';
$zz['fields'][3]['sql'] = 'SELECT contacts.contact_id
		, IFNULL(contact_short, contact) AS contact
		, contacts_identifiers.identifier AS zps_code
	FROM contacts
	LEFT JOIN contacts_identifiers
		ON contacts_identifiers.contact_id = contacts.contact_id
		AND contacts_identifiers.current = "yes"
	WHERE contacts.contact_category_id = /*_ID categories contact/federation _*/
	AND SUBSTRING(contacts_identifiers.identifier, -2) = "00"
	ORDER BY contacts_identifiers.identifier, contact_abbr';
$zz['fields'][3]['search'] = 'contact_short';
$zz['fields'][3]['display_field'] = 'contact';

$zz['fields'][5]['title'] = 'Kategorie';
$zz['fields'][5]['field_name'] = 'kontingent_category_id';
$zz['fields'][5]['type'] = 'select';
$zz['fields'][5]['sql'] = 'SELECT category_id, category, main_category_id
	FROM categories
	ORDER BY sequence, category';
$zz['fields'][5]['display_field'] = 'category';
$zz['fields'][5]['show_hierarchy'] = 'main_category_id';
$zz['fields'][5]['show_hierarchy_subtree'] = wrap_category_id('kontingente');

$zz['fields'][2]['title_tab'] = 'K.';
$zz['fields'][2]['field_name'] = 'kontingent';
$zz['fields'][2]['sum'] = true;
$zz['fields'][2]['default'] = 1;

$zz['fields'][6]['field_name'] = 'anmerkung';
$zz['fields'][6]['hide_in_list_if_empty'] = true;

$zz['fields'][99]['field_name'] = 'last_update';
$zz['fields'][99]['type'] = 'timestamp';
$zz['fields'][99]['hide_in_list'] = true;

$zz['sql'] = 'SELECT kontingente.*
		, IFNULL(contacts.contact_short, contacts.contact) AS contact
		, CONCAT(events.event, " ", IFNULL(event_year, YEAR(date_begin))) AS event
		, categories.category
	FROM kontingente
	LEFT JOIN contacts
		ON kontingente.federation_contact_id = contacts.contact_id
	LEFT JOIN events USING (event_id)
	LEFT JOIN categories
		ON categories.category_id = kontingente.kontingent_category_id
';
$zz['sqlorder'] = ' ORDER BY kontingent_id';

$zz['list']['tfoot'] = true;

$zz['subtitle']['event_id']['sql'] = 'SELECT event_id, event
	, CONCAT(events.date_begin, IFNULL(CONCAT("/", events.date_end), "")) AS duration
	FROM events';
$zz['subtitle']['event_id']['var'] = ['event', 'duration'];
$zz['subtitle']['event_id']['format'][1] = 'wrap_date';
$zz['subtitle']['event_id']['link'] = '../';
$zz['subtitle']['event_id']['link_no_append'] = true;

$zz['record']['copy'] = true;
