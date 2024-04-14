<?php 

/**
 * qualification module
 * Meldung der Landesverbände für ein Turnier
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2017-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


function mod_qualification_make_meldunglv($vars, $settings, $data) {
	if (count($vars) !== 3) return false;
	wrap_package_activate('tournaments');

	// Turnierbedinungen prüfen
	wrap_include_files('anmeldung', 'custom');
	wrap_include_files('persons', 'custom');
	wrap_include_files('zzform/editing', 'ratings');
	wrap_include_files('zzform/batch', 'contacts');

	// Zugriffsrechte
	$access = my_pruefe_meldunglv_rechte($vars[0].'/'.$vars[1], $vars[2]);
	// @todo Nach Meldeschluss: nur noch Ansicht der Daten
	
	// Landesverband
	wrap_include_files('functions', 'clubs');
	$lv = mf_clubs_federation($vars[2]);
	if (!$lv) return false;
	$data['landesverband'] = $lv['federation_short'];
	$data['landesverband_kennung'] = $lv['federation_identifier'];

	// Turniere
	$sql = 'SELECT event_id, event, alter_min, alter_max, geschlecht
			, dwz_min, dwz_max, elo_min, elo_max, events.identifier
			, IF(geschlecht = "m", 1, NULL) AS geschlecht_nur_m
			, IF(geschlecht = "w", 1, NULL) AS geschlecht_nur_w
			, SUBSTRING_INDEX(events.identifier, "/", -1) AS identifier_short
			, IF(offen = "ja", 1, NULL) AS offen
			, series.parameters
		FROM events
		LEFT JOIN tournaments USING (event_id)
		LEFT JOIN categories series
			ON events.series_category_id = series.category_id
		WHERE IFNULL(event_year, YEAR(date_begin)) = %d
		AND series.main_category_id = %d
		ORDER BY series.sequence';
	$sql = sprintf($sql, $vars[0], $data['series_category_id']);
	$data['turniere'] = wrap_db_fetch($sql, 'event_id');
	if (!$data['turniere']) return false;
	
	$sql = 'SELECT event_id, kontingent_id
			, kontingent, anmerkung, category, category_short
		FROM kontingente
		LEFT JOIN categories
			ON kontingente.kontingent_category_id = categories.category_id
		WHERE federation_contact_id = %d
		AND event_id IN (%s)
		ORDER BY event_id, categories.sequence';
	$sql = sprintf($sql, $lv['contact_id'], implode(',', array_keys($data['turniere'])));
	$kontingente = wrap_db_fetch($sql, ['event_id', 'kontingent_id']);

	$sql = 'SELECT event_id, participation_id, person_id, contact_id
			, IFNULL(
				CONCAT(t_vorname, IFNULL(CONCAT(" ", t_namenszusatz), ""), " ", t_nachname), 
				contact
			) AS person
			, contacts.identifier AS personenkennung
			, t_verein
			, t_dwz, t_elo, t_fidetitel
			, qualification, date_of_birth
			, IF(sex = "female", "W", IF(sex = "male", "M", "")) AS geschlecht
			, (SELECT identification FROM contactdetails
				WHERE contactdetails.contact_id = contacts.contact_id
				AND provider_category_id = %d
				LIMIT 1
			) AS e_mail
			, GROUP_CONCAT(DISTINCT contactdetails.identification SEPARATOR "; ") AS telefon
			, GROUP_CONCAT(DISTINCT CONCAT(address, "; "
				, IFNULL(CONCAT(addresses.postcode, " "), ""), addresses.place) SEPARATOR "; ") AS adresse
			, usergroups.identifier AS group_identifier
			, role
			, (SELECT SUM(betrag) FROM buchungen WHERE buchungen.participation_id = participations.participation_id) AS buchung
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN addresses USING (contact_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN contactdetails USING (contact_id)
		LEFT JOIN categories
			ON contactdetails.provider_category_id = categories.category_id
			AND (ISNULL(categories.parameters) OR categories.parameters LIKE "%%&type=phone%%")
		WHERE federation_contact_id = %d
		AND event_id IN (%s, %d)
		AND usergroup_id != %d
		AND status_category_id IN (%d, %d, %d)
		GROUP BY participations.participation_id';
	$sql = sprintf($sql
		, wrap_category_id('provider/e-mail')
		, $lv['contact_id']
		, implode(',', array_keys($data['turniere']))
		, $data['event_id']
		, wrap_id('usergroups', 'bewerber')
		, wrap_category_id('participation-status/subscribed')
		, wrap_category_id('participation-status/verified')
		, wrap_category_id('participation-status/participant')
	);
	$participations = wrap_db_fetch($sql, ['event_id', 'participation_id']);
	
	$data['buchungen'] = 0;
	$data['teilnehmer'] = 0;
	
	$data['betreuer'] = [];
	$data['betreuer_buchungen'] = 0;
	$data['betreuer_teilnehmer'] = 0;
	$data['mitreisende'] = [];
	$data['mitreisende_buchungen'] = 0;
	$data['mitreisende_teilnehmer'] = 0;
	$data['gast_teilnehmer'] = 0;
	$data['gast_buchungen'] = 0;
	if (!empty($participations[$data['event_id']])) {
		foreach ($participations[$data['event_id']] as $index => $teilnahme) {
			if ($teilnahme['group_identifier'] === 'landesverband-organisator') continue; 
			$teilnahme['access'] = $access;
			$data[$teilnahme['group_identifier']][$index] = $teilnahme;
			$data[$teilnahme['group_identifier'].'_teilnehmer']++;
			$data[$teilnahme['group_identifier'].'_buchungen'] += $teilnahme['buchung'];
			$data['buchungen'] += $teilnahme['buchung'];
			$data['teilnehmer']++;
		}
	}

	$meldungen = [];
	$data['opens_buchungen'] = 0;
	$data['opens_teilnehmer'] = 0;
	foreach ($data['turniere'] as $event_id => $turnier) {
		$data['turniere'][$event_id]['access'] = $access;
		if (empty($kontingente[$event_id])) {
			if (!$turnier['offen']) {
				$data['turniere'][$event_id]['kein_kontingent'] = true;
				continue;
			}
			unset($data['turniere'][$event_id]);
			if ($turnier['parameters'])
				parse_str($turnier['parameters'], $parameter);
			if (empty($participations[$event_id]) AND empty($parameter['lvmeldung'])) continue;
			$data['opens'][$event_id] = $turnier;
			$data['opens'][$event_id]['spieler'] = [];
			$data['opens'][$event_id]['buchungen'] = 0;
			$data['opens'][$event_id]['access'] = $access;
			if (!empty($participations[$event_id])) {
				foreach ($participations[$event_id] as $tn) {
					$data['opens'][$event_id][$tn['group_identifier'].'_offen'][$tn['participation_id']] = $tn;
					$data['opens'][$event_id]['buchungen'] += $tn['buchung'];
					$data['opens_buchungen'] += $tn['buchung'];
					$data['buchungen'] += $tn['buchung'];
					$data['teilnehmer']++;
				}
			}
			if (empty($data['opens'][$event_id]['spieler_offen'])) {
				$data['opens'][$event_id]['spieler_offen'] = 0;
			} else {
				$data['opens'][$event_id]['teilnehmer'] = count($data['opens'][$event_id]['spieler_offen']);
				$data['opens_teilnehmer'] += count($data['opens'][$event_id]['spieler_offen']);
			}
			continue;
		}
		foreach ($kontingente[$event_id] AS $kontingent) {
			$namen = $kontingent['anmerkung'] ? explode(',', $kontingent['anmerkung']) : [];
			for ($i = 0; $i < $kontingent['kontingent']; $i++) {
				$id = $kontingent['kontingent_id'].'-'.$i;
				$data['turniere'][$event_id]['spieler'][$id] = [
					'kontingent' => $kontingent['category'],
					'kontingent_abk' => $kontingent['category_short'],
					'fp' => !empty($namen[$i]) ? trim($namen[$i]) : '',
					'id' => $id,
					'access' => $access
				];
				$meldungen[$id] = [
					'event_id' => $event_id,
					'kontingent' => $kontingent['category']
				];
			}
		}
		if (empty($participations[$event_id])) continue;
		foreach ($participations[$event_id] as $teilnehmer) {
			if (!$teilnehmer['qualification']) continue;
			$id = explode(' ', $teilnehmer['qualification']);
			$id = array_pop($id);
			$id = substr($id, 1, -1);
			if (empty($data['turniere'][$event_id]['spieler'][$id]))
				$data['turniere'][$event_id]['spieler'][$id] = [];
			$data['turniere'][$event_id]['spieler'][$id]
				= array_merge($data['turniere'][$event_id]['spieler'][$id], $teilnehmer);
		}
	}
	foreach ($data['turniere'] as $event_id => $turnier) {
		$data['turniere'][$event_id]['buchungen'] = 0;
		if (empty($data['turniere'][$event_id]['spieler'])) continue;
		$data['turniere'][$event_id]['teilnehmer'] = count($data['turniere'][$event_id]['spieler']);
		$data['teilnehmer'] += count($data['turniere'][$event_id]['spieler']);
		foreach ($data['turniere'][$event_id]['spieler'] as $spieler) {
			if (empty($spieler['buchung'])) continue;
			$data['turniere'][$event_id]['buchungen'] += $spieler['buchung'];
			$data['buchungen'] += $spieler['buchung'];
		}
	}

	if (!empty($_POST) AND $access) {
		wrap_include_files('zzform.php', 'zzform');
		wrap_include_files('zzform/batch', 'contacts');
		zz_initialize();

		foreach ($_POST AS $meldung_id => $meldung) {
			// remove whitespace
			foreach ($meldung as $key => $value)
				$meldung[$key] = trim($value);
			$meldung_offen = false;
			// Übernahme der Daten
			if (in_array($meldung_id, ['betreuer', 'mitreisende'])) {
				foreach ($meldung as $key => $value) {
					$data[$meldung_id.'_'.$key] = $value; // für Formular
					if ($key === 'geschlecht') {
						$data[$meldung_id.'_'.$key.'_'.$value] = true;
					}
				}
				$m_person = &$data;
			} elseif (substr($meldung_id, 0, 8) === 'betreuer') {
				$m_person['participation_id'] = substr($meldung_id, 9);
				if (!in_array($m_person['participation_id'], array_keys($data['betreuer']))) {
					$m_person['participation_id'] = false;
				}
			} elseif (substr($meldung_id, 0, 11) === 'mitreisende') {
				$m_person['participation_id'] = substr($meldung_id, 12);
				if (!in_array($m_person['participation_id'], array_keys($data['mitreisende']))) {
					$m_person['participation_id'] = false;
				} 
			} else {
				if (!empty($data['opens']) AND array_key_exists($meldung_id, $data['opens'])) {
					$turnier = &$data['opens'][$meldung_id];
					$meldung_offen = true;
					$m_person = &$data['opens'][$meldung_id];
				} elseif (array_key_exists($meldung_id, $meldungen)) {
					$turnier = &$data['turniere'][$meldungen[$meldung_id]['event_id']];
					$m_person = &$turnier['spieler'][$meldung_id];
				} else {
					// delete open participant
					foreach ($data['opens'] as $open_id => $open) {
						if (!array_key_exists($meldung_id, $open['spieler_offen'])) continue;
						$m_person = &$data['opens'][$open_id]['spieler_offen'][$meldung_id];
					}
				}
				if (!empty($meldung['person']) or !empty($meldung['date_of_birth'])) {
					$m_person['name'] = $meldung['person'];
					$m_person['date_of_birth'] = $meldung['date_of_birth'];
				}
				if (!empty($meldung['geschlecht'])) {
					$m_person['geschlecht_'.$meldung['geschlecht']] = true;
				}
			}
			if (empty($meldung['melden'])) continue;
			if ($meldung['melden'] === 'Abmelden') {
				// Anmeldung löschen
				$sql = 'SELECT registration_id FROM registrations WHERE participation_id = %d';
				$sql = sprintf($sql, $m_person['participation_id']);
				$registration_id = wrap_db_fetch($sql, '', 'single value');
				if ($registration_id)
					zzform_delete('anmeldungen',  $registration_id);
				// Buchungen löschen
				$sql = 'SELECT buchung_id FROM buchungen WHERE participation_id = %d';
				$sql = sprintf($sql, $m_person['participation_id']);
				$booking_ids = wrap_db_fetch($sql, '_dummy_', 'single value');
				if ($booking_ids)
					zzform_delete('buchungen',  $booking_ids);
				$deleted = zzform_delete('participations', $m_person['participation_id']);
				if ($deleted) wrap_redirect_change();
			}
			
			if ($meldung['melden'] !== 'Anmelden') continue;
			if (!$meldung_offen
				AND !in_array($meldung_id, ['betreuer', 'mitreisende'])
				AND !array_key_exists($meldung_id, $meldungen)) {
				wrap_error(sprintf('Anmeldeversuch ohne gültige Meldungs-ID %d', $meldung_id), E_USER_WARNING);
				continue;
			}
			if (!$meldung['person']) {
				$m_person['error'] = 'Name fehlt.';
				continue;
			}
			if (!$meldung['date_of_birth'] AND substr($meldung_id, 0, 11) !== 'mitreisende') {
				$m_person['error'] = 'Geburtsdatum fehlt.';
				continue;
			}
			if ($meldung['date_of_birth'] AND !zz_check_date($meldung['date_of_birth'])) {
				$m_person['error'] = 'Geburtsdatum korrekt?';
				continue;
			}

			$person = my_person_suchen([$meldung['date_of_birth'], $meldung['person']]);
			if (!$person) {
				if (!in_array($meldung_id, ['betreuer', 'mitreisende'])) {
					$m_person['error'] = 'Person nicht gefunden (Daten korrekt?)';
					continue;
				} else {
					// @todo Prüfung: Vor- und Nachname vorhanden: ',' oder ' ' vorhanden?
					$person = my_namen_aufteilen($meldung['person']);
					$person['date_of_birth'] = $meldung['date_of_birth'];
					switch ($meldung['geschlecht']) {
						case 'm': $person['sex'] = 'male'; break;
						case 'w': $person['sex'] = 'female'; break;
					}
				}
			}
			if (empty($person['player_pass_dsb']) AND !in_array($meldung_id, ['betreuer', 'mitreisende'])) {
				$m_person['error'] = 'Person gefunden, aber nicht DSB-Mitglied';
				continue;
			}

			// Verein
			if (!empty($person['verein'])) {
				$verein = my_vereinssuche($person['verein']);
				if (count($verein) === 1) $verein = reset($verein);
				else $verein = [];
			} else {
				$verein = [];
			}

			// Wertungen
			$wertungen = $person['player_pass_dsb'] ? mf_ratings_player_rating_dsb($person['player_pass_dsb']) : [];

			if (!in_array($meldung_id, ['betreuer', 'mitreisende'])) {
				$error = my_pruefe_turnierbedinungen($turnier, $person, $wertungen);
				if ($error) {
					$m_person['error'] = $error;
					continue;
				}
			}
			
			if (empty($person['contact_id']))
				$person['contact_id'] = mf_contacts_add_person($person);

			$line = [
				'contact_id' => $person['contact_id'],
				't_dwz' => $wertungen['t_dwz'] ?? NULL,
				't_elo' => $wertungen['t_elo'] ?? NULL,
				't_fidetitel' => $wertungen['t_fidetitel'] ?? NULL,
				'club_contact_id' => $verein ? $verein['contact_id'] : '',
				'federation_contact_id' => $lv['contact_id'],
				'status_category_id' => wrap_category_id('participation-status/verified')
			];
			if ($meldung_id === 'betreuer') {
				$line['event_id'] = $data['event_id'];
				$line['usergroup_id'] = wrap_id('usergroups', 'betreuer');
				$line['role'] = $meldung['role'];
			} elseif ($meldung_id === 'mitreisende') {
				$line['event_id'] = $data['event_id'];
				$line['usergroup_id'] = wrap_id('usergroups', 'mitreisende');
			} elseif ($meldung_offen) {
				$line['event_id'] = $meldung_id;
				$line['usergroup_id'] = wrap_id('usergroups', 'spieler');
			} else {
				$line['event_id'] = $meldungen[$meldung_id]['event_id'];
				$line['usergroup_id'] = wrap_id('usergroups', 'spieler');
				$line['qualification'] = $meldungen[$meldung_id]['kontingent'].' ['.$meldung_id.']';
			}
			if (wrap_category_id('participations/registration', 'check')) {
				$line['participations_categories_'.wrap_category_id('participations/registration')][]['category_id']
					= wrap_category_id('participations/registration/federation');
			}
			zzform_insert('participations', $line);
			
			// ggf. Geburtsdatum aktualisieren
			wrap_include_files('batch', 'zzform');
			zzform_update_date($person, 'persons', 'contact_id', 'date_of_birth');
			wrap_redirect_change();
		}
	}
	if (!empty($data['error'])) {
		$data[$meldung_id.'_error'] = $data['error'];
	}
	$data['access'] = $access ? $access : NULL;

	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][]['title'] = $data['landesverband'];
	$page['title'] = $data['landesverband'].' – '.$data['event'].' '.$data['year'];
	$page['text'] = wrap_template('meldunglv', $data);
	return $page;
}
