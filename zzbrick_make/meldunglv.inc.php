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

	// access rights
	$access = my_pruefe_meldunglv_rechte($vars[0].'/'.$vars[1], $vars[2]);
	// @todo Nach Meldeschluss: nur noch Ansicht der Daten
	
	// federation or direct or organisation
	wrap_include_files('functions', 'clubs');
	$federation = mf_clubs_federation($vars[2]);
	if ($federation) {
		$data['landesverband'] = $federation['federation_short'];
		$data['landesverband_kennung'] = $federation['federation_identifier'];
	} else {
		$category = mf_qualification_registration_category($vars[2]);
		if (!$category) return false;
		$data += $category;
		$federation = NULL;
	}

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
	
	if ($federation)
		$kontingente = mf_qualification_quotas($federation['contact_id'], array_keys($data['turniere']));

	if ($federation)
		$where = sprintf('federation_contact_id = %d', $federation['contact_id']);
	else
		$where = sprintf('participations_categories.category_id = %d', $category['category_id']);
	$participations = mf_qualification_participants($where, $data['event_id'], array_keys($data['turniere']));
	$p_per_event = [];
	foreach ($participations as $participation_id => $participation)
		$p_per_event[$participation['event_id']][$participation_id] = $participation;

	$groups = [
		'spieler' => [
			'title' => 'Spieler',
			'index' => 0
		],
		'betreuer' => [
			'title' => 'Offizielle Betreuer',
			'index' => 1
		],
		'mitreisende' => [
			'title' => 'Mitreisende',
			'index' => 2
		],
	];
	$data += mf_qualification_event($data, $p_per_event[$data['event_id']], $groups, $access);

	$meldungen = [];
	foreach ($data['turniere'] as $event_id => $turnier) {
		$data['turniere'][$event_id]['access'] = $access;
		if (empty($kontingente[$event_id])) {
			if (!$turnier['offen'])
				$turnier['kein_kontingent'] = true;
			unset($data['turniere'][$event_id]);
			if ($turnier['parameters'])
				parse_str($turnier['parameters'], $parameter);
			if (empty($p_per_event[$event_id]) AND empty($parameter['lvmeldung'])) continue;
			$open_groups = $groups;
			unset($open_groups['betreuer']);
			$data['opens'][$event_id] = mf_qualification_event($turnier, $p_per_event[$event_id] ?? [], $open_groups, $access);
			$data['opens'][$event_id]['access'] = $access;
			$data['sum_total'] += $data['opens'][$event_id]['sum_total'];
			$data['participants_total'] += $data['opens'][$event_id]['participants_total'];
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
		if (empty($p_per_event[$event_id])) continue;
		foreach ($p_per_event[$event_id] as $teilnehmer) {
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
		$data['turniere'][$event_id]['spieler_sum'] = 0;
		$data['turniere'][$event_id]['spieler_count'] = 0;
		if (empty($data['turniere'][$event_id]['spieler'])) continue;
		foreach ($data['turniere'][$event_id]['spieler'] as $spieler) {
			if (!mf_qualification_federation_member($spieler)) continue;
			$data['turniere'][$event_id]['spieler_count']++;
			if (empty($spieler['buchung'])) continue;
			$data['turniere'][$event_id]['spieler_sum'] += $spieler['buchung'];
			$data['sum_total'] += $spieler['buchung'];
		}
		$data['participants_total'] += $data['turniere'][$event_id]['spieler_count'];
	}

	if (!empty($_POST) AND $access) {
		wrap_include_files('zzform.php', 'zzform');
		wrap_include_files('zzform/batch', 'contacts');
		zz_initialize();

		if (isset($_POST['unregister'])) {
			$participation_id = key($_POST['unregister']);
			mf_qualification_unregister($participations[$participation_id] ?? []);
			wrap_redirect_change();
		}
		if (isset($_POST['move'])) {
			$participation_id = key($_POST['move']);
			mf_qualification_move_to_federation_quota($participations[$participation_id] ?? []);
			wrap_redirect_change();
		}

		foreach ($_POST AS $participation_id => $meldung) {
			// remove whitespace
			foreach ($meldung as $key => $value)
				$meldung[$key] = trim($value);
			$meldung_offen = false;
			// Übernahme der Daten
			if (in_array($participation_id, ['betreuer', 'mitreisende'])) {
				foreach ($meldung as $key => $value) {
					$data['groups'][$groups[$participation_id]['index']]['form_'.$key] = $value; // for form
					if ($key === 'geschlecht')
						$data['groups'][$groups[$participation_id]['index']]['form_'.$key.'_'.$value] = true;
				}
				$m_person = &$data;
			} else {
				if (!empty($data['opens']) AND array_key_exists($participation_id, $data['opens'])) {
					$turnier = &$data['opens'][$participation_id];
					$meldung_offen = true;
					$m_person = &$data['opens'][$participation_id];
				} elseif (array_key_exists($participation_id, $meldungen)) {
					$turnier = &$data['turniere'][$meldungen[$participation_id]['event_id']];
					$m_person = &$turnier['spieler'][$participation_id];
				} else {
					// delete open participant
					foreach ($data['opens'] as $open_id => $open) {
						if (!array_key_exists($participation_id, $open['spieler_offen'])) continue;
						$m_person = &$data['opens'][$open_id]['spieler_offen'][$participation_id];
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
			
			if ($meldung['melden'] !== 'Anmelden') continue;
			if (!$meldung_offen
				AND !in_array($participation_id, ['betreuer', 'mitreisende'])
				AND !array_key_exists($participation_id, $meldungen)) {
				wrap_error(sprintf('Anmeldeversuch ohne gültige Meldungs-ID %d', $participation_id), E_USER_WARNING);
				continue;
			}
			if (!$meldung['person']) {
				$m_person['error'] = 'Name fehlt.';
				continue;
			}
			if (!$meldung['date_of_birth'] AND substr($participation_id, 0, 11) !== 'mitreisende') {
				$m_person['error'] = 'Geburtsdatum fehlt.';
				continue;
			}
			if ($meldung['date_of_birth'] AND !zz_check_date($meldung['date_of_birth'])) {
				$m_person['error'] = 'Geburtsdatum korrekt?';
				continue;
			}

			$person = my_person_suchen([$meldung['date_of_birth'], $meldung['person']]);
			if (!$person) {
				if (!in_array($participation_id, ['betreuer', 'mitreisende'])) {
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
			if (empty($person['player_pass_dsb']) AND !in_array($participation_id, ['betreuer', 'mitreisende'])) {
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
			$player_pass_dsb = $person['player_pass_dsb'] ?? '';
			if ($player_pass_dsb)
				$wertungen = mf_ratings_player_rating_dsb($player_pass_dsb);

			if (!in_array($participation_id, ['betreuer', 'mitreisende'])) {
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
				'federation_contact_id' => $federation['contact_id'],
				'status_category_id' => wrap_category_id('participation-status/verified')
			];
			if ($participation_id === 'betreuer') {
				$line['event_id'] = $data['event_id'];
				$line['usergroup_id'] = wrap_id('usergroups', 'betreuer');
				$line['role'] = $meldung['role'];
			} elseif ($participation_id === 'mitreisende') {
				$line['event_id'] = $data['event_id'];
				$line['usergroup_id'] = wrap_id('usergroups', 'mitreisende');
			} elseif ($meldung_offen) {
				$line['event_id'] = $participation_id;
				$line['usergroup_id'] = wrap_id('usergroups', 'spieler');
			} else {
				$line['event_id'] = $meldungen[$participation_id]['event_id'];
				$line['usergroup_id'] = wrap_id('usergroups', 'spieler');
				$line['qualification'] = $meldungen[$participation_id]['kontingent'].' ['.$participation_id.']';
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
		if (in_array($participation_id, ['betreuer', 'mitreisende'])) {
			$data['groups'][$groups[$participation_id]['index']]['form_error'] = $data['error'];
		} else {
			$data[$participation_id.'_error'] = $data['error'];
		}
	}
	$data['access'] = $access ?? NULL;
	unset($data['groups'][0]); // players
	
	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][] = sprintf('<a href="../">%s</a>', $data['event']);
	$page['breadcrumbs'][]['title'] = $federation['federation_short'] ?? $category['category'];
	$page['title'] = sprintf('%s – %s %d',
		$federation['federation_short'] ?? $category['category'],
		$data['event'],
		$data['year']
	);
	$page['text'] = wrap_template('meldunglv', $data);
	return $page;
}

/**
 * calculate an event
 *
 * @param array $event
 * @param array $participants
 * @param array $groups
 * @param string $access
 * @return array
 */
function mf_qualification_event($event, $participants, $groups, $access) {
	// total sums
	$event['participants_total'] = 0;
	$event['sum_total'] = 0;
	foreach ($groups as $index => $group) {
		$event['groups'][$group['index']] = [
			$index => true,
			'identifier' => $index,
			'title' => $group['title'],
			'sum' => 0,
			'count' => 0,
			'access' => $access,
			'participants' => [],
			'has_participants' => false
		];
		if ($index === 'betreuer')
			$event['groups'][$group['index']]['has_role'] = true;
	}

	foreach ($participants as $participation_id => $pt) {
		$pt['access'] = $access;
		$index = $groups[$pt['group_identifier']]['index'];
		$event['groups'][$index]['participants'][$participation_id] = $pt;
		$event['groups'][$index]['has_participants'] = true;
		if (!mf_qualification_federation_member($pt)) continue;
		$event['groups'][$index]['sum'] += $pt['buchung'];
		$event['sum_total'] += $pt['buchung'];
		$event['groups'][$index]['count']++;
		$event['participants_total']++;
	}

	return $event;
}

/**
 * move a participant from direct or organiser quota to federation quota
 *
 * @param array $participation
 * @return bool
 */
function mf_qualification_move_to_federation_quota($participation) {
	if (!$participation) return false;
	if (str_starts_with($participation['registration_path'], 'federation')) return false;

	$line = [
		'participation_category_id' => $participation['participation_category_id'],
		'type_category_id' => wrap_category_id('participations/registration'),
		'category_id' => wrap_category_id('participations/registration/federation-'.$participation['registration_path'])
	];
	zzform_update('participations_categories', $line);
	return true;
}

/**
 * unregister a participant
 *
 * @param array $participation
 * @return void
 */
function mf_qualification_unregister($participation) {
	if (!$participation) return false;
	
	// only allow to unregister people who were registered here before
	if ($participation['registration_path'] !== 'federation') return false;

	// delete registration
	$sql = 'SELECT registration_id FROM registrations WHERE participation_id = %d';
	$sql = sprintf($sql, $participation['participation_id']);
	$registration_id = wrap_db_fetch($sql, '', 'single value');
	if ($registration_id)
		zzform_delete('anmeldungen',  $registration_id);

	// delete bookings
	$sql = 'SELECT buchung_id FROM buchungen WHERE participation_id = %d';
	$sql = sprintf($sql, $participation['participation_id']);
	$booking_ids = wrap_db_fetch($sql, '_dummy_', 'single value');
	if ($booking_ids)
		zzform_delete('buchungen', $booking_ids);
	zzform_delete('participations', $participation['participation_id']);
	return true;
}

/**
 * check if participant was registered by federation
 *
 * @param array $participation
 * @return bool
 */
function mf_qualification_federation_member($participation) {
	if (empty($participation['registration_path'])) return false;
	if (!str_starts_with($participation['registration_path'], 'federation')) return false;
	return true;
}
