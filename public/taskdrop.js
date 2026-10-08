/**
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

/* global CFG_GLPI, glpi_toast_error, __ */

/**
 * Drag & drop of the pending tasks and reminders onto the GLPI planning.
 *
 * The planning is a Vue application since GLPI 12 and its FullCalendar instance is not
 * exposed, so the drop target is resolved from the calendar DOM (data-date / data-time).
 * Pointer events are used instead of HTML5 drag & drop to also support touch screens.
 */
window.GlpiPluginTaskdrop = (() => {
    'use strict';

    /** Distance, in pixels, the pointer has to move before a drag starts */
    const DRAG_THRESHOLD = 5;
    /** Distance, in pixels, from the edge of a calendar scroller that triggers the auto-scroll */
    const SCROLL_EDGE = 40;
    /** Auto-scroll step, in pixels per frame */
    const SCROLL_STEP = 10;
    /** Delay before reloading the list if the filter change request is not detected */
    const RELOAD_FALLBACK_DELAY = 2000;

    let container = null;
    let ajax_url = '';
    let drag = null;
    let reload_pending = false;
    let reload_requested_at = 0;
    let reload_timeout = null;
    let request_observer = null;

    function init(element) {
        if (!element) {
            return;
        }
        container = element;
        ajax_url = element.dataset.ajaxUrl;

        waitForPlanning().then((filter_content) => {
            filter_content.append(container);
            container.classList.remove('d-none');
            container.addEventListener('pointerdown', onPointerDown);
            watchFilters(filter_content.closest('#planning_filter'));
        });
    }

    /**
     * The planning Vue application is mounted asynchronously, after this script runs
     *
     * @returns {Promise<HTMLElement>} the filters panel content
     */
    function waitForPlanning() {
        return new Promise((resolve) => {
            const planning = document.getElementById('planning_container');
            if (!planning) {
                return;
            }
            const find = () => (planning.querySelector('.fc') ? planning.querySelector('#planning_filter_content') : null);
            const found = find();
            if (found) {
                resolve(found);
                return;
            }
            const observer = new MutationObserver(() => {
                const element = find();
                if (element) {
                    observer.disconnect();
                    resolve(element);
                }
            });
            observer.observe(planning, {childList: true, subtree: true});
        });
    }

    /**
     * Reloads the list when a planning is shown, hidden or recolored
     *
     * The core saves the filter in session with its own request, then reloads the events:
     * the list is reloaded once a core planning request started after the change completes,
     * with a delayed fallback.
     */
    function watchFilters(filter_panel) {
        filter_panel.addEventListener('change', (event) => {
            if (!event.target.matches('input[type="checkbox"], input[type="color"]')) {
                return;
            }
            reload_pending = true;
            reload_requested_at = performance.now();
            clearTimeout(reload_timeout);
            reload_timeout = setTimeout(reloadList, RELOAD_FALLBACK_DELAY);
        });

        if (request_observer === null && 'PerformanceObserver' in window) {
            request_observer = new PerformanceObserver((entries) => {
                const core_request_done = entries.getEntries().some((entry) => {
                    if (entry.startTime < reload_requested_at) {
                        return false;
                    }
                    const url = new URL(entry.name, window.location.href);
                    return url.pathname === `${CFG_GLPI.root_doc}/ajax/planning.php`;
                });
                if (reload_pending && core_request_done) {
                    reloadList();
                }
            });
            request_observer.observe({type: 'resource'});
        }
    }

    function reloadList() {
        reload_pending = false;
        clearTimeout(reload_timeout);
        fetch(`${ajax_url}?action=list`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        }).then((response) => (response.ok ? response.text() : Promise.reject(response)))
            .then((html) => {
                container.innerHTML = html;
            })
            .catch(() => {});
    }

    function onPointerDown(event) {
        const item = event.target.closest('.taskdrop-item');
        if (item === null || drag !== null || !event.isPrimary || event.button !== 0) {
            return;
        }
        drag = {
            item: item,
            pointer_id: event.pointerId,
            start_x: event.clientX,
            start_y: event.clientY,
            x: event.clientX,
            y: event.clientY,
            ghost: null,
            highlight: null,
            target: null,
            scroll: null,
            scroll_frame: null,
        };
        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp);
        document.addEventListener('pointercancel', stopDrag);
        document.addEventListener('keydown', onKeyDown);
    }

    function onPointerMove(event) {
        if (event.pointerId !== drag.pointer_id) {
            return;
        }
        drag.x = event.clientX;
        drag.y = event.clientY;
        if (drag.ghost === null) {
            if (Math.hypot(drag.x - drag.start_x, drag.y - drag.start_y) < DRAG_THRESHOLD) {
                return;
            }
            startDrag();
        }
        event.preventDefault();
        drag.ghost.style.transform = `translate(${drag.x - drag.offset_x}px, ${drag.y - drag.offset_y}px)`;
        updateTarget();
    }

    function onPointerUp(event) {
        if (event.pointerId !== drag.pointer_id) {
            return;
        }
        const {item, ghost, target} = drag;
        stopDrag();
        if (ghost !== null && target !== null) {
            plan(item, target.start);
        }
    }

    function onKeyDown(event) {
        if (event.key === 'Escape') {
            stopDrag();
        }
    }

    function startDrag() {
        const rect = drag.item.getBoundingClientRect();
        drag.offset_x = drag.start_x - rect.left;
        drag.offset_y = drag.start_y - rect.top;

        drag.ghost = drag.item.cloneNode(true);
        drag.ghost.classList.add('taskdrop-ghost');
        drag.ghost.style.width = `${rect.width}px`;
        drag.highlight = document.createElement('div');
        drag.highlight.classList.add('taskdrop-highlight');
        document.body.append(drag.ghost, drag.highlight);

        drag.item.classList.add('taskdrop-dragging');
        document.body.classList.add('taskdrop-is-dragging');
    }

    function stopDrag() {
        if (drag === null) {
            return;
        }
        document.removeEventListener('pointermove', onPointerMove);
        document.removeEventListener('pointerup', onPointerUp);
        document.removeEventListener('pointercancel', stopDrag);
        document.removeEventListener('keydown', onKeyDown);
        if (drag.scroll_frame !== null) {
            cancelAnimationFrame(drag.scroll_frame);
        }
        drag.ghost?.remove();
        drag.highlight?.remove();
        drag.item.classList.remove('taskdrop-dragging');
        document.body.classList.remove('taskdrop-is-dragging');
        drag = null;
    }

    function updateTarget() {
        drag.target = getDropTarget(drag.x, drag.y);
        const style = drag.highlight.style;
        if (drag.target === null) {
            style.display = 'none';
        } else {
            const rect = drag.target.rect;
            style.display = 'block';
            style.left = `${rect.left}px`;
            style.top = `${rect.top}px`;
            style.width = `${rect.right - rect.left}px`;
            style.height = `${rect.bottom - rect.top}px`;
        }
        autoScroll();
    }

    /**
     * Calendar slot under the pointer
     *
     * @returns {{start: string, rect: {left: number, top: number, right: number, bottom: number}}|null}
     *          start date as "Y-m-d H:i:s", in the GLPI timezone like the calendar dates
     */
    function getDropTarget(x, y) {
        const elements = document.elementsFromPoint(x, y);
        const find = (selector) => elements.find((element) => element.matches(selector)) ?? null;
        if (find('#planning_container .fc-view-harness') === null) {
            return null;
        }

        // Week and day views
        const column = find('.fc-timegrid-col[data-date]');
        const slot = find('.fc-timegrid-slot-lane[data-time]');
        if (column !== null && slot !== null) {
            return {
                start: `${column.dataset.date} ${slot.dataset.time}`,
                rect: intersect(column.getBoundingClientRect(), slot.getBoundingClientRect()),
            };
        }

        // Timeline view: only the date is set, the item keeps its actors
        const timeline_slot = find('.fc-timeline-slot-lane[data-date]');
        if (timeline_slot !== null) {
            const lane = find('.fc-timeline-lane');
            const rect = timeline_slot.getBoundingClientRect();
            return {
                start: toDateTime(timeline_slot.dataset.date),
                rect: lane === null ? rect : intersect(rect, lane.getBoundingClientRect()),
            };
        }

        // Month view and all-day row: start of the working day
        const day = find('.fc-daygrid-day[data-date]');
        if (day !== null) {
            return {
                start: toDateTime(day.dataset.date),
                rect: day.getBoundingClientRect(),
            };
        }

        return null;
    }

    function intersect(rect_a, rect_b) {
        return {
            left: Math.max(rect_a.left, rect_b.left),
            top: Math.max(rect_a.top, rect_b.top),
            right: Math.min(rect_a.right, rect_b.right),
            bottom: Math.min(rect_a.bottom, rect_b.bottom),
        };
    }

    /**
     * @param {string} value FullCalendar date ("2026-10-08" or "2026-10-08T08:00:00")
     * @returns {string} "Y-m-d H:i:s", a date alone starts with the working day
     */
    function toDateTime(value) {
        const [date, time] = value.replace(/Z$/, '').split('T');
        return `${date} ${normalizeTime(time ?? CFG_GLPI.planning_begin ?? '00:00:00')}`;
    }

    function normalizeTime(time) {
        const [hours = '0', minutes = '0', seconds = '0'] = String(time).substring(0, 8).split(':');
        return [hours, minutes, seconds].map((part) => part.padStart(2, '0')).join(':');
    }

    /**
     * Scrolls the calendar while the pointer is close to the edge of a scrollable area
     */
    function autoScroll() {
        const scroller = document.elementsFromPoint(drag.x, drag.y).find((element) => {
            return element.classList.contains('fc-scroller') && element.scrollHeight > element.clientHeight;
        });
        let delta = 0;
        if (scroller !== undefined) {
            const rect = scroller.getBoundingClientRect();
            if (drag.y < rect.top + SCROLL_EDGE) {
                delta = -SCROLL_STEP;
            } else if (drag.y > rect.bottom - SCROLL_EDGE) {
                delta = SCROLL_STEP;
            }
        }
        drag.scroll = delta === 0 ? null : {scroller: scroller, delta: delta};

        if (drag.scroll !== null && drag.scroll_frame === null) {
            const step = () => {
                if (drag === null || drag.scroll === null) {
                    if (drag !== null) {
                        drag.scroll_frame = null;
                    }
                    return;
                }
                drag.scroll.scroller.scrollTop += drag.scroll.delta;
                updateTarget();
                drag.scroll_frame = requestAnimationFrame(step);
            };
            drag.scroll_frame = requestAnimationFrame(step);
        }
    }

    function plan(item, start) {
        item.classList.add('taskdrop-pending');
        fetch(ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({
                action: 'plan',
                itemtype: item.dataset.itemtype,
                id: item.dataset.id,
                start: start,
            }),
        }).then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((data) => {
                if (data.success !== true) {
                    return Promise.reject(data);
                }
                item.remove();
            })
            .catch(() => {
                glpi_toast_error(__('An error occurred'));
                reloadList();
            })
            .finally(refreshCalendar);
    }

    /**
     * Uses the refresh button of the core planning, that also displays the session messages
     */
    function refreshCalendar() {
        document.querySelector('#planning_container .fc-toolbar-title .ti-refresh')?.closest('button')?.click();
    }

    return {
        init: init,
    };
})();
