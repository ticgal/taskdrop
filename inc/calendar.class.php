<?php

/*
-------------------------------------------------------------------------
 Task&drop plugin for GLPI
 Copyright (C) 2018-2026 by the TICGAL Team.

 https://github.com/ticgal/Task&drop
 -------------------------------------------------------------------------

 LICENSE

 This file is part of the Task&drop plugin.

 Task&drop plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 Task&drop plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Task&drop. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   Task&drop
 @author    the TICGAL team & ITSM Factory
 @copyright Copyright (c) 2018-2026 TICGAL team & 2024-2026 ITSM Factory
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://tic.gal & https://itsm-factory.com/
 @since     2018
 ---------------------------------------------------------------------- */

class PluginTaskdropCalendar extends CommonDBTM
{
    public static $rightname = 'calendar';

    public static function getTypeName($nb = 0)
    {
        return __('TaskDrop', 'taskdrop');
    }

    private static function formatContent(string $content, int $length = 150): array
    {
        $has_image = (bool) preg_match('/<img\b/i', $content ?? '');
        $stripped = Toolbox::stripTags($content ?? '');
        $normalized = trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');

        if ($normalized === '' && $has_image) {
            // stripTags() drops <img> tags entirely, leaving no text to render
            // for image-only content; fall back to a placeholder so the entry
            // stays visible and draggable.
            $normalized = '[' . __('Image') . ']';
        }

        $full = htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8');

        $label = $normalized;
        if (mb_strlen($normalized) > $length) {
            $label = mb_substr($normalized, 0, $length) . '…';
        }
        $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return [
            'label' => $label,
            'title' => $full,
        ];
    }

