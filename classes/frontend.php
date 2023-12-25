<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace availability_facetoface;

/**
 * Front-end class for facetoface restrictions.
 *
 * @package    availability_facetoface
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * Gets all facetoface restriction options for the course.
     *
     * @param int $courseid Course id
     * @return array Array of facetoface restriction options
     */
    public static function get_facetoface_options(int $courseid): array {
        global $DB, $CFG;
        require_once("$CFG->dirroot/mod/facetoface/lib.php");

        $sql = "SELECT m.*, cm.id AS cmid
                  FROM {course_modules} cm, {modules} md, {facetoface} m
                 WHERE cm.course = :courseid AND cm.instance = m.id
                       AND md.name = 'facetoface' AND md.id = cm.module";
        $facetofaces = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        if (!$facetofaces) {
            return [];
        }

        $context = \context_course::instance($courseid);
        foreach ($facetofaces as $k => $facetoface) {
            $facetofaces[$k]->name = format_string($facetoface->name, true, ['context' => $context]);
        }
        \core_collator::asort_objects_by_property($facetofaces, 'name', \core_collator::SORT_STRING);

        $result = [];
        foreach ($facetofaces as $facetoface) {
            $sessions = facetoface_get_sessions($facetoface->id);
            $result[] = (object)[
                'id' => -1 * (int)$facetoface->id,
                'name' => $facetoface->name . ' - ' . get_string('anysession', 'availability_facetoface'),
            ];
            foreach ($sessions as $session) {
                if ($session->datetimeknown) {
                    $date = reset($session->sessiondates);
                    $name = $facetoface->name . ' - ' . userdate($date->timestart, get_string('strftimedatetimeshort', 'core_langconfig'));
                } else {
                    $name = $facetoface->name . ' ' . get_string('unknowndate', 'mod_facetoface');
                }
                $result[] = (object)[
                    'id' => (int)$session->id,
                    'name' => $name,
                ];
            }
        }

        return $result;
    }

    /**
     * Gets a list of string identifiers (in the plugin's language file) that
     * are required in JavaScript for this plugin. The default returns nothing.
     *
     * @return array Array of required string identifiers
     */
    protected function get_javascript_strings() {
        return ['effectivefromstart'];
    }

    /**
     * Gets additional parameters for the plugin's initInner function.
     *
     * @param \stdClass $course Course object
     * @param \cm_info|null $cm Course-module currently being edited (null if none)
     * @param \section_info|null $section Section currently being edited (null if none)
     * @return array Array of parameters for the JavaScript function
     */
    protected function get_javascript_init_params($course, \cm_info $cm = null, \section_info $section = null): array {
        $options = self::get_facetoface_options($course->id);
        return [$options];
    }

    /**
     * Decides whether this plugin should be available in a given course.
     * Returns true if there is any facetoface instance in course.
     *
     * @param \stdClass $course Course object
     * @param \cm_info|null $cm Course-module currently being edited (null if none)
     * @param \section_info|null $section Section currently being edited (null if none)
     * @return bool
     */
    protected function allow_add($course, \cm_info $cm = null, \section_info $section = null): bool {
        global $DB;

        return $DB->record_exists('facetoface', ['course' => $course->id]);
    }
}
