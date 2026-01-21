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

include("../../../inc/includes.php");

header('Content-Type: application/json');

if (!isset($_REQUEST["action"])) {
    exit;
}

global $DB, $CFG_GLPI;

if ($_REQUEST["action"] == "add_tickettask") {
    $query = [
        'SELECT' => [
            'glpi_tickettasks.*',
            'glpi_tickets.name',
        ],
        'FROM' => 'glpi_tickettasks',
        'LEFT JOIN' => [
            'glpi_tickets' => [
                'FKEY' => [
                    'glpi_tickets' => 'id',
                    'glpi_tickettasks' => 'tickets_id',
                ]
            ]
        ],
        'WHERE' => [
            'glpi_tickettasks.id' => $_REQUEST["id"],
        ]
    ];

    $req = $DB->request($query);
    $row = $req->current();

    $end = ($row['actiontime'] > 0 ? date("Y-m-d H:i", strtotime($_REQUEST['start'] . " +" . $row['actiontime'] . " seconds")) : date("Y-m-d H:i", strtotime($_REQUEST['start'] . " +30 minutes")));

    $event = [
        'title' => $row['name'],
        'content' => $row['content'],
        'start' => date("Y-m-d H:i", strtotime($_REQUEST['start'])),
        'end' => $end,
        'url' => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $row['tickets_id'],
        'itemtype' => 'TicketTask',
        'items_id' => $_REQUEST["id"],
        'state' => $row['state']
    ];

    if ($row['actiontime'] > 0) {
        $actiontime = $row['actiontime'];
    } else {
        $actiontime = 1800;
    }

    $tickettask = new TicketTask();
    $tickettask->getFromDB($_REQUEST["id"]);
    $tickettask->update([
        'id' => $_REQUEST["id"],
        'begin' => date("Y-m-d H:i", strtotime($_REQUEST['start'])),
        'end' => $end,
        'actiontime' => $actiontime,
        'users_id_tech' => $tickettask->fields['users_id_tech']
    ]);

    echo json_encode($event);
} elseif ($_REQUEST["action"] == "add_changetask") {
    $query = [
        'SELECT' => [
            'glpi_changetasks.*',
            'glpi_changes.name',
        ],
        'FROM' => 'glpi_changetasks',
        'LEFT JOIN' => [
            'glpi_changes' => [
                'FKEY' => [
                    'glpi_changes' => 'id',
                    'glpi_changetasks' => 'changes_id',
                ]
            ]
        ],
        'WHERE' => [
            'glpi_changetasks.id' => $_REQUEST["id"],
        ]
    ];

    $req = $DB->request($query);
    $row = $req->current();

    $end = ($row['actiontime'] > 0 ? date("Y-m-d H:i", strtotime($_REQUEST['start'] . " +" . $row['actiontime'] . " seconds")) : date("Y-m-d H:i", strtotime($_REQUEST['start'] . " +30 minutes")));

    $event = [
        'title' => $row['name'],
        'content' => $row['content'],
        'start' => date("Y-m-d H:i", strtotime($_REQUEST['start'])),
        'end' => $end,
        'url' => $CFG_GLPI['root_doc'] . '/front/change.form.php?id=' . $row['changes_id'],
        'itemtype' => 'ChangeTask',
        'items_id' => $_REQUEST["id"],
        'state' => $row['state']
    ];

    if ($row['actiontime'] > 0) {
        $actiontime = $row['actiontime'];
    } else {
        $actiontime = 1800;
    }

    $changetask = new ChangeTask();
    $changetask->getFromDB($_REQUEST["id"]);
    $changetask->update([
        'id' => $_REQUEST["id"],
        'begin' => date("Y-m-d H:i", strtotime($_REQUEST['start'])),
        'end' => $end,
        'actiontime' => $actiontime,
        'users_id_tech' => $changetask->fields['users_id_tech']
    ]);

    echo json_encode($event);
} elseif ($_REQUEST["action"] == "update_task") {

    $div  = PluginTaskdropCalendar::addTask();
    $div .= PluginTaskdropCalendar::addReminder();
    echo $div;
} else if ($_REQUEST["action"] == "add_reminder") {
    $end = date("Y-m-d H:i", strtotime($_REQUEST['start'] . " +30 minutes"));
    $DB->update(
        'glpi_reminders',
        [
            'begin' => $_REQUEST['start'],
            'end' => $end,
            'is_planned' => 1
        ],
        [
            'id' => $_REQUEST["id"]
        ]
    );
    $event = [
        'start' => $_REQUEST['start'],
        'end' => $end,
        'url' => $CFG_GLPI['root_doc'] . '/front/reminder.form.php?id=' . $_REQUEST["id"],
        'itemtype' => 'Reminder',
        'items_id' => $_REQUEST["id"],
        'state' => 1
    ];
    echo json_encode($event);
}