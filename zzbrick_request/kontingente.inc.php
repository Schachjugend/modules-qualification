<?php 

/**
 * qualification module
 * Kontingente kopieren
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2018-2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * Kontingente kopieren
 *
 * @param array $vars
 */
function mod_qualification_kontingente_kopieren($vars, $settings, $event) {
	if (count($vars) !== 2) return false;
	parse_str($event['series_parameter'], $parameter);
	if (empty($parameter['kontingent'])) return false;
	
	$page['dont_show_h1'] = true;
	$page['breadcrumbs'][] = '<a href="../kontingente">Kontingente</a>';
	$page['breadcrumbs'][] = 'Kopieren';

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
			WHERE kontingent_category_id = %d
			AND main_events.event_id = %d';
		$sql = sprintf($sql
			, $vars[0]
			, wrap_category_id('kontingente/lv')
			, $_POST['event_id']
		);
		$data = wrap_db_fetch($sql, 'kontingent_id');
		if (!$data) {
			$event['no_copy'] = true;
		}

		$event['copied'] = 0;
		foreach ($data as $id => $line) {
			$values = [];
			$values['action'] = 'insert';
			$values['ids'] = ['federation_contact_id', 'event_id', 'kontingent_category_id'];
			unset($line['kontingent_id']);
			$values['POST'] = $line;
			$values['POST']['kontingent_category_id'] = wrap_category_id('kontingente/lv');
			$ops = zzform_multi('kontingente', $values);
			if (!$ops['id']) {
				wrap_error(sprintf('Fehler beim Kopieren von Kontingent ID %d', $id));
			} else {
				$event['copied'] ++;
			}
		}
	}
	$page['text'] = wrap_template('kontingente-kopieren', $event);
	return $page;
}
