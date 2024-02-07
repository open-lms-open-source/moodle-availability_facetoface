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
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

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
            ]
        ];
        $session1 = $generator->create_facetoface_session($facetoface1->id, null, $sessiondates1);
        $sessiondates2 = [
            (object)[
                'timestart' => $now + 1 * DAYSECS,
                'timefinish' => $now + 2 * DAYSECS,
            ],
        ];
        $session2 = $generator->create_facetoface_session($facetoface1->id, null, $sessiondates2);
        $session3 = $generator->create_facetoface_session($facetoface1->id);

        $session4 = $generator->create_facetoface_session($facetoface2->id, null, $sessiondates1);

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

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 0, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 1, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user1->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user1->id, $course2->id));

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user2->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user2->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 0, $user2->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 1, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, $user2->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user2->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user2->id, $course2->id));

        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, $user3->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user3->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user3->id, $course2->id));

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, $user4->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session2->id, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, $user4->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user4->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user4->id, $course2->id));

        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session2->id, 1, $user5->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session3->id, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session3->id, 1, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface2->id, 0, $user5->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user5->id, $course2->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user5->id, $course2->id));

        $DB->set_field('facetoface_sessions', 'datetimeknown', 0, ['id' => $session1->id]);
        $this->assertTrue(condition::evaluate_availability(-1 * $facetoface1->id, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability(-1 * $facetoface1->id, 1, $user1->id, $course1->id));
        $this->assertTrue(condition::evaluate_availability($session1->id, 0, $user1->id, $course1->id));
        $this->assertFalse(condition::evaluate_availability($session1->id, 1, $user1->id, $course1->id));
    }

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
            ]
        ];
        $session1 = $generator->create_facetoface_session($facetoface1->id, null, $sessiondates1);
        $sessiondates2 = [
            (object)[
                'timestart' => $now + 1 * DAYSECS,
                'timefinish' => $now + 2 * DAYSECS,
            ],
        ];
        $session2 = $generator->create_facetoface_session($facetoface1->id, null, $sessiondates2);
        $session3 = $generator->create_facetoface_session($facetoface1->id);
        $session4 = $generator->create_facetoface_session($facetoface2->id, null, $sessiondates1);

        $structure = (object)['id' => -1 * $facetoface1->id, 'effectivefromstart' => 1];
        $condition = new condition($structure);
        $data = $condition->save();
        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertSame([
            'type' => 'facetoface',
            'id' => -1 * $facetoface1->id,
            'effectivefromstart' => 1
        ], (array)$data);

        $structure = (object)['id' => $session2->id, 'effectivefromstart' => 0];
        $condition = new condition($structure);
        $data = $condition->save();
        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertSame([
            'type' => 'facetoface',
            'id' => (int)$session2->id,
            'effectivefromstart' => 0
        ], (array)$data);
    }
}
