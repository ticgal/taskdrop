# Task'n'drop
<!-- changelog-format: 2 -->
The Task'n'drop plugin for GLPI

## [Unreleased]
_Pending release; the plugin version is still 3.0.2._

### Bugs
- Users could read and reschedule tasks and reminders they had no access to, including from the simplified interface.
- The panel listed tasks and reminders from other entities, private tasks and other users' private reminders.
- A task or reminder text could run a script in the Planning page of whoever displayed it.
- A link opened by a logged-in user could reschedule a task or reminder without their consent.
- A crafted planning colour could run a script in the user's own Planning page.
- The panel now lists only the tasks and reminders the current user is allowed to reschedule.
- Tasks of solved or closed changes no longer appear in the panel.
- Dropping a reminder on the calendar no longer fails with an error.

## [3.0.2] - 2026-09-29
### Bugs
- Fix "Undefined array key color" warning when the planning filter "all users of a group" was active

## [3.0.1] - 2026-09-08
### Bugs
- Fix tasks with line breaks

## [3.0.0] - 2026-01-21
### Features
- GLPI 11 support

## [2.1.1] - 2025-02-04
### Bugs
- Fix sanitize errors

## [2.1.0] - 2024-10-22
### Features
- Ticket tasks of non solved tickets
- Non finished tasks
- Non scheduled tasks

## [2.0.0] - 2022-07-28
### Features
- GLPI 10 Compatibility #10340
### Bugs
- PHP Warning when selecting all users of a group at Planning view #6955
- Fix planning colors for groups over 15 users by @cconard96 in #12339

## [1.3.0] - 2022-07-27
### Features
- Added support for change tasks
