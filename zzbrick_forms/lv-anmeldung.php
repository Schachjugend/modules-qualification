<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2017, 2019-2024 Gustaf Mossakowski <gustaf@koenige.org>
// Skript: Kontaktdaten einer Person eines Teams eines Turniers


if (count($brick['vars']) === 1 AND strstr($brick['vars'][0], '/'))
	$brick['vars'] = explode('/', $brick['vars'][0]);

wrap_include('functions', 'clubs');
$lv = mf_clubs_federation($brick['vars'][2]);
if (!$lv) wrap_quit(404);

wrap_include('anmeldung', 'custom');
$access = my_pruefe_meldunglv_rechte($brick['vars'][0].'/'.$brick['vars'][1], $brick['vars'][2]);

$sql = 'SELECT participation_id
		, SUBSTRING_INDEX(events.identifier, "/", 1) AS year
		, SUBSTRING_INDEX(events.identifier, "/", -1) AS identifier
	FROM participations
	JOIN events USING (event_id)
	LEFT JOIN categories series
		ON events.series_category_id = series.category_id
	WHERE IFNULL(events.event_year, YEAR(events.date_begin)) = %d
	AND (series.main_category_id = %d OR series.category_id = %d)
	AND participations.federation_contact_id = %d
	ORDER BY series.sequence';
//	AND events.offen = "nein"
$sql = sprintf($sql, $brick['vars'][0], $brick['data']['series_category_id'], $brick['data']['series_category_id'], $lv['contact_id']);
$participation_ids = wrap_db_fetch($sql, 'participation_id');

if (!in_array($brick['vars'][3], array_keys($participation_ids))) wrap_quit(403);
$event = my_event($participation_ids[$brick['vars'][3]]['year'], $participation_ids[$brick['vars'][3]]['identifier']);

// Kosten von Hauptreihe (bei DEM)?
$event['series_event_id'] = $brick['data']['event_id'];

// existiert Anmeldung?, Daten aus Teilnahmen auslesen
$sql = 'SELECT participation_id, persons.person_id, registration_id, participations.event_id
		, events.date_begin, events.date_end
		, contact
		, usergroup
	FROM participations
	LEFT JOIN events USING (event_id)
	LEFT JOIN persons USING (contact_id)
	LEFT JOIN contacts USING (contact_id)
	LEFT JOIN usergroups USING (usergroup_id)
	LEFT JOIN registrations USING (participation_id)
	WHERE participation_id = %d';
$sql = sprintf($sql, $brick['vars'][3]);
$teilnahme = wrap_db_fetch($sql);

$zz = zzform_include('anmeldungen');

$zz['access'] = 'add_then_edit';
if (!$access) $zz['access'] = 'show';
if ($teilnahme['registration_id']) {
	$zz['where']['registration_id'] = $teilnahme['registration_id'];
	$zz['where']['participation_id'] = $brick['vars'][3];
}

// Anreisetag, Ankunftszeit, Anreise mit, Abreisetag, person_id, participation_id
// tn_name = Person, Sonstiges
$keep_fields = [1, 32, 4, 15, 16, 25, 11, 28, 19, 21, 22];
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $keep_fields)) unset($zz['fields'][$no]);
}

// person_id
$zz['fields'][28]['type'] = 'write_once';
$zz['fields'][28]['type_detail'] = 'select';
if (!$teilnahme['registration_id']) {
	$zz['fields'][28]['type'] = 'hidden';
	$zz['fields'][28]['value'] = $teilnahme['person_id'];
}

// Termin
$zz['fields'][32]['hide_in_form'] = true;
$zz['fields'][32]['type'] = 'write_once'; 
$zz['fields'][32]['type_detail'] = 'select';
if (!$teilnahme['registration_id']) {
	$zz['fields'][32]['type'] = 'hidden';
	$zz['fields'][32]['value'] = $teilnahme['event_id'];
}

$zz['fields'][15]['default'] = $teilnahme['date_begin'];
$zz['fields'][11]['default'] = $teilnahme['date_end'];
$zz['fields'][16]['explanation'] = false; // Ankunftszeit ohne Tag! 

// tn_name
$zz['fields'][4]['type'] = 'write_once'; 
if (!$teilnahme['registration_id']) {
	$zz['fields'][4]['type'] = 'hidden';
	$zz['fields'][4]['value'] = $teilnahme['contact'];
}
$zz['fields'][4]['hide_in_form'] = true;

// $zz['fields'][19] = []; // participation_id
$zz['fields'][19]['class'] = '';
$zz['fields'][19]['hide_in_form'] = true;
$zz['fields'][19]['type'] = 'hidden';
$zz['fields'][19]['value'] = $teilnahme['participation_id'];

$zz['fields'][22]['hide_in_form'] = true; // Stand

// add: Buchungen dazu, abhängig von der usergroup_id und event_id
$product_areas = my_formkit_products($event, $teilnahme['usergroup']);
if ($product_areas) {
	$i = 80; // Feldnummern, fortlaufend 80, 81, 82
	foreach ($product_areas as $area => $products) {
		$zz['fields'][$i] = my_formkit_products_field(array_keys($products), $area, $event);
		// participation_id ergänzen
		$zz['fields'][$i]['fields'][17]['type'] = 'hidden';
		$zz['fields'][$i]['fields'][17]['value'] = $teilnahme['participation_id'];
		$zz['fields'][$i]['sql'] .= sprintf(' AND participation_id = %d', $teilnahme['participation_id']);
		$i++;
	}
	$zz['hooks']['before_upload'][] = 'my_buchung';
}
unset($zz['hooks']['before_insert']); // my_anmeldung_check

$zz['page']['dont_show_title_as_breadcrumb'] = true;

$zz['page']['breadcrumbs'][] = sprintf('<a href="../../">%s</a>', $lv['federation_short']);
$zz['page']['breadcrumbs'][]['title'] = 'Buchung';

$zz['title'] = '<a href="../../">Landesverband '.$lv['federation_short'].'</a>: Anmeldung
	<br><a href="../../../">'.$brick['data']['event'].' '.wrap_date($brick['data']['duration']).'</a> <em>in '.$brick['data']['place'].'</em>';

$zz['page']['referer'] = '../../';
