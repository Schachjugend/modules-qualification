<?php 

/**
 * qualification module
 * quota calculation for German Youth Team Championships (DVM)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Falco Nogatz <fnogatz@gmail.com>
 * @copyright Copyright © 2013, 2016-2023 Gustaf Mossakowski
 * @copyright Copyright © 2016 Falco Nogatz
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Übersicht Kontingente
 *
 * @param array $vars
 */
function mod_qualification_quotacalcdvm($vars, $settings, $event) {
	if (count($vars) !== 2) return false;
	wrap_package_activate('tournaments'); // for CSS, class .results

	$sql = 'SELECT categories.category_id, categories.category AS series
			, categories.category_short AS series_short
			, SUBSTRING_INDEX(categories.category_short, " ", -1) AS altersklasse
			, (SELECT category FROM events
				JOIN tournaments USING (event_id)
				JOIN categories turnierformen
					ON tournaments.turnierform_category_id = turnierformen.category_id
				WHERE events.series_category_id = categories.category_id
				LIMIT 1
			) AS turnierform
			, main_series.category_short AS main_series_short
			, SUBSTRING_INDEX(main_series.path, "/", -1) AS main_series_path
			, categories.parameters
		FROM categories
		LEFT JOIN categories main_series
			ON main_series.category_id = categories.main_category_id
		WHERE categories.path LIKE "reihen%%/%s"';
	$sql = sprintf($sql, wrap_db_escape($vars[1]));
	$data = wrap_db_fetch($sql);
	if (!$data) return false;
	parse_str($data['parameters'], $parameter);
	$data += $parameter;
	if (empty($parameter['kontingent'])) return false;

	$data['year'] = $vars[0];

	$events = cms_kontingent_termine($data);
	if (!$events) wrap_quit(404, sprintf('Für das Jahr %d liegen (noch) keine Daten für die %s vor.', $data['year'], $data['series']));
	return cms_kontingent_mannschaft($data, $events);
}

function cms_kontingent_termine($data) {
	$sql = 'SELECT event_id
			, events.identifier
			, events.event
			, turnierformen.category AS turnierform
			, (SELECT IFNULL(landesverbaende.contact_id, mutterverbaende.contact_id)
				FROM events_contacts
				JOIN contacts
					ON events_contacts.contact_id = contacts.contact_id
					AND events_contacts.role_category_id = %d
				LEFT JOIN contacts_identifiers ok
					ON contacts.contact_id = ok.contact_id
				LEFT JOIN contacts_identifiers lvk
					ON CONCAT(SUBSTRING(ok.identifier, 1, 1), "00") = lvk.identifier
				LEFT JOIN contacts landesverbaende
					ON landesverbaende.contact_id = lvk.contact_id
				LEFT JOIN contacts mutterverbaende
					ON contacts.mother_contact_id = mutterverbaende.contact_id
				WHERE events_contacts.event_id = events.event_id
				LIMIT 1) AS federation_contact_id
			, IFNULL(event_year, YEAR(date_begin)) AS year
			, series.path AS series_path
			, (SELECT COUNT(*) FROM events_websites WHERE events_websites.event_id = events.event_id) AS veroeffentlicht
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories turnierformen
			ON tournaments.turnierform_category_id = turnierformen.category_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE (series.main_category_id = %d OR series.category_id = %d)
		AND IFNULL(event_year, YEAR(date_begin)) IN (%s)
		AND series.parameters LIKE "%%&kontingent=1%%"
		AND NOT ISNULL(tournament_id)
		HAVING veroeffentlicht > 0
		ORDER BY series.sequence, IFNULL(event_year, YEAR(date_begin))';
	$sql = sprintf($sql
		, wrap_category_id('rollen/ausrichter')
		, $data['category_id'], $data['category_id'], $data['year']
	);
	$events = wrap_db_fetch($sql, 'event_id');
	return $events;
}

