<?php 

/**
 * qualification module
 * form: Kontingente für ein Turnier
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


// Turnier oder Reihe?
if (empty($brick['data']['turnierform'])) {
	$sql = 'SELECT event_id
		FROM events
		LEFT JOIN categories
			ON events.series_category_id = categories.category_id
		WHERE IFNULL(event_year, YEAR(date_begin)) = %d
		AND categories.main_category_id = %d';
	$sql = sprintf($sql, $brick['vars'][0], $brick['data']['series_category_id']);
	$event_ids = wrap_db_fetch($sql, 'event_id', 'single value');
}
if (empty($event_ids)) {
	$event_ids[] = $brick['data']['event_id'];
}

$zz = zzform_include_table('kontingente');

if (!wrap_access('qualification_quota_edit')) $zz['access'] = 'none';

$zz['sql'] .= sprintf(' WHERE event_id IN (%s)', implode(',', $event_ids));

$zz['fields'][4]['sql'] = sprintf('SELECT event_id, event, IFNULL(event_year, YEAR(date_begin))
	FROM events
	WHERE event_id IN (%s)
	ORDER BY event', implode(',', $event_ids));

if (empty($brick['data']['turnierform'])) {
	$zz['filter'][1]['title'] = 'Termin';
	$zz['filter'][1]['type'] = 'list';
	$zz['filter'][1]['where'] = 'event_id';
	$zz['filter'][1]['field_name'] = 'event_id';
	$zz['filter'][1]['sql'] = sprintf('SELECT event_id
			, CONCAT(event, " ", IFNULL(event_year, YEAR(date_begin))) AS event
		FROM kontingente
		JOIN events USING (event_id)
		LEFT JOIN categories
			ON events.series_category_id = categories.category_id
		WHERE event_id IN (%s)
		ORDER BY categories.sequence, event', implode(',', $event_ids));
}

$zz['filter'][2]['title'] = 'Kategorie';
$zz['filter'][2]['type'] = 'list';
$zz['filter'][2]['where'] = 'kontingent_category_id';
$zz['filter'][2]['field_name'] = 'kontingent_category_id';
$zz['filter'][2]['sql'] = sprintf('SELECT category_id
		, category
	FROM kontingente
	JOIN categories
		ON categories.category_id = kontingente.kontingent_category_id
	WHERE event_id IN (%s)
	ORDER BY sequence', implode(',', $event_ids));

$zz['filter'][3]['title'] = 'LV';
$zz['filter'][3]['type'] = 'list';
$zz['filter'][3]['where'] = 'federation_contact_id';
$zz['filter'][3]['field_name'] = 'federation_contact_id';
$zz['filter'][3]['sql'] = sprintf('SELECT federation_contact_id
		, contact_abbr
	FROM kontingente
	JOIN contacts
		ON kontingente.federation_contact_id = contacts.contact_id
	WHERE event_id IN (%s)
	ORDER BY contact_abbr', implode(',', $event_ids));

$zz_conf['dont_show_title_as_breadcrumb'] = true;
$zz_conf['breadcrumbs'][] = ['linktext' => $zz['title']];

$zz['title'] = 'Vergabe von Kontingenten<br><a href="../">'.$brick['data']['event'].' '.wrap_date($brick['data']['duration']).'</a> <em>in '.$brick['data']['turnierort'].'</em>';

$zz['explanation'] = '<p>Hinweis zur 2. Freiplatzrunde: erst hier Kontingent eintragen mit Namen, dann können Bewerberinnen und Bewerber in Spielerinnen und Spieler geändert werden.</p>';

$zz['if']['list_empty']['explanation'] = '<p>Keine Lust auf tippen und klicken? – <a href="../kontingente-kopieren/">Kontingente von vergangenem Termin kopieren</a></p>';
