<?php 

/**
 * qualification module
 * Übersicht der Meldungen für ein Turnier
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_qualification_meldungen($vars, $settings, $data) {
	if (count($vars) !== 2) return false;
	wrap_package_activate('tournaments'); // for CSS
	
	$sql = 'SELECT kontingent_id, kontingent
			, contact_id, contact_short AS landesverband
			, contacts.identifier AS landesverband_kennung
			, events.event_id, events.event
			, events.identifier AS event_identifier
			, kontingent_category_id
			, kontingente.anmerkung
			, categories.category, categories.category_short
			, categories.sequence
		FROM kontingente
		LEFT JOIN events USING (event_id)
		LEFT JOIN contacts
			ON kontingente.federation_contact_id = contacts.contact_id
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		LEFT JOIN events main_events
			ON IFNULL(main_events.event_year, YEAR(main_events.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin))
			AND main_events.series_category_id = series.main_category_id
		LEFT JOIN categories
			ON kontingente.kontingent_category_id = categories.category_id
		WHERE main_events.event_id = %d
		ORDER BY series.sequence, series.category, contact_short, categories.sequence';
	$sql = sprintf($sql, $data['event_id']);
	$kontingente = wrap_db_fetch($sql, 'kontingent_id');
	if (!$kontingente) return false;
	
	// Sortierung nach Landesverbänden
	foreach ($kontingente as $kontingent) {
		$data['kontingente'][$kontingent['kontingent_category_id'].'k'] = [
			'category' => $kontingent['category'],
			'category_short' => $kontingent['category_short'],
			'kontingent' => 0,
			'sequence' => $kontingent['sequence']
		];
		if (empty($data['landesverbaende'][$kontingent['contact_id']])) {
			$data['landesverbaende'][$kontingent['contact_id']]['landesverband'] = $kontingent['landesverband'];
			$data['landesverbaende'][$kontingent['contact_id']]['landesverband_kennung'] = $kontingent['landesverband_kennung'];
		}
		$event_ids[$kontingent['event_id']] = $kontingent['event_id'];
	}
	array_multisort(array_column($data['kontingente'], 'sequence'), SORT_ASC, $data['kontingente']);
	foreach ($data['kontingente'] as $category_id => $kontingent) {
		$data['kontingente'][substr($category_id, 0, -1)] = $kontingent;
		unset($data['kontingente'][$category_id]);
	}
	$data['kontingente'][0] = [
		'category' => 'Summe Kontingente',
		'category_short' => '∑',
		'kontingent' => 0
	];
	foreach ($data['kontingente'] as $category_id => $kontingent) {
		foreach ($data['landesverbaende'] as $contact_id => $lv) {
			$data['landesverbaende'][$contact_id]['kontingente'][$category_id] = [
				'kontingent' => 0,
				'anmerkung' => []
			];
		}
	}

	$sql = 'SELECT usergroup_id, usergroup
			, SUBSTRING(usergroup, 1, 1) AS gruppe_abk
			, 0 AS participations
			, SUBSTRING_INDEX(SUBSTRING_INDEX(parameters, "reihenfolge=", -1), "&", 1) AS reihenfolge
			, identifier
		FROM usergroups
		LEFT JOIN usergroups_categories USING (usergroup_id)
		WHERE category_id = /*_ID categories verknuepfungen/teilnahmestatus _*/
		AND parameters LIKE "%&reihenfolge=%"
		ORDER BY reihenfolge';
	$usergroups = wrap_db_fetch($sql, 'usergroup_id');

	$event_ids[] = $data['event_id'];
	$sql = 'SELECT federation_contact_id, COUNT(*) AS participations
			, usergroup_id
		FROM participations
		WHERE event_id IN (%s)
		AND NOT ISNULL(federation_contact_id)
		AND usergroup_id IN (%s)
		GROUP BY federation_contact_id, usergroup_id
	';
	$sql = sprintf($sql
		, implode(',', $event_ids)
		, implode(',', array_keys($usergroups))
	);
	$meldungen = wrap_db_fetch($sql, ['federation_contact_id', 'usergroup_id']);

	$sql = 'SELECT federation_contact_id, SUM(betrag) AS kosten, kosten_status
		FROM buchungen
		LEFT JOIN participations USING (participation_id)
		WHERE participations.event_id IN (%s)
		AND NOT ISNULL(federation_contact_id)
		AND kosten_status != "gelöscht"
		GROUP BY federation_contact_id, kosten_status
	';
	$sql = sprintf($sql, implode(',', $event_ids));
	$buchungen = wrap_db_fetch($sql, ['federation_contact_id', 'kosten_status']);

	foreach ($kontingente as $lv) {
		foreach ($data['kontingente'] as $category_id => $category) {
			if ($lv['kontingent_category_id'].'' !== $category_id.'') continue;
			if (empty($lv['kontingent'])) continue;
			$data['landesverbaende'][$lv['contact_id']]['kontingente'][$category_id]['kontingent'] += $lv['kontingent'];
			if ($lv['anmerkung'])
				$data['landesverbaende'][$lv['contact_id']]['kontingente'][$category_id]['anmerkung'][]['anmerkung'] = $lv['anmerkung'];
			$data['landesverbaende'][$lv['contact_id']]['kontingente'][0]['kontingent'] += $lv['kontingent'];
			$data['kontingente'][$category_id]['kontingent'] += $lv['kontingent'];
			$data['kontingente'][0]['kontingent'] += $lv['kontingent'];
		}
	}

	foreach ($usergroups as $usergroup_id => $usergroup) {
		$data['participations'][$usergroup_id] = $usergroup;
		foreach (array_keys($data['landesverbaende']) as $federation_contact_id) {
			$data['landesverbaende'][$federation_contact_id]['participations'][$usergroup_id] = [
				'usergroup' => $usergroup['identifier'], 'participations' => 0
			];
		}
	}
	$data['participations'][0] = [
		'usergroup' => 'Summe Meldungen',
		'gruppe_abk' => '∑',
		'participations' => 0,
		'reihenfolge' => 5
	];

	foreach (array_keys($data['landesverbaende']) as $federation_contact_id) {
		foreach ($buchungen as $lv_buchungen) {
			foreach (array_keys($lv_buchungen) as $status) {
				if (!isset($data['buchungen'][$status]['status'])) {
					$data['buchungen'][$status]['status'] = $status;
					$data['buchungen'][$status]['betrag'] = 0;
				}
				if (!isset($data['landesverbaende'][$federation_contact_id]['buchungen'][$status]))
					$data['landesverbaende'][$federation_contact_id]['buchungen'][$status] = [
						'status' => $status, 'betrag' => 0
					];
			}
		}
		$data['landesverbaende'][$federation_contact_id]['buchungen'][0] = [
			'betrag' => 0
		];
		$data['landesverbaende'][$federation_contact_id]['participations'][0] = [
			'usergroup' => 'Summe', 'participations' => 0
		];
	}
	$data['buchungen'][0] = [
		'betrag' => 0, 'status' => 'Summe'
	];
	
	foreach ($meldungen as $federation_contact_id => $lv_meldung) {
		foreach ($lv_meldung as $meldung_id => $meldung) {
			$data['landesverbaende'][$meldung['federation_contact_id']]['participations'][$meldung['usergroup_id']]['participations'] = $meldung['participations'];
			$data['landesverbaende'][$meldung['federation_contact_id']]['participations'][0]['participations'] += $meldung['participations'];
			$data['participations'][$meldung['usergroup_id']]['participations'] += $meldung['participations'];
			$data['participations'][0]['participations'] += $meldung['participations'];
		}
	}
	foreach ($buchungen as $federation_contact_id => $buchung) {
		foreach ($buchung as $status => $betrag) {
			$data['landesverbaende'][$federation_contact_id]['buchungen'][$status]['betrag'] = $betrag['kosten'];
			$data['landesverbaende'][$federation_contact_id]['buchungen'][0]['betrag'] += $betrag['kosten'];
			$data['buchungen'][$status]['betrag'] += $betrag['kosten'];
			$data['buchungen'][0]['betrag'] += $betrag['kosten'];
		}
	}
	foreach ($data['landesverbaende'] as $federation_contact_id => $lv_data) {
		$data['landesverbaende'][$federation_contact_id]['buchungen'] = array_values($lv_data['buchungen']);
		if ($lv_data['kontingente'][0]['kontingent'].'' === $lv_data['participations'][wrap_id('usergroups', 'spieler')]['participations'].'') {
			$data['landesverbaende'][$federation_contact_id]['participations'][wrap_id('usergroups', 'spieler')]['color'] = 'green';
		} elseif (!$lv_data['participations'][wrap_id('usergroups', 'spieler')]['participations']){
			$data['landesverbaende'][$federation_contact_id]['participations'][wrap_id('usergroups', 'spieler')]['color'] = 'red';
		} else {
			$data['landesverbaende'][$federation_contact_id]['participations'][wrap_id('usergroups', 'spieler')]['color'] = 'yellow';
		}
	}
	$data['anzahl_kontingente'] = count($data['kontingente']);
	$data['anzahl_participations'] = count($data['participations']);
	$data['anzahl_buchungen'] = count($data['buchungen']);

	// per Turnier
	$data['turniere'] = [];
	foreach ($kontingente as $k) {
		if (!array_key_exists($k['event_id'], $data['turniere'])) {
			$data['turniere'][$k['event_id']]['event'] = $k['event'];
			$data['turniere'][$k['event_id']]['event_identifier'] = $k['event_identifier'];
			$data['turniere'][$k['event_id']]['kontingente'] = $data['kontingente'];
			foreach (array_keys($data['turniere'][$k['event_id']]['kontingente']) as $k_id) {
				$data['turniere'][$k['event_id']]['kontingente'][$k_id]['kontingent'] = false;
			}
			$data['turniere'][$k['event_id']]['participations'] = $data['participations'];
			foreach (array_keys($data['turniere'][$k['event_id']]['participations']) as $t_id) {
				$data['turniere'][$k['event_id']]['participations'][$t_id]['participations'] = false;
			}
			$data['turniere'][$k['event_id']]['spielberechtigungen'][0]['anzahl'] = 0;
			$data['turniere'][$k['event_id']]['spielberechtigungen'][1]['anzahl'] = 0;
			$data['turniere'][$k['event_id']]['spielberechtigungen'][2]['anzahl'] = 0;
		}
		$data['turniere'][$k['event_id']]['kontingente'][$k['kontingent_category_id']]['kontingent'] += $k['kontingent'];
		$data['turniere'][$k['event_id']]['kontingente'][0]['kontingent'] += $k['kontingent'];
	}

	$sql = 'SELECT event_id, COUNT(participation_id) AS participations
			, usergroup_id, usergroups.identifier, usergroup
			, COUNT(IF(spielberechtigt = "ja", 1, NULL)) AS spielberechtigt_ja
			, COUNT(IF(spielberechtigt = "nein", 1, NULL)) AS spielberechtigt_nein
			, COUNT(IF(spielberechtigt = "vorläufig nein", 1, NULL)) AS spielberechtigt_offen
		FROM participations
		LEFT JOIN usergroups USING (usergroup_id)
		WHERE event_id IN (%s)
		AND NOT ISNULL(federation_contact_id)
		AND usergroup_id IN (%s)
		GROUP BY event_id, usergroup_id
		ORDER BY IF(usergroups.identifier = "spieler", NULL, 1)
			, IF(usergroups.identifier = "betreuer", NULL, 1)
	';
	$sql = sprintf($sql, implode(',', $event_ids), implode(',', array_keys($usergroups)));
	$t_meldungen = wrap_db_fetch($sql, ['event_id', 'usergroup_id']);
	
	$data['spielberechtigungen'][0]['anzahl'] = 0;
	$data['spielberechtigungen'][1]['anzahl'] = 0;
	$data['spielberechtigungen'][2]['anzahl'] = 0;
	
	foreach ($t_meldungen as $event_id => $t_meldung_per_gruppe) {
		if (empty($data['turniere'][$event_id])) continue; // DEM
		foreach ($t_meldung_per_gruppe as $usergroup_id => $t_meldung) {
			$data['turniere'][$event_id]['participations'][$usergroup_id]['participations'] += $t_meldung['participations'];
			$data['turniere'][$event_id]['participations'][0]['participations'] += $t_meldung['participations'];
			if ($usergroup_id.'' === wrap_id('usergroups', 'spieler').'') {
				$data['turniere'][$event_id]['spielberechtigungen'][0]['anzahl'] = $t_meldung['spielberechtigt_ja'];
				$data['turniere'][$event_id]['spielberechtigungen'][1]['anzahl'] = $t_meldung['spielberechtigt_nein'];
				$data['turniere'][$event_id]['spielberechtigungen'][2]['anzahl'] = $t_meldung['spielberechtigt_offen'];
				$data['spielberechtigungen'][0]['anzahl'] += $t_meldung['spielberechtigt_ja'];
				$data['spielberechtigungen'][1]['anzahl'] += $t_meldung['spielberechtigt_nein'];
				$data['spielberechtigungen'][2]['anzahl'] += $t_meldung['spielberechtigt_offen'];
			}
		}
	}

	$check = [];
	foreach ($data['turniere'] as $event_id => $turnier) {
		if ($turnier['kontingente'][0]['kontingent'].'' === $turnier['participations'][wrap_id('usergroups', 'spieler')]['participations'].'') {
			$data['turniere'][$event_id]['participations'][wrap_id('usergroups', 'spieler')]['color'] = 'green';
			$data['turniere'][$event_id]['hinweise'] = '';
			$check[] = $event_id;
		} elseif (!$turnier['participations'][wrap_id('usergroups', 'spieler')]['participations']){
			$data['turniere'][$event_id]['participations'][wrap_id('usergroups', 'spieler')]['color'] = 'red';
			$data['turniere'][$event_id]['hinweise'] = '– noch keine Teilnehmer gemeldet –';
		} else {
			$data['turniere'][$event_id]['participations'][wrap_id('usergroups', 'spieler')]['color'] = 'yellow';
			$data['turniere'][$event_id]['hinweise'] = '';
			$check[] = $event_id;
		}
		if ($turnier['participations'][wrap_id('usergroups', 'spieler')]['participations'].'' === $turnier['spielberechtigungen'][0]['anzahl'].'') {
			$data['turniere'][$event_id]['spielberechtigungen'][0]['color'] = 'green';
		} elseif (!$turnier['spielberechtigungen'][0]['anzahl']) {
			$data['turniere'][$event_id]['spielberechtigungen'][0]['color'] = 'red';
		} else {
			$data['turniere'][$event_id]['spielberechtigungen'][0]['color'] = 'yellow';
		}
	}
	
	// Check: wer fehlt?
	// Check: Freiplätze richtig belegt?
	if ($check) {
		$sql = 'SELECT kontingent_id, contact_abbr, kontingente.event_id
				, kontingent, kontingente.anmerkung, category
				, (SELECT COUNT(*)
					FROM participations
					WHERE participations.federation_contact_id = kontingente.federation_contact_id
					AND SUBSTRING_INDEX(participations.qualification, " [", 1) = categories.category
					AND participations.event_id = kontingente.event_id) AS plaetze
				, (SELECT GROUP_CONCAT(CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname))
					FROM participations
					WHERE participations.federation_contact_id = kontingente.federation_contact_id
					AND SUBSTRING_INDEX(participations.qualification, " [", 1) = categories.category
					AND participations.event_id = kontingente.event_id) AS meldungen
			FROM kontingente
			LEFT JOIN contacts
				ON kontingente.federation_contact_id = contacts.contact_id
			LEFT JOIN categories
				ON kontingente.kontingent_category_id = categories.category_id
			WHERE kontingente.event_id IN (%s)
			HAVING kontingent != plaetze
			OR NOT ISNULL(anmerkung)';
		$sql = sprintf($sql, implode(',', $check));
		// 1. Freiplatzrunde [91-0], Landesverband [40-0]
		$checks = wrap_db_fetch($sql, ['event_id', 'kontingent_id']);
		foreach ($checks as $event_id => $kontingente) {
			foreach ($kontingente as $kontingent) {
				$fehlend = 0;
				if ($kontingent['anmerkung'] && $kontingent['category'] != 'Landesverband') {
					if ($kontingent['meldungen'])
						$meldungen = explode(',', $kontingent['meldungen']);
					else
						$meldungen = [];
					$plaetze = explode(',', $kontingent['anmerkung']);
					foreach ($plaetze as $index => $platz)
						$plaetze[$index] = trim($platz);
					foreach ($meldungen as $meldung) {
						$meldung = trim($meldung);
						$key = array_search($meldung, $plaetze);
						if ($key !== false) unset($plaetze[$key]);
					}
					if ($plaetze) {
						if ($data['turniere'][$event_id]['hinweise'])
							$data['turniere'][$event_id]['hinweise'] .= ', ';
						$data['turniere'][$event_id]['hinweise'] .= sprintf(
							'%d × %s (%s)', count($plaetze), $kontingent['contact_abbr'], implode(', ', $plaetze)
						);
						$fehlend = count($plaetze);
					}
				}
				$diff = $kontingent['kontingent'] - $kontingent['plaetze'];
				if ($diff AND $diff !== $fehlend) {
					if ($data['turniere'][$event_id]['hinweise'])
						$data['turniere'][$event_id]['hinweise'] .= ', ';
					$data['turniere'][$event_id]['hinweise'] .= sprintf(
						'%d × %s', $diff, $kontingent['contact_abbr']
					);
				}
			}
		}
	}
	
	// replace 0 with '' for better readability
	foreach ($data['landesverbaende'] as $id => $lv) {
		foreach ($lv['participations'] as $t_id => $line) {
			if (!$line['participations'])
				$data['landesverbaende'][$id]['participations'][$t_id]['participations'] = '';
		}
		foreach ($lv['kontingente'] as $t_id => $line) {
			if (!$line['kontingent'])
				$data['landesverbaende'][$id]['kontingente'][$t_id]['kontingent'] = ''; 
		}
		foreach ($lv['buchungen'] as $t_id => $line) {
			if (!$line['betrag'])
				$data['landesverbaende'][$id]['buchungen'][$t_id]['betrag'] = ''; 
		}
	}
	foreach ($data['turniere'] as $id => $turnier) {
		foreach ($turnier['spielberechtigungen'] as $t_id => $line) {
			if (!$line['anzahl'])
				$data['turniere'][$id]['spielberechtigungen'][$t_id]['anzahl'] = ''; 
		}
	}
	foreach ($data['spielberechtigungen'] as $id => $sp) {
		if (!$sp['anzahl'])
			$data['spielberechtigungen'][$id]['anzahl'] = ''; 
	}
	
	$data['categories'] = mf_qualification_registration_categories();
	$first_federation = reset($data['landesverbaende']);
	foreach (array_keys($data['categories']) AS $category_id) {
		$data['categories'][$category_id]['columns_quota'] = count($first_federation['kontingente']);
		$data['categories'][$category_id]['columns_booking'] = count($first_federation['buchungen']);
		$data['categories'][$category_id]['columns_participations'] = count($first_federation['participations']);
	}

	$page['dont_show_h1'] = true;
	$page['text'] = wrap_template('meldungen', $data);
	$page['breadcrumbs'][]['title'] = 'Meldungsübersicht';
	$page['title'] = 'Meldungen '.$data['event'].' '.wrap_date($data['duration']);
	return $page;
}