function cms_kontingent_mannschaft($data, $events) {
	global $zz_setting;

	$sql = 'SELECT contact_id, contact, country
			, contact_abbr, regionalgruppe
		FROM contacts
		JOIN contacts_identifiers ok USING (contact_id)
		JOIN countries USING (country_id)
		LEFT JOIN regionalgruppen
			ON regionalgruppen.federation_contact_id = contacts.contact_id
			AND series_category_id = %d
		WHERE mother_contact_id = %d AND contact_category_id = %d
		AND ok.current = "yes"
		ORDER BY country';
	$sql = sprintf($sql
		, $data['category_id']
		, $zz_setting['contact_ids']['dsb']
		, wrap_category_id('contact/federation')
	);
	$lv = wrap_db_fetch($sql, 'contact_id');
	$regionalgruppe = reset($lv);
	$regionalgruppe = !empty($regionalgruppe['regionalgruppe']) ? true : false;

	// Jahre
	$last = $data;
	$last['year'] = implode(',', [$data['year'], $data['year'] - 1, $data['year'] -2]);
	$events = cms_kontingent_termine($last);
	
	$sql = 'SELECT teams.event_id, landesverbaende.contact_id AS federation_contact_id
			, COUNT(teams.team_id) AS teams
			, GROUP_CONCAT(teams.team_id ORDER BY wertung DESC, teams.team_id SEPARATOR ",") AS team_ids
			, GROUP_CONCAT(ROUND(wertung, 1) ORDER BY wertung DESC, teams.team_id SEPARATOR ",") AS wertungen
		FROM teams
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN tabellenstaende
			ON tabellenstaende.team_id = teams.team_id
			AND tabellenstaende.runde_no = tournaments.runden
		LEFT JOIN tabellenstaende_wertungen tsw
			ON tabellenstaende.tabellenstand_id = tsw.tabellenstand_id
			AND tsw.wertung_category_id = %d
		LEFT JOIN contacts_identifiers ok
			ON teams.club_contact_id = ok.contact_id
		LEFT JOIN contacts_identifiers lvk
			ON CONCAT(SUBSTRING(ok.identifier, 1, 1), "00") = lvk.identifier
		LEFT JOIN contacts landesverbaende
			ON landesverbaende.contact_id = lvk.contact_id
		WHERE teams.event_id IN (%s)
		AND teams.team_status = "Teilnehmer"
		AND ok.current = "yes"
		AND lvk.current = "yes"
		GROUP BY event_id, landesverbaende.contact_id
	';
	$sql = sprintf($sql
		, wrap_category_id('turnierwertungen/mp')
		, implode(',', array_keys($events))
	);
	$teams = wrap_db_fetch($sql, ['event_id', 'federation_contact_id']);
	if (count($teams) !== count($events)) return false; // noch keine Teams
	
	$sql = 'SELECT team_id, CONCAT(team, IFNULL(CONCAT(" ", team_no), "")) AS team
		FROM teams WHERE event_id IN (%s)';
	$sql = sprintf($sql, implode(',', array_keys($teams)));
	$teamnamen = wrap_db_fetch($sql, '_dummy_', 'key/value');

	$data['summe'] = 0;
	$last_series = '';
	foreach ($events as $event) {
		$verbandsdaten = [];
		$summe = 0;
		foreach ($lv as $verband) {
			if ($regionalgruppe) {
				$key = $verband['regionalgruppe'];
				if (empty($verbandsdaten[$key])) {
					$verbandsdaten[$key]['teams'] = 0;
					$verbandsdaten[$key]['punkte'] = [];
					$verbandsdaten[$key]['team_ids'] = [];
				}
			} else {
				$key = $verband['contact_id'];
			}
			if (empty($teams[$event['event_id']][$verband['contact_id']])) {
				if (!$regionalgruppe) {
					$verbandsdaten[$key]['teams'] = '--';
					$verbandsdaten[$key]['punkte'] = [];
					$verbandsdaten[$key]['team_ids'] = [];
				}
			} else {
				$summe += $teams[$event['event_id']][$verband['contact_id']]['teams'];
				if ($regionalgruppe) {
					$verbandsdaten[$key]['teams'] += $teams[$event['event_id']][$verband['contact_id']]['teams'];
				} else {
					$verbandsdaten[$key]['teams'] = $teams[$event['event_id']][$verband['contact_id']]['teams'];
				}
				$verbandsdaten[$key]['punkte'] = array_merge(
					$verbandsdaten[$key]['punkte'],
					explode(',', $teams[$event['event_id']][$verband['contact_id']]['wertungen'])
				);
				$verbandsdaten[$key]['team_ids'] = array_merge(
					$verbandsdaten[$key]['team_ids'],
					explode(',', $teams[$event['event_id']][$verband['contact_id']]['team_ids'])
				);
				if (!isset($summe_regional[$key])) $summe_regional[$key] = 0;
				$summe_regional[$key] += $teams[$event['event_id']][$verband['contact_id']]['teams'];
			}
			if ($event['federation_contact_id'] == $verband['contact_id']) {
				$verbandsdaten[$key]['ausrichter'] = true;
			}
		}
		$verbandsdaten = array_values($verbandsdaten);
		foreach ($verbandsdaten as $id => $value) {
			while (count($value['punkte']) < count($value['team_ids'])) {
				$value['punkte'][] = ''; 
			}
			array_multisort($value['punkte'], SORT_DESC, SORT_NUMERIC, $value['team_ids'], SORT_ASC, SORT_NUMERIC);
			$extra = false;
			$extra_team_id = false;
			if (!empty($value['ausrichter'])) {
				$extra = array_pop($value['punkte']);
				$extra_team_id = array_pop($value['team_ids']);
				$value['teams']--;
			}
			if ($value['teams'])
				$verbandsdaten[$id]['schnitt'] = round(array_sum($value['punkte']) / $value['teams'], 3);
			foreach ($value['punkte'] as $index => $punkte) {
				if (substr($punkte, -2) === '.0') $punkte = substr($punkte, 0, -2);
				$verbandsdaten[$id]['wertungen'][$index] = [
					'punkte' => wrap_number($punkte, 'simple'),
					'team' => $teamnamen[$value['team_ids'][$index]]
				];
			}
			if ($extra) {
				if (substr($extra, -2) === '.0') $extra = substr($extra, 0, -2);
				$verbandsdaten[$id]['wertungen'][] = [
					'punkte' => wrap_number($extra, 'simple'),
					'team' => $teamnamen[$extra_team_id],
					'streichwertung' => true
				];
			}
			if ($verbandsdaten[$id]['teams']) continue;
			$verbandsdaten[$id]['teams'] = '--';
		}
		$data['turniere'][] = [
			'turnier' => $event['event'],
			'turnier_kennung' => $event['identifier'],
			'verbaende' => $verbandsdaten,
			'summe' => $summe,
			'year' => $event['year'],
			'separator' => $last_series AND $event['series_path'] !== $last_series ? true: false
		];
		$last_series = $event['series_path'];
		$data['summe'] += $summe;
	}
	foreach ($lv as $verband) {
		if ($regionalgruppe) {
			$data['verbaende'][$verband['regionalgruppe']] = [
				'verband_kurz' => $verband['regionalgruppe'],
				'summe' => isset($summe_regional[$verband['regionalgruppe']])
					? $summe_regional[$verband['regionalgruppe']] : 0
			];
		} else {
			$data['verbaende'][] = [
				'verband_kurz' => $verband['contact_abbr'],
				'verband' => $verband['contact'],
				'summe' => isset($summe_regional[$verband['contact_id']])
					? $summe_regional[$verband['contact_id']] : 0
			];
		}
	}
	$data['verbaende'] = array_values($data['verbaende']);
	
	$page['text'] = wrap_template('quota-calculation-dvm', $data);
//	$page['breadcrumbs'][] = '<a href="../">'.$data['series_short'].' '.$data['year'].'</a>';
	$page['breadcrumbs'][] = 'Kontingent';
	$page['dont_show_h1'] = true;
	$page['title'] = $data['series_short'].' '.$data['year'].', Kontingent';
	return $page;
}
