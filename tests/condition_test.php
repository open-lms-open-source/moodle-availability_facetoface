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

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Facetoface availability condition tests.
 *
 * @package    availability_facetoface
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \availability_facetoface\condition
 */
class condition_test extends \advanced_testcase {

    /**
     * Setup to ensure that fixtures are loaded.
     */
    public static function setupBeforeClass(): void {
        global $CFG;
        // Load the mock info class so that it can be used.
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info_module.php');
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info_section.php');
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * @covers \availability_facetoface\condition::evaluate_availability
     */
    public function test_evaluate_availability(): void {
        global $DB;

        /** @var \mod_facetoface_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $course1 = $this->getDataGenerator()->create_course();
        $facetoface1 = $generator->create_instance(['course' => $course1->id, 'name' => 'aaa']);
        $facetoface2 = $generator->create_instance(['course' => $course1->id, 'name' => 'bbb']);
        $course2 = $this->getDataGenerator()->create_course();
        $facetoface3 = $generator->create_instance(['course' => $course2->id, 'name' => 'xxx']);

        $now = time();

        $sessiondates1 = [
            (object)[
                'timestart' => $now - HOURSECS,
                'timefinish' => $now + 1 * DAYSECS,
            ],
            (object)[
                'timestart' => $now + 20 * DAYSECS,
                'timefinish' => $now + 21 * DAYSECS,
            ],
        ];
        $session1 = $generator->create_session([
            'facetoface' => $facetoface1->id,
            'sessiondates' => $sessiondates1,
        ]);
        $sessiondates2 = [
            (object)[
                'timestart' => $now + 1 * DAYSECS,
                'timefinish' => $now + 2 * DAYSECS,
            ],
        ];
        $session2 = $generator->create_session([
            'facetoface' => $facetoface1->id,
            'sessiondates' => $sessiondates2,
        ]);
        $session3 = $generator->create_session([
            'facetoface' => $facetoface1->id,
            'sessiondates' => [],
        ]);

        $session4 = $generator->create_session([
            'facetoface' => $facetoface2->id,
            'sessiondates' => $sessiondates1,
        ]);

        $user1 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session1, $facetoface1, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_APPROVED, $user1->id, false);

        $user2 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session1, $facetoface1, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_BOOKED, $user2->id, false);

        $user3 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session1, $facetoface1, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_REQUESTED, $user3->id, false);

        $user4 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session2, $facetoface1, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_APPROVED, $user4->id, false);

        $user5 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session3, $facetoface1, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_WAITLISTED, $user5->id, false);

        $user6 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session4, $facetoface2, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_APPROVED, $user6->id, false);

        $user7 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session3, $facetoface1, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_WAITLISTED, $user7->id, false);

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 0, 0, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 1, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user1->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user1->id, $course2->id));

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user2->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user2->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 0, 0, $user2->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 1, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user2->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user2->id, $course2->id));

        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 0, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user3->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user3->id, $course2->id));

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 0, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, 0, $user4->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session2->id, 0, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user4->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0,$user4->id, $course2->id));

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, 1, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 0, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user5->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user5->id, $course2->id));

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, 1, $user7->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 1, $user5->id, $course2->id));
        $this->assertTrue(condition::evaluate_availability($session2->id, 0, 1, $user4->id, $course1->id));

        $DB->set_field('facetoface_sessions', 'datetimeknown', 0, ['id' => $session1->id]);
        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 0, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, 0, $user1->id, $course1->id));
        $DB->set_field('facetoface_sessions', 'datetimeknown', 1, ['id' => $session1->id]);

        $cm1 = get_coursemodule_from_instance('facetoface', $facetoface1->id, $course1->id, false, MUST_EXIST);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $cm1->id]);
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 0, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, 0, $user1->id, $course1->id));
    }

    public function test_upgrade_waitlistedusers(): void {
        /** @var \mod_facetoface_generator $generator */
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        $course1 = $this->getDataGenerator()->create_course();
        $facetoface1 = $generator->create_instance(['course' => $course1->id, 'name' => 'aaa']);
        $session = $generator->create_session([
            'facetoface' => $facetoface1->id,
            'sessiondates' => [],
        ]);
        $user1 = $this->getDataGenerator()->create_user();
        facetoface_user_signup($session, $facetoface1, $course1, '', MDL_F2F_BOTH, MDL_F2F_STATUS_WAITLISTED, $user1->id, false);
        $user2 = $this->getDataGenerator()->create_user();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_page');

        $page1 = $generator->create_instance(
            ['course' => $course1->id]);
        $DB->set_field('course_modules', 'availability',
            '{"op":"|","show":true,"c":[' .
            '{"type":"facetoface","id":-2,"effectivefromstart":0}]}',
            ['id' => $page1->cmid]);

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
        $pagerecord = $DB->get_record('course_modules', ['id' => $page1->cmid]);
        $this->assertSame('{"op":"|","show":true,"c":[{"type":"facetoface","id":-2,"effectivefromstart":0,"includewaitlistedusers":1}]}',
            $pagerecord->availability);
    }


    /**
     * @covers \availability_facetoface\condition::save
     */
    public function test_save(): void {
        /** @var \mod_facetoface_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $course1 = $this->getDataGenerator()->create_course();
        $facetoface1 = $generator->create_instance(['course' => $course1->id, 'name' => 'aaa']);
        $facetoface2 = $generator->create_instance(['course' => $course1->id, 'name' => 'bbb']);
        $course2 = $this->getDataGenerator()->create_course();
        $facetoface3 = $generator->create_instance(['course' => $course2->id, 'name' => 'xxx']);

        $now = time();

        $sessiondates1 = [
            (object)[
                'timestart' => $now - HOURSECS,
                'timefinish' => $now + 1 * DAYSECS,
            ],
            (object)[
                'timestart' => $now + 20 * DAYSECS,
                'timefinish' => $now + 21 * DAYSECS,
            ],
        ];
        $session1 = $generator->create_session([
            'facetoface' => $facetoface1->id,
            'sessiondates' => $sessiondates1,
        ]);
        $sessiondates2 = [
            (object)[
                'timestart' => $now + 1 * DAYSECS,
                'timefinish' => $now + 2 * DAYSECS,
            ],
        ];
        $session2 = $generator->create_session([
            'facetoface' => $facetoface1->id,
            'sessiondates' => $sessiondates2,
        ]);
        $session3 = $generator->create_session([
            'facetoface' => $facetoface1->id,
            'sessiondates' => [],
        ]);
        $session4 = $generator->create_session([
            'facetoface' => $facetoface2->id,
            'sessiondates' => $sessiondates1,
        ]);

        $structure = (object)['id' => -1 * $facetoface1->id, 'effectivefromstart' => 1, 'includewaitlistedusers' => 0];
        $condition = new condition($structure);
        $data = $condition->save();
        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertSame([
            'type' => 'facetoface',
            'id' => -1 * $facetoface1->id,
            'effectivefromstart' => 1,
            'includewaitlistedusers' => 0
        ], (array)$data);

        $structure = (object)['id' => $session2->id, 'effectivefromstart' => 0];
        $condition = new condition($structure);
        $data = $condition->save();
        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertSame([
            'type' => 'facetoface',
            'id' => (int)$session2->id,
            'effectivefromstart' => 0,
            'includewaitlistedusers' => 0
        ], (array)$data);
    }


}
