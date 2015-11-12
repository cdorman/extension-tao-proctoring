/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA ;
 *
 */
define(['ui/breadcrumbs'], function(breadcrumbs){
    
    /**
     * Wrap the generic breadcrumbs component into a very specialized
     * 
     * @param {JQyery} $container
     * @param {type} crumbs
     * @returns {unresolved}
     */
    return function breadcrumbFactory($container, crumbs){
        return breadcrumbs({
            breadcrumbs : crumbs,
            renderTo: $container.find('.header'),
            replace: true,
            cls : 'action-bar horizontal-action-bar'
        });
    };
});
