<?php 

/**
 * qualification module
 * quota calculation for German Youth Championships (DEM)
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @author Falco Nogatz <fnogatz@gmail.com>
 * @copyright Copyright © 2013, 2016-2024, 2026 Gustaf Mossakowski
 * @copyright Copyright © 2016 Falco Nogatz
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Übersicht Kontingente
 *
 * @param array $vars
 */
function mod_qualification_quotacalcdem($vars, $settings, $event) {
	global $tournaments;

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

	$data['year'] = $vars[0];
	
	// gewähltes Jahr mit Turnier? Nein = kein Link
	$sql = 'SELECT event_id FROM events
		WHERE IFNULL(event_year, YEAR(date_begin)) = %d AND series_category_id = %d';
	$sql = sprintf($sql, $data['year'], $data['category_id']);
	$data['tournament'] = wrap_db_fetch($sql);

	$page['breadcrumbs'][]['title'] = 'Kontingent';
	$page['dont_show_h1'] = true;
	$page['title'] = $data['series_short'].' '.$data['year'].', Kontingent';

	$landesverbaende = mf_tournaments_federations('code');

	$parameters = ['younger', 'alternate', 'younger_alternate'];
	$series = [];
	foreach ($parameters as $type) {
		if (!array_key_exists($type, $data))
			$series[$type] = '';
		else
			$series[$type] = wrap_category_id('reihen/dem/'.$data[$type]);
	}

	$sql = 'SELECT event_id, IFNULL(event_year, YEAR(date_begin)) AS year, "Letztes Jahr" AS type
			, "A" AS sequence, IFNULL(event_year, YEAR(date_begin)) - alter_max AS min_year, identifier, event
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE series_category_id = %d
		AND IFNULL(event_year, YEAR(date_begin)) = %d -1
		UNION SELECT event_id, IFNULL(event_year, YEAR(date_begin)) AS year, "Letztes Jahr" AS type
			, "B" AS sequence, IFNULL(event_year, YEAR(date_begin)) - alter_max AS min_year, identifier, event
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE series_category_id = %d
		AND IFNULL(event_year, YEAR(date_begin)) = %d -1
		UNION SELECT event_id, IFNULL(event_year, YEAR(date_begin)) AS year, "Vorletztes Jahr" AS type
			, "A" AS sequence, IFNULL(event_year, YEAR(date_begin)) - alter_max AS min_year, identifier, event
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE series_category_id = %d
		AND IFNULL(event_year, YEAR(date_begin)) = %d -2
		UNION SELECT event_id, IFNULL(event_year, YEAR(date_begin)) AS year, "Vorletztes Jahr" AS type
			, "B" AS sequence, IFNULL(event_year, YEAR(date_begin)) - alter_max AS min_year, identifier, event
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE series_category_id = %d
		AND IFNULL(event_year, YEAR(date_begin)) = %d -2
		UNION SELECT event_id, IFNULL(event_year, YEAR(date_begin)) AS year, "Letztes Jahr, jüngere AK" AS type
			, "A" AS sequence, IFNULL(event_year, YEAR(date_begin)) - alter_max AS min_year, identifier, event
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE series_category_id = %d
		AND IFNULL(event_year, YEAR(date_begin)) = %d -1
		UNION SELECT event_id, IFNULL(event_year, YEAR(date_begin)) AS year, "Letztes Jahr, jüngere AK" AS type
			, "B" AS sequence, IFNULL(event_year, YEAR(date_begin)) - alter_max AS min_year, identifier, event
		FROM events
		LEFT JOIN tournaments USING (event_id)
		WHERE series_category_id = %d
		AND IFNULL(event_year, YEAR(date_begin)) = %d -1
		ORDER BY sequence DESC
	';
	$sql = sprintf($sql
		, $data['category_id'], $data['year']
		, $series['alternate'], $data['year']
		, $data['category_id'], $data['year']
		, $series['alternate'], $data['year']
		, $series['younger'], $data['year']
		, $series['younger_alternate'], $data['year']
	);
	$tournaments = wrap_db_fetch($sql, 'type');
	if (empty($series['younger'])) {
		if (count($tournaments) !== 2) {
			$data['no_data'] = true;
			$page['text'] = wrap_template('quota-calculation-dem', $data);
			$page['status'] = 404;
			return $page;
		}
	} elseif (count($tournaments) !== 3) {
		$data['no_data'] = true;
		$page['text'] = wrap_template('quota-calculation-dem', $data);
		$page['status'] = 404;
		return $page;
	}

	$sex = substr($data['altersklasse'], -1) === 'w' ? 'female' : 'male';

	$lvs = calc($tournaments, $sex);
	if (empty($data['distribution'])) {
		$data['distribution_missing'] = true;
		$page['text'] = wrap_template('quota-calculation-dem', $data);
		return $page;
	}
	$distribution = wrap_setting_list($data['distribution']);

	// enough data?
	$first_lv = reset($lvs);
	$enough = true;
	foreach ($tournaments as $tournament) {
		if (array_key_exists($tournament['event_id'], $first_lv)) continue;
		$enough = false;
		break;
	}

	if (!$enough) {
		$data['no_data'] = true;
		$page['text'] = wrap_template('quota-calculation-dem', $data);
		$page['status'] = 404;
		return $page;
	}

	usort($lvs, "cmp");

	$vorjahr = $tournaments['Letztes Jahr']['event_id'];
	$vorvorjahr = $tournaments['Vorletztes Jahr']['event_id'];
	$pos = 0;

	$existing_data_for_lv = [];
	foreach ($lvs as $lvid => $lv) {
		$bp_rechnung = [];
		if (count($lv['bp']) >= 2) {
			foreach ($lv['bp'] as $bp) {
				$bp_rechnung[] = ['bp' => $bp];
			}
		}
		$data['lines'][] = [
			'landesverband' => $landesverbaende[$lv['id']]['contact_abbr'],
			'landesverband_lang' => $landesverbaende[$lv['id']]['contact'],
			'plaetze' => $distribution[$pos],
			'gesamtpunkte' => $lv['gp'],
			'jahrespunkte' => $lv['jp'],
			'jahrespunkte_vorjahr' => array_key_exists($vorjahr, $lv) ? $lv[$vorjahr]['jdp'] : NULL,
			'jahrespunkte_vorvorjahr' => array_key_exists($vorvorjahr, $lv) ? $lv[$vorvorjahr]['jdp'] : NULL,
			'bonuspunkte' => count($lv['bp']) != 0 ? array_sum($lv['bp']) : NULL,
			'bonuspunkte_rechnung' => count($lv['bp']) >= 2 ? $bp_rechnung : NULL,
			'bester_platz' => array_key_exists($vorjahr, $lv) ? $lv[$vorjahr]['bester_platz'] : NULL
		];
		$existing_data_for_lv[] = $lv['id'];
		$pos++;
	}
	foreach ($landesverbaende as $landesverband) {
		if (in_array($landesverband['code'], $existing_data_for_lv)) continue;
		$data['lines'][] = [
			'landesverband' => $landesverband['contact_abbr'],
			'landesverband_lang' => $landesverband['contact'],
			'plaetze' => $distribution[$pos],
			'gesamtpunkte' => 0,
			'jahrespunkte' => 0
		];		
	}
	foreach ($tournaments as $tournament) {
		$tournament['title'] = $tournament['type'];
		switch ($tournament['type']) {
		case 'Letztes Jahr':
			$data['tournaments'][] = $tournament;
			$tournament['title'] .= ', jüngere Jahrgänge';
			$tournament['table'] = 'jung';
			$data['tournaments'][] = $tournament;
			break;
		case 'Letztes Jahr, jüngere AK':
			$tournament['title'] .= ', ältester Jahrgang';
			$tournament['table'] = 'alt';
			$data['tournaments'][] = $tournament;
			break;
		default:
			$data['tournaments'][] = $tournament;
			break;
		}
	}

	wrap_setting('number_format', 'two-decimal-places');
	$page['text'] = wrap_template('quota-calculation-dem', $data);
	return $page;
}

