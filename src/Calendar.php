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

namespace GlpiPlugin\Taskdrop;

use Change;
use ChangeTask;
use CommonDBTM;
use CommonITILTask;
use DateTime;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\RichText\RichText;
use Planning;
use Reminder;
use Ticket;
use TicketTask;

/**
 * Lists the pending (not scheduled) tasks and reminders of the plannings
 * displayed in the planning view, and plans the ones dropped onto the calendar.
 */
final class Calendar
{
    /** Duration, in seconds, given to an item without action time */
    public const DEFAULT_DURATION = 1800;

    /** Maximum length of the label displayed for an item */
    private const LABEL_LENGTH = 150;

    /**
     * Task itemtypes that can be dropped, with their parent ITIL itemtype
     *
     * @var array<class-string<CommonITILTask>, class-string<\CommonITILObject>>
     */
    private const TASK_ITEMTYPES = [
        TicketTask::class => Ticket::class,
        ChangeTask::class => Change::class,
    ];

    /**
     * Hook post_show_tab: displays the list of pending items in the planning view
     *
     * @param array{item?: mixed, options?: array<string, mixed>} $params
     */
    public static function showForPlanning(array $params): void
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $item = $params['item'] ?? null;
        if (
            !($item instanceof Planning)
            || ($params['options']['itemtype'] ?? null) !== Planning::class
            || !Planning::canView()
        ) {
            return;
        }

