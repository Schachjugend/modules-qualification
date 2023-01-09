/**
 * qualification module
 * SQL queries
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2023 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


-- qualification_quotacalc_dvm_event_id --
SELECT IF((
SELECT COUNT(*) FROM regionalgruppen
WHERE regionalgruppen.series_category_id = events.series_category_id), (
SELECT COUNT(*) FROM participations
LEFT JOIN teams USING (team_id)
WHERE participations.event_id IN (SELECT event_id
FROM events tournaments
LEFT JOIN categories series
ON tournaments.series_category_id = series.category_id
WHERE series.main_category_id = events.series_category_id
AND IFNULL(tournaments.event_year, YEAR(tournaments.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin)))
AND participations.usergroup_id = /*_ID usergroups spieler_*/
AND participations.teilnahme_status = "Teilnehmer"
AND (ISNULL(team_id) OR teams.meldung = "teiloffen" OR teams.meldung = "komplett")
), NULL) AS quota
FROM events
WHERE event_id = %d

-- qualification_quotacalc_dvm_event --
SELECT IF((
SELECT COUNT(*) FROM regionalgruppen
WHERE regionalgruppen.series_category_id = events.series_category_id), (
SELECT COUNT(*) FROM participations
LEFT JOIN teams USING (team_id)
WHERE participations.event_id IN (SELECT event_id
FROM events tournaments
LEFT JOIN categories series
ON tournaments.series_category_id = series.category_id
WHERE series.main_category_id = events.series_category_id
AND IFNULL(tournaments.event_year, YEAR(tournaments.date_begin)) = IFNULL(events.event_year, YEAR(events.date_begin)))
AND participations.usergroup_id = /*_ID usergroups spieler_*/
AND participations.teilnahme_status = "Teilnehmer"
AND (ISNULL(team_id) OR teams.meldung = "teiloffen" OR teams.meldung = "komplett")
), NULL) AS quota
FROM events
WHERE identifier = '%s'
