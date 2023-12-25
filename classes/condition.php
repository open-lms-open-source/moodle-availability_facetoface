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

namespace availability_facetoface;

/**
 * Facetoface availability condition main class.
 *
 * @package    availability_facetoface
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int ID of facetoface that this condition requires */
    protected $facetofaceorsessionid;

    /** @var int affective only after session start, value 0 or 1 */
    protected $effectivefromstart;

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     */
    public function __construct($structure) {
        $this->facetofaceorsessionid = (int)$structure->id;
        $this->effectivefromstart = (int)($structure->effectivefromstart ?? 0);
    }

    /**
     * Saves tree data back to a structure object.
     *
     * @return \stdClass Structure object (ready to be made into JSON format)
     */
    public function save() {
        $result = (object)['type' => 'facetoface'];
        $result->id = $this->facetofaceorsessionid;
        $result->effectivefromstart = $this->effectivefromstart;
        return $result;
    }

    /**
     * Determines whether a particular item is currently available
     * according to this availability condition.
     *
     * If implementations require a course or modinfo, they should use
     * the get methods in $info.
     *
     * The $not option is potentially confusing. This option always indicates
     * the 'real' value of NOT. For example, a condition inside a 'NOT AND'
     * group will get this called with $not = true, but if you put another
     * 'NOT OR' group inside the first group, then a condition inside that will
     * be called with $not = false. We need to use the real values, rather than
     * the more natural use of the current value at this point inside the tree,
     * so that the information displayed to users makes sense.
     *
     * @param bool $not Set true if we are inverting the condition
     * @param \core_availability\info $info Item we're checking
     * @param bool $grabthelot Performance hint: if true, caches information
     *   required for all course-modules, to make the front page and similar
     *   pages work more quickly (works only for current user)
     * @param int $userid User ID to check availability for
     * @return bool True if available
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid): bool {
        $available = self::evaluate_availability(
            $this->facetofaceorsessionid, $this->effectivefromstart, $userid, $info->get_course()->id);

        if ($not) {
            $available = !$available;
        }

        return $available;
    }

    /**
     * Check if available.
     *
     * NOTE: this is static to make this easy to test.
     *
     * @param int $facetofaceorsessionid
     * @param int $effectivefromstart
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function evaluate_availability(int $facetofaceorsessionid, int $effectivefromstart, int $userid, int $courseid): bool {
        global $DB;

        if ($facetofaceorsessionid == 0) {
            return false;
        }

        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
            'now' => time(),
            'approved' => 50, // MDL_F2F_STATUS_APPROVED - do not include facetoface/lib.php here for performance reasons.
        ];

        if ($effectivefromstart) {
            $datesjoin = "JOIN {facetoface_sessions_dates} fsd ON fsd.sessionid = fs.id AND fsd.timestart < :now AND fsd.timestart > 0";
        } else {
            $datesjoin = "";
        }

        $where = [];
        if ($facetofaceorsessionid < 0) {
            $params['id'] = -1 * $facetofaceorsessionid;
            $where[] = 'f.id = :id';
        } else {
            $params['id'] = $facetofaceorsessionid;
            $where[] = 'fs.id = :id';
        }
        $where = implode(' AND ', $where);

        $sql = "SELECT DISTINCT f.id
                  FROM {facetoface_sessions} fs
                  JOIN {facetoface} f ON f.id = fs.facetoface AND f.course = :courseid
                  JOIN {facetoface_signups} fsu ON fsu.sessionid = fs.id AND fsu.userid = :userid
                  JOIN {facetoface_signups_status} fsus
                       ON fsus.signupid = fsu.id AND fsus.superceded = 0 AND fsus.statuscode >= :approved
                  $datesjoin
                 WHERE $where";

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Gets the actual facetoface id or session id for the condition.
     *
     * @param \core_availability\info $info Info about context cm
     * @return int negative is facetoface id, positive integer is session id
     */
    protected function get_facetoface_or_session_id(\core_availability\info $info) {
        return $this->facetofaceorsessionid;
    }

    /**
     * Describes the availability condition for current user.
     *
     * @param $full
     * @param $not
     * @param \core_availability\info $info
     * @return string
     */
    public function get_description($full, $not, \core_availability\info $info) {
        global $DB;
        $course = $info->get_course();
        $context = \context_course::instance($course->id);
        $facetofaceorsessionid = $this->get_facetoface_or_session_id($info);

        if ($this->effectivefromstart) {
            $effective = ' ' . get_string('requires_effectivefromstart', 'availability_facetoface');
        } else {
            $effective = '';
        }

        if ($facetofaceorsessionid > 0) {
            $id = $facetofaceorsessionid;
            $session = $DB->get_record('facetoface_sessions', ['id' => $id]);
            if ($session) {
                $facetoface = $DB->get_record('facetoface', ['id' => $session->facetoface, 'course' => $course->id]);
                if ($facetoface) {
                    $a = format_string($facetoface->name, true, ['context' => $context]);
                    if ($session->datetimeknown) {
                        $min = $DB->get_field('facetoface_sessions_dates', 'MIN(timestart)', ['sessionid' => $id]);
                        $a .= ' - ' . userdate($min, get_string('strftimedatetimeshort', 'core_langconfig'));
                    } else {
                        $a .= ' - ' . get_string('unknowndate', 'mod_facetoface');
                    }
                    if ($not) {
                        return get_string('requires_notsession', 'availability_facetoface', $a) . $effective;
                    } else {
                        return get_string('requires_session', 'availability_facetoface', $a) . $effective;
                    }
                }
            }

        } else if ($facetofaceorsessionid < 0) {
            $id = -1 * $facetofaceorsessionid;
            $facetoface = $DB->get_record('facetoface', ['id' => $id, 'course' => $course->id]);
            if ($facetoface) {
                $a = format_string($facetoface->name, true, ['context' => $context]);
                if ($not) {
                    return get_string('requires_notfacetoface', 'availability_facetoface', $a) . $effective;
                } else {
                    return get_string('requires_facetoface', 'availability_facetoface', $a) . $effective;
                }
            }
        }

        return get_string('error_invalidconfiguration', 'availability_facetoface');
    }

    protected function get_debug_string() {
        return '#' . $this->facetofaceorsessionid . ':' . $this->effectivefromstart;
    }

    /**
     * Always map facetoface or session.
     *
     * @param int $restoreid The restore Id.
     * @param int $courseid The ID of the course.
     * @param \base_logger $logger The logger being used.
     * @param string $name Name of item being restored.
     * @param \base_task $task The task being performed.
     *
     * @return bool
     */
    public function include_after_restore($restoreid, $courseid, \base_logger $logger,
                                          $name, \base_task $task) {
        return true;
    }

    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        global $DB;

        if ($this->facetofaceorsessionid == 0) {
            // Value is missing.
            return false;
        } else if ($this->facetofaceorsessionid < 0) {
            $fid = -1 * $this->facetofaceorsessionid;
            $rec = \restore_dbops::get_backup_ids_record($restoreid, 'facetoface', $fid);
            if (!$rec || !$rec->newitemid) {
                // If we are on the same course (e.g. duplicate) then we can just
                // use the existing one.
                if ($DB->record_exists('facetoface',
                    array('id' => $fid, 'course' => $courseid))) {
                    return false;
                }
                // Otherwise it's a warning.
                $this->facetofaceorsessionid = 0;
                $logger->process('Restored item (' . $name .
                    ') has availability condition on facetoface that was not restored',
                    \backup::LOG_WARNING);
            } else {
                $this->facetofaceorsessionid = -1 * (int)$rec->newitemid;
            }
        } else {
            $sid = $this->facetofaceorsessionid;
            $rec = \restore_dbops::get_backup_ids_record($restoreid, 'facetoface_session', $sid);
            if (!$rec || !$rec->newitemid) {
                // If we are on the same course (e.g. duplicate) then we can just
                // use the existing one.
                $session = $DB->get_record('facetoface_sessions', ['id' => $sid]);
                if ($session) {
                    if ($DB->record_exists('facetoface', ['id' => $session->facetoface, 'course' => $courseid])) {
                        return false;
                    }
                }
                // Otherwise it's a warning.
                $this->facetofaceorsessionid = 0;
                $logger->process('Restored item (' . $name .
                    ') has availability condition on facetoface that was not restored',
                    \backup::LOG_WARNING);
            } else {
                $this->facetofaceorsessionid = (int)$rec->newitemid;
            }
        }

        return true;
    }

    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'facetoface' && $this->facetofaceorsessionid < 0) {
            if (-1 * $this->facetofaceorsessionid == $oldid) {
                $this->facetofaceorsessionid = -1 * $newid;
                return true;
            } else {
                return false;
            }

        } else if ($table === 'facetoface_sessions' && $this->facetofaceorsessionid > 0) {
            if ($this->facetofaceorsessionid == $oldid) {
                $this->facetofaceorsessionid = $newid;
                return true;
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    public function is_applied_to_user_lists() {
        // Not supported, the user listing cannot be considered permanent enough.
        return false;
    }

    /**
     * Returns a JSON object which corresponds to a condition of this type.
     *
     * Intended for unit testing, as normally the JSON values are constructed
     * by JavaScript code.
     *
     * @param int $facetofaceorsessionid
     * @param int $effectivefromstart
     * @return \stdClass Object representing condition
     */
    public static function get_json(int $facetofaceorsessionid, int $effectivefromstart = 0) {
        $result = (object)['type' => 'facetoface'];
        $result->id = $facetofaceorsessionid;
        $result->effectivefromstart = $effectivefromstart;
        return $result;
    }
}