        echo TemplateRenderer::getInstance()->render('@taskdrop/planning.html.twig', [
            'ajax_url' => $CFG_GLPI['root_doc'] . '/plugins/taskdrop/ajax/planning.php',
            'list'     => self::renderList(),
        ]);
    }

    /**
     * Renders the list of pending tasks and reminders
     */
    public static function renderList(): string
    {
        return TemplateRenderer::getInstance()->render('@taskdrop/list.html.twig', [
            'tasks'     => self::getPendingTasks(),
            'reminders' => self::getPendingReminders(),
        ]);
    }

    /**
     * Plans a pending task or reminder at the given start date
     *
     * Keeps the task action time as duration, or 30 minutes when it has none.
     *
     * @param string $start Start date, as "Y-m-d H:i:s" in the GLPI timezone
     *
     * @return bool false if the item does not exist, is already planned or cannot be updated
     */
    public static function plan(string $itemtype, int $items_id, string $start): bool
    {
        if (!in_array($itemtype, self::getAllowedItemtypes(), true)) {
            throw new BadRequestHttpException();
        }
        $begin = DateTime::createFromFormat('!Y-m-d H:i:s', $start);
        if ($begin === false || $begin->format('Y-m-d H:i:s') !== $start) {
            throw new BadRequestHttpException();
        }

        $item = getItemForItemtype($itemtype);
        if (
            !$item->getFromDB($items_id)
            || !empty($item->fields['begin'])
            || !self::canPlan($item, $items_id)
        ) {
            return false;
        }

        $duration = (int) ($item->fields['actiontime'] ?? 0);
        if ($duration <= 0) {
            $duration = self::DEFAULT_DURATION;
        }
        $end = (clone $begin)->modify('+' . $duration . ' seconds');

        // Same path as moving an event in the planning: rights, trash and planning checks
        return Planning::updateEventTimes([
            'itemtype' => $itemtype,
            'items_id' => $items_id,
            'start'    => $begin->format('Y-m-d H:i:s'),
            'end'      => $end->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<class-string<CommonDBTM>>
     */
    public static function getAllowedItemtypes(): array
    {
        return [...array_keys(self::TASK_ITEMTYPES), Reminder::class];
    }

    /**
     * The user can see the item, like in the GLPI planning, and update it
     *
     * Update rights alone are not enough: they do not take private tasks into account.
     */
    private static function canPlan(CommonDBTM $item, int $items_id): bool
    {
        return $item->can($items_id, READ) && $item->can($items_id, UPDATE);
    }

    /**
     * Users and groups whose planning is displayed, with their color
     *
     * @return array{users: array<int, string>, groups: array<int, string>}
     */
    private static function getDisplayedActors(): array
    {
        $actors = ['users' => [], 'groups' => []];

        foreach ($_SESSION['glpi_plannings']['plannings'] ?? [] as $key => $planning) {
            if (!self::isDisplayed($planning)) {
                continue;
            }
            if (preg_match('/^user_(\d+)$/', (string) $key, $matches)) {
                $actors['users'][(int) $matches[1]] ??= self::getColor($planning);
            } elseif (preg_match('/^group_(\d+)$/', (string) $key, $matches)) {
                $actors['groups'][(int) $matches[1]] ??= self::getColor($planning);
            } elseif (preg_match('/^group_\d+_users$/', (string) $key)) {
                // "All users of a group" planning: one sub-planning per user
                foreach ($planning['users'] ?? [] as $user_key => $user_planning) {
                    if (self::isDisplayed($user_planning) && preg_match('/^user_(\d+)$/', (string) $user_key, $matches)) {
                        $actors['users'][(int) $matches[1]] ??= self::getColor($user_planning);
                    }
                }
            }
        }

        return $actors;
    }

    /**
     * @return list<array{itemtype: string, id: int, color: string, label: string, title: string}>
     */
    private static function getPendingTasks(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $actors = self::getDisplayedActors();
        if (count($actors['users']) === 0 && count($actors['groups']) === 0) {
            return [];
        }

        $tasks = [];
        foreach (self::TASK_ITEMTYPES as $task_itemtype => $parent_itemtype) {
            $task_table   = $task_itemtype::getTable();
            $parent_table = $parent_itemtype::getTable();

            $actor_criteria = [];
            if (count($actors['users']) > 0) {
                $actor_criteria[$task_table . '.users_id_tech'] = array_keys($actors['users']);
            }
            if (count($actors['groups']) > 0) {
                $actor_criteria[$task_table . '.groups_id_tech'] = array_keys($actors['groups']);
            }

            $where = [
                $task_table . '.state'        => Planning::TODO,
                $task_table . '.begin'        => null,
                $parent_table . '.is_deleted' => 0,
                ['OR' => $actor_criteria],
            ] + getEntitiesRestrictCriteria($parent_table);
            if ($parent_itemtype === Ticket::class) {
                $where[$parent_table . '.status'] = Ticket::getNotSolvedStatusArray();
            }

            $iterator = $DB->request([
                'SELECT'     => [
                    $task_table . '.id',
                    $task_table . '.content',
                    $task_table . '.users_id_tech',
                    $task_table . '.groups_id_tech',
                ],
                'FROM'       => $task_table,
                'INNER JOIN' => [
                    $parent_table => [
                        'FKEY' => [
                            $task_table   => $parent_itemtype::getForeignKeyField(),
                            $parent_table => 'id',
                        ],
                    ],
                ],
                'WHERE'      => $where,
                'ORDER'      => [$task_table . '.id'],
            ]);

            $task = new $task_itemtype();
            foreach ($iterator as $row) {
                if (!self::canPlan($task, (int) $row['id'])) {
                    continue;
                }
                $color = $actors['users'][$row['users_id_tech']] ?? $actors['groups'][$row['groups_id_tech']] ?? '';
                $tasks[] = self::formatItem(
                    $task_itemtype,
                    (int) $row['id'],
                    $color,
                    self::getTextFromHtml((string) $row['content']),
                );
            }
        }

        return $tasks;
    }

    /**
     * @return list<array{itemtype: string, id: int, color: string, label: string, title: string}>
     */
    private static function getPendingReminders(): array
    {
        /** @var \DBmysql $DB */
        global $DB;

        $users = self::getDisplayedActors()['users'];
        if (count($users) === 0) {
            return [];
        }

        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'users_id'],
            'FROM'   => Reminder::getTable(),
            'WHERE'  => [
                'state'    => Planning::TODO,
                'begin'    => null,
                'users_id' => array_keys($users),
            ],
            'ORDER'  => ['id'],
        ]);

        $reminders = [];
        $reminder  = new Reminder();
        foreach ($iterator as $row) {
            if (!self::canPlan($reminder, (int) $row['id'])) {
                continue;
            }
            $reminders[] = self::formatItem(Reminder::class, (int) $row['id'], $users[$row['users_id']], (string) $row['name']);
        }

        return $reminders;
    }

    /**
     * @return array{itemtype: string, id: int, color: string, label: string, title: string}
     */
    private static function formatItem(string $itemtype, int $id, string $color, string $text): array
    {
        $text  = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $label = $text;
        if (mb_strlen($label) > self::LABEL_LENGTH) {
            $label = mb_substr($label, 0, self::LABEL_LENGTH) . '…';
        }

        return [
            'itemtype' => $itemtype,
            'id'       => $id,
            'color'    => $color,
            'label'    => $label,
            'title'    => $text,
        ];
    }

    /**
     * Plain text of a rich text content, keeping block elements (paragraphs, lines...) apart
     */
    private static function getTextFromHtml(string $content): string
    {
        $content = preg_replace(
            '/<\/?(?:p|div|br|li|ul|ol|h[1-6]|tr|td|th|table|blockquote|pre)\b[^>]*>/i',
            ' $0 ',
            $content,
        ) ?? $content;

        return RichText::getTextFromHtml($content, false, true);
    }

    private static function isDisplayed(mixed $planning): bool
    {
        return is_array($planning) && filter_var($planning['display'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Planning color, only if it is a valid CSS hexadecimal color
     *
     * @param array<string, mixed> $planning
     */
    private static function getColor(array $planning): string
    {
        $color = (string) ($planning['color'] ?? '');

        return preg_match('/^#[0-9a-f]{3,8}$/i', $color) ? $color : '';
    }
}