    /**
     * Planning colors come from the session, where core stores them as posted
     */
    private static function sanitizeColor(mixed $color): string
    {
        if (is_string($color) && preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $color)) {
            return $color;
        }
        return Planning::getPaletteColor('bg', 0);
    }

    private static function renderItem(string $action, int $id, string $content, string $color, string $class = ''): string
    {
        $content = self::formatContent($content);
        return "<div class='fc-event-external " . $class . "' style='max-width:400px;max-height:150px;cursor:grab;padding:2px;margin:2px;background-color: "
            . $color . ";' tid='" . $id . "' action='" . $action . "' title='" . $content['title'] . "'>"
            . $content['label'] . "</div>";
    }

    /**
     * Unplanned tasks of a user or group the current user may see and plan
     *
     * @param class-string<CommonITILTask> $itemtype
     */
    private static function renderTasks(string $itemtype, string $actor_field, int $actor_id, string $color): string
    {
        /** @var \DBmysql $DB */
        global $DB;

        $task_table   = $itemtype::getTable();
        $parent       = $itemtype::getItilObjectItemType();
        $parent_table = $parent::getTable();
        $parent_fk    = $parent::getForeignKeyField();
        $action       = $itemtype === TicketTask::class ? 'add_tickettask' : 'add_changetask';

        $where = [
            $task_table . '.state'      => Planning::TODO,
            $task_table . '.begin'      => null,
            $task_table . '.' . $actor_field => $actor_id,
            $parent_table . '.is_deleted' => 0,
            ['NOT' => [$parent_table . '.status' => array_merge($parent::getSolvedStatusArray(), $parent::getClosedStatusArray())]],
        ];
        $entities = getEntitiesRestrictCriteria($parent_table, '', '', (new $parent())->maybeRecursive());
        if (!empty($entities)) {
            $where[] = $entities;
        }

        if (!Session::haveRight(CommonITILTask::$rightname, CommonITILTask::SEEPRIVATE)) {
            $visible = [
                $task_table . '.is_private'    => 0,
                $task_table . '.users_id'      => Session::getLoginUserID(),
                $task_table . '.users_id_tech' => Session::getLoginUserID(),
            ];
            if (!empty($_SESSION['glpigroups'])) {
                $visible[$task_table . '.groups_id_tech'] = $_SESSION['glpigroups'];
            }
            $where[] = ['OR' => $visible];
        }

        $query = [
            'SELECT' => [$task_table . '.id', $task_table . '.content'],
            'FROM' => $task_table,
            'INNER JOIN' => [
                $parent_table => [
                    'FKEY' => [
                        $task_table => $parent_fk,
                        $parent_table => 'id',
                    ],
                ],
            ],
            'WHERE' => $where,
        ];

        $div = '';
        $task = new $itemtype();
        foreach ($DB->request($query) as $row) {
            // Same per-item checks as core planning and as the drop action
            if (!$task->can($row['id'], READ) || !$task->can($row['id'], UPDATE)) {
                continue;
            }
            $div .= self::renderItem($action, (int) $row['id'], (string) $row['content'], $color, 'overflow-auto event_type text-break');
        }

        return $div;
    }

    private static function renderActorTasks(string $actor_field, int $actor_id, mixed $color): string
    {
        $color = self::sanitizeColor($color);
        return self::renderTasks(TicketTask::class, $actor_field, $actor_id, $color)
            . self::renderTasks(ChangeTask::class, $actor_field, $actor_id, $color);
    }

    public static function addTask()
    {
        $div = "<h3>" . __s('Plan this task', 'taskdrop') . "</h3>";
        foreach ($_SESSION['glpi_plannings']['plannings'] ?? [] as $key => $value) {
            if (($value['display'] ?? 0) != 1) {
                continue;
            }
            $actor = explode('_', $key);
            if ($actor[0] === 'user') {
                $div .= self::renderActorTasks('users_id_tech', (int) $actor[1], $value['color'] ?? null);
            } elseif ($actor[0] === 'group') {
                if (($value['type'] ?? null) === 'group_users') {
                    foreach ($value['users'] ?? [] as $user_key => $user_value) {
                        if (($user_value['display'] ?? 0) != 1) {
                            continue;
                        }
                        $user_actor = explode('_', $user_key);
                        $div .= self::renderActorTasks('users_id_tech', (int) $user_actor[1], $user_value['color'] ?? null);
                    }
                } else {
                    $div .= self::renderActorTasks('groups_id_tech', (int) $actor[1], $value['color'] ?? null);
                }
            }
        }
        return $div;
    }

    public static function addReminder()
    {
        /** @var \DBmysql $DB */
        global $DB;

        $div = "<h3>" . __s('Planning reminder', 'taskdrop') . "</h3>";
        $visibility = Reminder::getVisibilityCriteria();
        $reminder = new Reminder();
        foreach ($_SESSION['glpi_plannings']['plannings'] ?? [] as $key => $value) {
            if (!preg_match('/^user_\d+$/', $key) || ($value['display'] ?? 0) != 1) {
                continue;
            }
            $actor = explode('_', $key);
            $color = self::sanitizeColor($value['color'] ?? null);
            $query = [
                'SELECT' => ['glpi_reminders.id', 'glpi_reminders.name'],
                'DISTINCT' => true,
                'FROM' => 'glpi_reminders',
                'WHERE' => [
                    'glpi_reminders.state' => Planning::TODO,
                    'glpi_reminders.begin' => null,
                    'glpi_reminders.users_id' => (int) $actor[1],
                ],
            ];
            if (isset($visibility['LEFT JOIN'])) {
                $query['LEFT JOIN'] = $visibility['LEFT JOIN'];
            }
            if (!empty($visibility['WHERE'])) {
                $query['WHERE'][] = $visibility['WHERE'];
            }
            foreach ($DB->request($query) as $row) {
                if (!$reminder->can($row['id'], UPDATE)) {
                    continue;
                }
                $div .= self::renderItem('add_reminder', (int) $row['id'], (string) $row['name'], $color);
            }
        }
        return $div;
    }

    public static function listTask($params)
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $options = $params['options'];
        if ($options['itemtype'] != 'Planning') {
            return;
        }
        $div = "<div id='external-events'>";
        $div .= self::addTask();
        $div .= self::addReminder();
        $div .= "</div>";

        $ajax_url = $CFG_GLPI['root_doc'] . '/plugins/taskdrop/ajax/planning.php';
        $div_json = json_encode($div, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $script = <<<JAVASCRIPT
		$(document).ready(function() {

         $('#planning_filter_content').append({$div_json});

			var Draggable = FullCalendarInteraction.Draggable;
			var containerEl = document.getElementById('external-events');
			new Draggable(containerEl, {
				itemSelector: '.fc-event-external',
			});

			GLPIPlanning.calendar.setOption('editable',true);
			GLPIPlanning.calendar.setOption('droppable',true);
			GLPIPlanning.calendar.setOption('dropAccept','.fc-event-external');
			GLPIPlanning.calendar.setOption('dragRevertDuration',0);
			GLPIPlanning.calendar.on('drop',function(dropInfo){
				$.ajax({
					url: '{$ajax_url}',
					type: 'POST',
					data:{
						action: $(dropInfo.draggedEl).attr('action'),
						start: dropInfo.date.toISOString(),
						id: $(dropInfo.draggedEl).attr('tid')
					},
					success: function(event){
                  $(dropInfo.draggedEl).remove();
						GLPIPlanning.refresh();
					},
					error: function(xhr) {
						alert('An error occured: '+ xhr.status + ' ' + xhr.statusText);
					}
				});
			});

			$('#planning_filter li.user input[type="checkbox"],#planning_filter li.group input[type="checkbox"]').on('click',function(){
				setTimeout(function(){
					$.ajax({
	               url:  '{$ajax_url}',
	               type: 'POST',
	               data: {
	                  action:  'update_task'
	               },
	               success: function(div) {
							$('#external-events').html(div);
	               }
	            });
				},500);
			});
         $('#'+GLPIPlanning.dom_id+' .fc-toolbar .fc-center h2')
            .after(
               $('<i id="refresh_planning" class="fa fa-sync pointer"></i>')
            ).after(
               $('<div id="planning_datepicker"><a data-toggle><i class="far fa-calendar-alt fa-lg pointer"></i></a>')
            );
         GLPIPlanning.initFCDatePicker();
      });
JAVASCRIPT;
        echo Html::scriptBlock($script);
    }
}
