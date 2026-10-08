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

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Taskdrop\Calendar;

Session::checkLoginUser();

if (!Planning::canView()) {
    throw new AccessDeniedHttpException();
}

switch ($_REQUEST['action'] ?? '') {
    case 'list':
        header('Content-Type: text/html; charset=UTF-8');
        echo Calendar::renderList();
        break;

    case 'plan':
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new BadRequestHttpException();
        }
        $itemtype = $_POST['itemtype'] ?? null;
        $items_id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $start    = $_POST['start'] ?? null;
        if (!is_string($itemtype) || $items_id === false || !is_string($start)) {
            throw new BadRequestHttpException();
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => Calendar::plan($itemtype, $items_id, $start)]);
        break;

    default:
        throw new BadRequestHttpException();
}
