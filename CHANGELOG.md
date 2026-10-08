# Task'n'drop
The Task'n'drop plugin for GLPI

## 4.0.0-beta.1 - 2026-10-08
### Feature
- GLPI 12 support
- Drag'n'drop rebuilt for the GLPI 12 planning, now a Vue application without the former FullCalendar globals. Works in the week, day, month and timeline views, and on touch screens
- The calendar scrolls while dragging an item close to its edge
- Escape cancels a drag
### Security
- Planning a task or reminder requires the right to update it. Any logged in user could plan any task or reminder from its id
- Only the tasks of the active entities are listed, and only the tasks and reminders the user can see and update: private tasks require the right to see them, like in the GLPI planning
- Strict validation of the planning request (item type, id and date)
### Bugfix
- Items are planned through the GLPI planning API, like moving an event: planning conflict warnings are displayed and items in the trash are rejected
- Dates are sent without timezone: the dropped item could be planned at a shifted hour depending on the server timezone
- "All users of a group" plannings listed the group tasks instead of the tasks of its users
- External calendars were handled as groups
- An item displayed by several plannings was listed several times
- Tasks of deleted tickets and changes were listed
- Paragraphs of a task were merged in its label
### Changed
- Class moved to src/ with the GlpiPlugin\Taskdrop namespace
- List rendered with Twig, JavaScript and CSS moved to public/
- Removed the CSRF compliance flag, GLPI 12 validates CSRF with request headers
- Unique Composer autoloader suffix: GLPI 12 does not load a plugin whose autoloader class collides with another plugin's
- Removed the unused chillerlan/php-qrcode dependency, the obsolete Travis configuration and a migration script of another plugin left in tools/
- PHPStan configuration works both locally and in CI, without generic ignores

## 3.0.1 - 2026-09-08
### Bugfix
- Fix tasks with line breaks

## 3.0.0 - 2026-01-21
### Feature
- GLPI 11 support

## 2.1.1 - 2025-02-04
### Bugfixes
- Fix sanitize errors

## 2.1.0 - 2024-10-22
### Features
- Ticket tasks of non solved tickets
- Non finished tasks
- Non scheduled tasks

## 2.0.0 - 2022-07-28
### Features
- GLPI 10 Compatibility #10340
### Bugfixes
- PHP Warning when selecting all users of a group at Planning view #6955
  - Fix planning colors for groups over 15 users by @cconard96 in #12339

## 1.3.0 - 2022-07-27
### Features
- Added support for change tasks
