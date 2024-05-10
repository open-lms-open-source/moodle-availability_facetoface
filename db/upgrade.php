<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * @package    availability_facetoface
 * @copyright  2024 Open LMS (https://www.openlms.net/)
 * @author     Farhan Karmali
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_availability_facetoface_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2024011800) {

        $sql = "SELECT cm.* 
                  FROM {course_modules} cm
                 WHERE ".$DB->sql_like('availability', '?');
        $records = $DB->get_records_sql($sql, ['%facetoface%']);
        foreach ($records as $record) {
            $data = json_decode($record->availability, true);
            foreach ($data['c'] as &$item) {
                if (isset($item['type']) && $item['type'] === 'facetoface' && !isset($item['includewaitlistedusers'])) {
                    $item['includewaitlistedusers'] = 1;
                }
            }
            $record->availability = json_encode($data);
            $DB->update_record('course_modules', $record);
        }
    }

    return true;
}
