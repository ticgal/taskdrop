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

// The planning panel is a central-interface feature
Session::checkCentralAccess();

// Write actions must only run on POST so the kernel CSRF check applies
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!isset($_POST["action"])) {
    exit;
}

$action = $_POST["action"];

if ($action == "update_task") {
    $div  = PluginTaskdropCalendar::addTask();
    $div .= PluginTaskdropCalendar::addReminder();
    echo $div;
    exit;
}

$itemtypes = [
    'add_tickettask' => TicketTask::class,
    'add_changetask' => ChangeTask::class,
    'add_reminder'   => Reminder::class,
];
if (!isset($itemtypes[$action])) {
    http_response_code(400);
    exit;
}

$id    = is_numeric($_POST["id"] ?? null) ? (int) $_POST["id"] : 0;
$start = is_string($_POST["start"] ?? null) ? strtotime($_POST["start"]) : false;
if ($id <= 0 || $start === false) {
    http_response_code(400);
    exit;
}

$item = new $itemtypes[$action]();
if (!$item->can($id, READ) || !$item->can($id, UPDATE)) {
    http_response_code(403);
    exit;
}

if ($item instanceof CommonITILTask) {
    $actiontime = $item->fields['actiontime'] > 0 ? (int) $item->fields['actiontime'] : 1800;
    $input = [
        'id'            => $id,
        'begin'         => date("Y-m-d H:i:s", $start),
        'end'           => date("Y-m-d H:i:s", $start + $actiontime),
        'actiontime'    => $actiontime,
        'users_id_tech' => $item->fields['users_id_tech'],
    ];
} else {
    $input = [
        'id'         => $id,
        'begin'      => date("Y-m-d H:i:s", $start),
        'end'        => date("Y-m-d H:i:s", $start + 1800),
        'is_planned' => 1,
    ];
}

if (!$item->update($input)) {
    http_response_code(500);
    exit;
}

echo json_encode([
    'itemtype' => $item::class,
    'items_id' => $id,
    'start'    => $input['begin'],
    'end'      => $input['end'],
]);
