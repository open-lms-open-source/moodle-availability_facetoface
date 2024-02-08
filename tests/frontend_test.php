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
 * Facetoface availability frontend tests.
 *
 * @package    availability_facetoface
 * @copyright  2023 Open LMS (https://www.openlms.net/)
 * @author     Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \availability_facetoface\condition
 */
class frontend_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_get_facetoface_options(): void {
        global $DB;

        /** @var \mod_facetoface_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $course1 = $this->getDataGenerator()->create_course();
        $facetoface1 = $generator->create_instance(['course' => $course1->id, 'name' => 'bbb']);
        $course2 = $this->getDataGenerator()->create_course();

        $options = frontend::get_facetoface_options($course1->id);
        $this->assertCount(1, $options);
        $this->assertSame(-1 * $facetoface1->id, $options[0]->id);
        $this->assertSame('bbb - any session', $options[0]->name);

        $options = frontend::get_facetoface_options($course2->id);
        $this->assertCount(0, $options);

        $now = time();

        $facetoface2 = $generator->create_instance(['course' => $course1->id, 'name' => 'aaa']);
        $sessiondates1 = [
            (object)[
                'timestart' => $now,
                'timefinish' => strtotime('+20 days', $now),
            ],
            (object)[
                'timestart' => strtotime('+20 days', $now),
                'timefinish' => strtotime('+21 days', $now),
            ]
        ];
        $session1 = $generator->create_facetoface_session($facetoface2->id, null, $sessiondates1);
        $sessiondates2 = [
            (object)[
                'timestart' => $now - DAYSECS,
                'timefinish' => strtotime('+20 days', $now),
            ],
        ];
        $session2 = $generator->create_facetoface_session($facetoface2->id, null, $sessiondates2);
        $session3 = $generator->create_facetoface_session($facetoface2->id);

        $options = frontend::get_facetoface_options($course1->id);
        $this->assertCount(5, $options);
        $this->assertSame(-1 * $facetoface2->id, $options[0]->id);
        $this->assertSame('aaa - any session', $options[0]->name);
        $this->assertSame((int)$session3->id, $options[1]->id);
        $this->assertSame('aaa (unknown date)', $options[1]->name);
        $this->assertSame((int)$session2->id, $options[2]->id);
        $date = userdate($now - DAYSECS, get_string('strftimedatetimeshort', 'core_langconfig'));
        $this->assertSame("aaa - $date", $options[2]->name);
        $this->assertSame((int)$session1->id, $options[3]->id);
        $date = userdate($now, get_string('strftimedatetimeshort', 'core_langconfig'));
        $this->assertSame("aaa - $date", $options[3]->name);
        $this->assertSame(-1 * $facetoface1->id, $options[4]->id);
        $this->assertSame('bbb - any session', $options[4]->name);

        $cm2 = get_coursemodule_from_instance('facetoface', $facetoface2->id, $course1->id, false, MUST_EXIST);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $cm2->id]);
        $options = frontend::get_facetoface_options($course1->id);
        $this->assertCount(1, $options);
        $this->assertSame(-1 * $facetoface1->id, $options[0]->id);
        $this->assertSame('bbb - any session', $options[0]->name);
    }

    public function test_get_javascript_strings(): void {
        $class = new class extends frontend {
            public function get_javascript_strings() {
                return parent::get_javascript_strings();
            }
        };

        $frontend = new $class();
        $strings = $frontend->get_javascript_strings();
        foreach ($strings as $string) {
            get_string($string, 'availability_facetoface');
        }
    }

    public function test_get_javascript_init_params(): void {
        /** @var \mod_facetoface_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $class = new class extends frontend {
            public function get_javascript_init_params($course, \cm_info $cm = null, \section_info $section = null): array {
                return parent::get_javascript_init_params($course, $cm, $section);
            }
        };

        $course = $this->getDataGenerator()->create_course();

        $frontend = new $class();
        $data = $frontend->get_javascript_init_params($course);
        $this->assertCount(1, $data);
        $this->assertCount(0, $data[0]);

        $facetoface1 = $generator->create_instance(['course' => $course->id, 'name' => 'bbb']);
        $data = $frontend->get_javascript_init_params($course);
        $this->assertCount(1, $data);
        $this->assertCount(1, $data[0]);
    }

    public function test_allow_add(): void {
        global $DB;

        /** @var \mod_facetoface_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $course1 = $this->getDataGenerator()->create_course();
        $facetoface1 = $generator->create_instance(['course' => $course1->id, 'name' => 'bbb']);
        $course2 = $this->getDataGenerator()->create_course();

        $class = new class extends frontend {
            public function allow_add($course, \cm_info $cm = null, \section_info $section = null): bool {
                return parent::allow_add($course, $cm, $section);
            }
        };

        $frontend = new $class();

        $this->assertTrue($frontend->allow_add($course1));
        $this->assertFalse($frontend->allow_add($course2));

        $cm1 = get_coursemodule_from_instance('facetoface', $facetoface1->id, $course1->id, false, MUST_EXIST);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $cm1->id]);
        $this->assertFalse($frontend->allow_add($course1));
    }
}
