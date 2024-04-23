<?php 

// Zugzwang Project
// deutsche-schachjugend.de
// Copyright (c) 2017-2020, 2022-2024 Gustaf Mossakowski <gustaf@koenige.org>
// Übersicht der Anmeldungen eines Landesverbandes
// Hinzufügen von Buchungen nicht möglich, Bearbeiten über lv-anmeldung.php


if (count($brick['vars']) === 1 AND strstr($brick['vars'][0], '/'))
	$brick['vars'] = explode('/', $brick['vars'][0]);

wrap_include_files('functions', 'qualification');
list($federation, $category) = mf_qualification_list($brick['vars'][2]);
if ($federation) {
	$where = sprintf('participations.federation_contact_id = %d', $federation['contact_id']);
} elseif ($category) {
	$where = sprintf('participations_categories.category_id = %d', $category['category_id']);
} else {
	wrap_quit(404);
}

wrap_include_files('anmeldung', 'custom');
my_pruefe_meldunglv_rechte($brick['vars'][0].'/'.$brick['vars'][1], $brick['vars'][2]);

$sql = 'SELECT participations.participation_id
	FROM participations
	JOIN events USING (event_id)
	LEFT JOIN categories series
		ON events.series_category_id = series.category_id
	LEFT JOIN usergroups USING (usergroup_id)
	LEFT JOIN participations_categories
		ON participations_categories.participation_id = participations.participation_id
		AND participations_categories.type_category_id = /*_ID CATEGORIES participations/registration _*/
	WHERE IFNULL(events.event_year, YEAR(events.date_begin)) = %d
	AND (series.main_category_id = %d OR series.category_id = %d)
	AND events.offen = "nein"
	AND (ISNULL(usergroups.parameters) OR usergroups.parameters NOT LIKE "%%&present=0%%")
	AND %s
	ORDER BY series.sequence';

$sql = sprintf($sql
	, $brick['vars'][0]
	, $brick['data']['series_category_id']
	, $brick['data']['series_category_id']
	, $where
);
$participation_ids = wrap_db_fetch($sql, '_dummy_', 'single value');

$zz = zzform_include('anmeldungen');

if ($participation_ids) {
	$zz['sql'] .= sprintf(' WHERE participation_id IN (%s)', implode(',', $participation_ids));
} else {
	$zz['explanation'] = '<p class="error">Es wurde noch niemand angemeldet.</p>';
	$zz['sql'] .= ' WHERE participation_id = 0';
}

// Anreisetag, Ankunftszeit, Anreise mit, Abreisetag, person_id, participation_id
// tn_name = Person, Sonstiges
$keep_fields = [1, 32, 4, 15, 16, 25, 11, 28, 19, 21, 22, 80];
foreach (array_keys($zz['fields']) as $no) {
	if (!in_array($no, $keep_fields)) unset($zz['fields'][$no]);
}

// Termin
$zz['fields'][32]['type'] = 'write_once'; 
$zz['fields'][32]['type_detail'] = 'date'; 

// tn_name
$zz['fields'][4]['type'] = 'write_once'; 
$zz['fields'][4]['hide_in_list'] = true; 

// person_id
$zz['fields'][28]['type'] = 'write_once';

// participation_id
$zz['fields'][19]['class'] = '';
$zz['fields'][19]['hide_in_form'] = true;
$zz['fields'][19]['hide_in_list'] = true; 

// Anreise mit
$zz['fields'][15]['list_append_next'] = true;
$zz['fields'][16]['list_prefix'] = '; ';
$zz['fields'][16]['list_append_next'] = true;
$zz['fields'][25]['list_prefix'] = '; ';
$zz['fields'][25]['hide_in_list'] = false;

// Anmerkungen
$zz['fields'][21]['hide_in_list'] = false;


$zz['fields'][80] = zzform_include('buchungen');
$zz['fields'][80]['fields'][20]['type'] = 'foreign_key';
$zz['fields'][80]['type'] = 'subtable';
$zz['fields'][80]['subselect']['sql'] = 'SELECT registration_id, buchung, betrag
	FROM buchungen';

$zz['fields'][81]['type'] = 'display';
$zz['fields'][81]['field_name'] = 'summe';
$zz['fields'][81]['type_detail'] = 'number';
$zz['fields'][81]['number_type'] = 'currency';
$zz['fields'][81]['exclude_from_search'] = true;
$zz['fields'][81]['unit'] = '€';
$zz['fields'][81]['sum'] = true;

// add: Buchungen dazu, abhängig von der usergroup_id und event_id

$zz['list']['group'] = 'event_id';
$zz['list']['tfoot'] = true;

$zz['export'] = [];
$zz['record']['add'] = false;
$zz['record']['delete'] = false;
$zz['record']['edit'] = false;
$zz['page']['dont_show_title_as_breadcrumb'] = true;

$zz['details'][0]['title'] = 'Bearbeiten';
$zz['details'][0]['link'] = [
	'field1' => 'participation_id',
	'string1' => '/'
];

$zz['page']['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $federation['breadcrumb'] ?? $category['category']);
$zz['page']['breadcrumbs'][]['title'] = 'Anmeldungen';

$title = $federation['title'] ?? $category['title'];
$zz['title'] = '<a href="../">'.$title.'</a>: Anmeldungen
	<br><a href="../../">'.$brick['data']['event'].' '.wrap_date($brick['data']['duration']).'</a> <em>in '.$brick['data']['place'].'</em>';

$zz['page']['referer'] = '../';
