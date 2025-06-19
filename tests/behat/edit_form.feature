@javascript @mod_quiz @quizaccess @quizaccess_sebserver
Feature: SEB Server section in quiz edit form
  In order to configure a SEB Server exam
  As a manager
  I need to be able to see the SEB Server section in the quiz edit form

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username |
      | teacher1 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: Quiz setting features a "SEB Server" section.
    Given the following "activities" exist:
      | activity | course | section | name   |
      | quiz     | C1     | 1       | Quiz 1 |
    When I am on the "Quiz 1" "quiz activity editing" page logged in as teacher1
    And I expand all fieldsets
    Then I should see "SEB Server"

  Scenario: Teachers without capabilities should not see the "SEB Server" section.
    Given the following "permission overrides" exist:
      | capability                           | permission | role           | contextlevel | reference |
      | quizaccess/sebserver:canusesebserver | Prevent    | editingteacher | System       |           |
    And the following "activities" exist:
      | activity | course | section | name   |
      | quiz     | C1     | 1       | Quiz 1 |
    When I am on the "Quiz 1" "quiz activity editing" page logged in as teacher1
    And I expand all fieldsets
    Then I should not see "SEB Server"
