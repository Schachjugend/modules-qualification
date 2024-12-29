<?php 

/**
 * qualification module
 * Kontingente kopieren
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2018-2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Kontingente kopieren
 *
 * @param array $vars
 */
function mod_qualification_make_kontingente_kopieren($vars, $settings, $event) {
	if (count($vars) !== 2) return false;
	
	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][] = ['title' => 'Kontingente', 'url_path' => '../kontingente/'];
	$page['breadcrumbs'][]['title'] = 'Kopieren';

	$sql = 'SELECT main_events.event_id, main_events.identifier
		FROM kontingente
		JOIN events USING (event_id)
		LEFT JOIN categories
			ON events.series_category_id = categories.category_id
		LEFT JOIN events main_events
			ON main_events.series_category_id = categories.main_category_id
			AND IFNULL(events.event_year, YEAR(events.date_begin)) = IFNULL(main_events.event_year, YEAR(main_events.date_begin))
		WHERE categories.main_category_id = %d
		ORDER BY main_events.date_begin DESC';
	$sql = sprintf($sql, $event['series_category_id']);
	$event['series'] = wrap_db_fetch($sql, 'event_id');
	if (!$event['series']) {
		$event['no_data'] = true;
	}
	if (!empty($_POST['event_id'])) {
		$sql = 'SELECT kontingent_id, kontingent, federation_contact_id, new_events.event_id
			FROM kontingente
			LEFT JOIN events USING (event_id)
			LEFT JOIN categories series
				ON events.series_category_id = series.category_id
			LEFT JOIN events main_events
				ON main_events.series_category_id = series.main_category_id
				AND IFNULL(events.event_year, YEAR(events.date_begin)) = IFNULL(main_events.event_year, YEAR(main_events.date_begin))
			LEFT JOIN categories main_series
				ON series.main_category_id = main_series.category_id
			LEFT JOIN events new_events
				ON new_events.series_category_id = series.category_id
				AND YEAR(new_events.date_begin) = %d
			WHERE kontingent_category_id = /*_ID categories kontingente/lv _*/
			AND main_events.event_id = %d';
		$sql = sprintf($sql
			, $vars[0]
			, $_POST['event_id']
		);
		$data = wrap_db_fetch($sql, 'kontingent_id');
		if (!$data) $event['no_copy'] = true;

		$event['copied'] = 0;
		foreach ($data as $id => $line) {
			unset($line['kontingent_id']);
			$line['kontingent_category_id'] = wrap_category_id('kontingente/lv');
			$result = zzform_insert('kontingente', $line);
			if ($result) $event['copied']++;
		}
	}
	$page['text'] = wrap_template('kontingente-kopieren', $event);
	return $page;
}
