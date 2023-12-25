@availability @availability_facetoface @openlms
Feature: availability_facetoface tests
  In order to control student access to activities
  As a teacher
  I need to set facetoface conditions which prevent student access

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | enablecompletion | numsections |
      | Course 1 | C1        | topics | 1                | 3           |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name  |
      | page     | C1     | P1    |
      | page     | C1     | P2    |
      | page     | C1     | P3    |

  @javascript
  Scenario: Test availability_facetoface condition
    Given I am on the "P1" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    Then "Face-to-face booking" "button" should not exist in the "Add restriction..." "dialogue"
    And I click on "Cancel" "button" in the "Add restriction..." "dialogue"

    When the following "activity" exist:
      | activity   | course | name  | idnumber |
      | facetoface | C1     | F2F1  | F2F1     |
    And the following "mod_facetoface > sessions" exist:
      | facetoface | timestart            | timefinish           |
      | F2F1       | ##8 Jan 2028 08:00## | ##8 Jan 2028 12:00## |
    And I am on the "P1" "page activity editing" page
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    Then "Face-to-face booking" "button" should exist in the "Add restriction..." "dialogue"

    When I click on "Face-to-face booking" "button" in the "Add restriction..." "dialogue"
    And I set the field "Face-to-face booking" to "F2F1 - any session"
    And I click on ".availability-item .availability-eye img" "css_element"
    And I click on "Save and return to course" "button"

    And I am on the "P2" "page activity editing" page
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Face-to-face booking" "button" in the "Add restriction..." "dialogue"
    And I set the field "Face-to-face booking" to "F2F1 - 8/01/28, 08:00"
    And I click on ".availability-item .availability-eye img" "css_element"
    And I click on "Save and return to course" "button"

    And I am on the "P3" "page activity editing" page
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Face-to-face booking" "button" in the "Add restriction..." "dialogue"
    And I set the field "Face-to-face booking" to "F2F1 - 8/01/28, 08:00"
    And I set the field "Effective from session start date" to "1"
    And I click on ".availability-item .availability-eye img" "css_element"
    And I click on "Save and return to course" "button"

    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "P1" in the "region-main" "region"
    And I should not see "P2" in the "region-main" "region"
    And I should not see "P3" in the "region-main" "region"

    When I am on the "C1" "Course" page logged in as "teacher1"
    And I click on "View all sessions" "link"
    And I click on "Attendees" "link"
    And I click on "Add/remove attendees" "link"
    And I wait until the page is ready
    And I set the field "addselect" to "Student 1"
    And I press "Add"
    And I follow "Go back"

    And I am on the "Course 1" "course" page logged in as "student1"
    Then I should see "P1" in the "region-main" "region"
    And I should see "P2" in the "region-main" "region"
    And I should not see "P3" in the "region-main" "region"
