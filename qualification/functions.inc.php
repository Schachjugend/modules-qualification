<?php

/**
 * qualification module
 * common functions
 *
 * Part of »Zugzwang Project«
 * https://www.zugzwang.org/modules/qualification
 *
 * @author Gustaf Mossakowski <gustaf@koenige.org>
 * @copyright Copyright © 2024 Gustaf Mossakowski
 * @license http://opensource.org/licenses/lgpl-3.0.html LGPL-3.0
 */


/**
 * get data for registration type participation
 *
 * @param string $identifier
 * @return array
 */
function mf_qualification_registration_category($identifier) {
	$sql = 'SELECT category_id, category, description
	    FROM categories
	    WHERE parameters LIKE "%%&url_path=%s%%"';
	$sql = sprintf($sql, wrap_db_escape($identifier));
	$category = wrap_db_fetch($sql);
	return $category;
}

/**
 * get quotas for events for a federation 
 * 
 * @param int $federation_contact_id
 * @param array $event_ids
 * @return array
 */
function mf_qualification_quotas($federation_contact_id, $event_ids) {
	$sql = 'SELECT event_id, kontingent_id
			, kontingent, anmerkung, category, category_short
		FROM kontingente
		LEFT JOIN categories
			ON kontingente.kontingent_category_id = categories.category_id
		WHERE federation_contact_id = %d
		AND event_id IN (%s)
		ORDER BY event_id, categories.sequence';
	$sql = sprintf($sql, $federation_contact_id, implode(',', $event_ids));
	return wrap_db_fetch($sql, ['event_id', 'kontingent_id']);
}

/**
 * get participants for events per condition
 * 
 * @param string $where WHERE condition
 * @param int $event_id
 * @param array $event_ids
 * @return array
 */
function mf_qualification_participants($where, $event_id, $event_ids) {
	$event_ids[] = $event_id;

	$sql = 'SELECT event_id, participations.participation_id
			, contact_id, contact, contacts.identifier AS contact_identifier
			, t_verein
			, t_dwz, t_elo, t_fidetitel
			, qualification, date_of_birth
			, YEAR(date_of_birth) AS birth_year
			, IF(sex = "female", "W", IF(sex = "male", "M", "")) AS geschlecht
			, usergroups.identifier AS group_identifier
			, role
			, (SELECT SUM(betrag) FROM buchungen WHERE buchungen.participation_id = participations.participation_id) AS buchung
			, participations_categories.participation_category_id
			, SUBSTRING_INDEX(
				IFNULL(SUBSTRING_INDEX(SUBSTRING_INDEX(registration.parameters, "&alias=", -1), "&", 1), registration.path), "/", -1
			) AS registration_path
		FROM participations
		LEFT JOIN persons USING (contact_id)
		LEFT JOIN contacts USING (contact_id)
		LEFT JOIN usergroups USING (usergroup_id)
		LEFT JOIN participations_categories
			ON participations_categories.participation_id = participations.participation_id
			AND participations_categories.type_category_id = /*_ID CATEGORIES participations/registration _*/
		LEFT JOIN categories registration
			ON participations_categories.category_id = registration.category_id
		WHERE %s
		AND event_id IN (%s)
		AND status_category_id IN (
			/*_ID CATEGORIES participation-status/subscribed _*/,
			/*_ID CATEGORIES participation-status/verified _*/,
			/*_ID CATEGORIES participation-status/participant _*/
		)
		AND (ISNULL(usergroups.parameters) OR usergroups.parameters NOT LIKE "%%&present=0%%")';
	$sql = sprintf($sql
		, $where
		, implode(',', $event_ids)
	);
	$participations = wrap_db_fetch($sql, 'participation_id');
	$contact_ids = [];
	foreach ($participations as $participation) {
		$contact_ids[] = $participation['contact_id'];
	}
	$addresses = mf_contacts_addresses($contact_ids);
	$contactdetails = mf_contacts_contactdetails($contact_ids);

	foreach ($participations as $participation_id => &$participation) {
		$participation['addresses'] = $addresses[$participation['contact_id']] ?? [];
		$participation += $contactdetails[$participation['contact_id']] ?? [];
		if ($participation['registration_path']) // old participants might not have this
			$participation[str_replace('-', '_', $participation['registration_path'])] = true;
	}

	return $participations;
}

/**
 * get federation or category for list
 *
 * @param string $identifier
 * @return array
 */
function mf_qualification_list($identifier) {
	wrap_include_files('functions', 'clubs');
	$federation = mf_clubs_federation($identifier);
	if ($federation) return [$federation, NULL];

	$category = mf_qualification_registration_category($identifier);
	if ($category) return [NULL, $category];
	
	wrap_quit(404);
}
