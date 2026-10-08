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
use Glpi\Plugin\Hooks;
use GlpiPlugin\Taskdrop\Calendar;

define('PLUGIN_TASKDROP_VERSION', '4.0.0-beta.1');
// Minimal GLPI version, inclusive
define("PLUGIN_TASKDROP_MIN_GLPI", "12.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_TASKDROP_MAX_GLPI", "12.1.0");

function plugin_version_taskdrop(): array
{
    return [
        'name'           => 'TaskDrop',
        'version'        => PLUGIN_TASKDROP_VERSION,
        'author'         => '<a href="https://tic.gal">TICGAL</a> and <a href="https://itsm-factory.com">ITSM Factory</a>',
        'homepage'       => 'https://tic.gal/en/project/taskdrop-easy-ticket-task-reminders-planning-glpi/',
        'license'        => 'AGPLv3+',
        'requirements'   => [
            'glpi'   => [
                'min' => PLUGIN_TASKDROP_MIN_GLPI,
                'max' => PLUGIN_TASKDROP_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Check plugin's config before activation
 */
function plugin_taskdrop_check_config($verbose = false): bool
{
    return true;
}

function plugin_init_taskdrop(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    if (!Plugin::isPluginActive('taskdrop')) {
        return;
    }

    $PLUGIN_HOOKS[Hooks::POST_SHOW_TAB]['taskdrop'] = [Calendar::class, 'showForPlanning'];
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['taskdrop'] = ['taskdrop.js'];
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['taskdrop'] = ['taskdrop.css'];
}