/**
 * Vergleichsfunktion zwischen zwei Landesverbänden
 *
 * @param array $a Daten des ersten Landesverbands
 * @param array $b Daten des zweiten Landesverbands
 * @global $tournaments
 * @return int -1: erster Landesverband ist vorne, 1: zweiter Landesverband ist vorne
 */
function cmp($a, $b) {
	global $tournaments;

	if ($a['gp'] > $b['gp']) return -1;
	if ($a['gp'] < $b['gp']) return 1;

	$letztes_turnier = $tournaments['Letztes Jahr']['event_id'];
	$a_jdp = $a[$letztes_turnier]['jdp'];
	$b_jdp = $b[$letztes_turnier]['jdp'];
	if ($a_jdp > $b_jdp) return -1;
	if ($a_jdp < $b_jdp) return 1;

	// Platzierungen vergleichen
	if ($a[$letztes_turnier]['bester_platz'] < $b[$letztes_turnier]['bester_platz']) return -1;
	if ($a[$letztes_turnier]['bester_platz'] > $b[$letztes_turnier]['bester_platz']) return 1;
	
	die("Gleiche Punktzahlen!");
}

function calc($tournaments, $sex) {
	$event_ids = [];
	foreach ($tournaments as $tournament) {
		$event_ids[] = $tournament['event_id'];
	}

	/**
	 * Alle Teilnehmenden der gleichen Altersklasse des Vorjahrs und des
     * Vorvorjahres.
     */
	$query1 = 'SELECT
			tabellenstaende.event_id,
			persons.first_name,
			persons.last_name,
			LEFT(contacts_identifiers.identifier, 1) AS lv,
			YEAR(persons.date_of_birth) AS geburtsjahr,
			tabellenstaende_wertungen.wertung,
			tabellenstaende.platz_no
		FROM tabellenstaende 
		LEFT JOIN events USING (event_id)
		LEFT JOIN tournaments USING (event_id)
		INNER JOIN tabellenstaende_wertungen
			ON tabellenstaende_wertungen.tabellenstand_id = tabellenstaende.tabellenstand_id
			AND tabellenstaende_wertungen.wertung_category_id = /*_ID categories turnierwertungen/pkt _*/
			AND tabellenstaende.runde_no = tournaments.runden
		INNER JOIN persons
			ON persons.person_id = tabellenstaende.person_id
			AND IF(IFNULL(events.event_year, YEAR(events.date_begin)) < 2016 AND series_category_id IN (/*_ID categories reihen/dem/dem-u10 _*/, /*_ID categories reihen/dem/dem-u12 _*/), persons.sex = "%s", 1 = 1)
		LEFT JOIN participations
			ON participations.event_id = tabellenstaende.event_id
			AND participations.contact_id = persons.contact_id
			AND participations.usergroup_id = /*_ID usergroups spieler _*/
		LEFT JOIN contacts_identifiers
			ON contacts_identifiers.contact_id = participations.club_contact_id
			AND contacts_identifiers.current = "yes"
		WHERE tabellenstaende.event_id IN (%s)
		ORDER BY lv ASC, wertung DESC
	';
	$query1 = sprintf($query1, $sex, implode(',', $event_ids));

	/**
	 * Jahreswertungspunkte JWP: Durchschnittspunktzahlen
	 * der Teilnehmenden je Landesverband.
	 */
	$query2 = 'SELECT
			event_id,
			lv,
			(SUM(wertung) / COUNT(wertung)) AS jdp,
			COUNT(wertung) AS tn,
			NULL AS bp,
			MIN(platz_no) AS bester_platz
		FROM ('.$query1.') AS result1
		GROUP BY
			event_id,
			lv
		ORDER BY
			lv ASC
	';

	$lvs = [];
	$lvs = wrap_db_fetch($query2, ['lv', 'event_id']);
	foreach (array_keys($lvs) as $lv) {
		foreach ($lvs[$lv] as $event_id => $line) {
			$lvs[$lv][$event_id]['bp'] = [];
		}
	}

	/**
	 * Liste der besten 5 Teilnehmenden des jüngeren Jahrgangs im Vorjahr.
	 */
	$anzahl = 5;
	$query3 = '
		SELECT
			event_id,
			first_name,
			last_name,
			lv,
			geburtsjahr,
			platz_no
		FROM ('.$query1.') AS result1
		WHERE
			event_id = '.$tournaments['Letztes Jahr']['event_id'].' AND
			geburtsjahr >= '.($tournaments['Letztes Jahr']['min_year'] + 1).'
		ORDER BY
			platz_no ASC
		LIMIT '.$anzahl.'
	';

	/**
	 * Addiere Sonderpunkte.
	 */
	$sonderpunkte = wrap_db_fetch($query3, '_dummy_', 'numeric');
	$bp = count($sonderpunkte) * 0.1;
	foreach ($sonderpunkte as $spieler) {
		$lvs[$spieler['lv']][$spieler['event_id']]['bp'][] = $bp;
		$bp -= 0.1;
	}

	if (!empty($tournaments['Letztes Jahr, jüngere AK'])) {
		/**
		 * Liste der besten 10 Teilnehmenden des älteren Jahrgangs der vorigen
		 * Altersklasse im Vorjahr.
		 */
		$query4 = '
			SELECT
				event_id,
				first_name,
				last_name,
				lv,
				geburtsjahr,
				platz_no
			FROM ('.$query1.') AS result1
			WHERE
				event_id = '.$tournaments['Letztes Jahr, jüngere AK']['event_id'].' AND
				geburtsjahr = '.$tournaments['Letztes Jahr, jüngere AK']['min_year'].'
			ORDER BY
				platz_no ASC
			LIMIT 10
		';

		/**
		 * Addiere Sonderpunkte.
		 */
		$sonderpunkte = wrap_db_fetch($query4, '_dummy_', 'numeric');
		$bp = count($sonderpunkte) * 0.1;
		foreach ($sonderpunkte as $spieler) {
			$lvs[$spieler['lv']][$spieler['event_id']]['bp'][] = $bp;
			$bp -= 0.1;
		}
	}

	/**
	 * Berechne JWP und WP
	 */
	foreach ($lvs as $lvid => $lv) {
		$lvs[$lvid]['id'] = $lvid;

		$vorjahr = $tournaments['Letztes Jahr']['event_id'];
		$vorvorjahr = $tournaments['Vorletztes Jahr']['event_id'];
		$lvs[$lvid]['jp'] = 2 * (array_key_exists($vorjahr, $lv) ? $lv[$vorjahr]['jdp'] : 0)
			+ (array_key_exists($vorvorjahr, $lv) ? $lv[$vorvorjahr]['jdp'] : 0);

		// Sonderpunkte addieren
		$lvs[$lvid]['bp'] = [];
		foreach ($lv as $event_id => $event) {
			$lvs[$lvid]['bp'] = array_merge($lvs[$lvid]['bp'], $event['bp']);
		}

		$lvs[$lvid]['gp'] = $lvs[$lvid]['jp'] + array_sum($lvs[$lvid]['bp']);
	}

	return $lvs;
}
