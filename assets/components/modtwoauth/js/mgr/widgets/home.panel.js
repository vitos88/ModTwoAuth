/*
 * This file is part of ModTwoAuth.
 *
 * Copyright (C) 2026 vitos88
 *
 * ModTwoAuth is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ModTwoAuth is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ModTwoAuth; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA
 */

ModTwoAuth.panel.Home = function (config) {
    config = config || {};
    Ext.apply(config, {
        cls: 'container modtwoauth-manager-home',
        layout: 'anchor',
        hideMode: 'offsets',
        border: false,
        autoHeight: true,
        items: [{
            html: '<h2>' + _('modtwoauth') + '</h2>',
            cls: 'modtwoauth-page-title',
            style: {margin: '5px 0 15px'}
        }, {
            html: (ModTwoAuth.config && ModTwoAuth.config.securityDiagnosticsHtml)
                ? ModTwoAuth.config.securityDiagnosticsHtml
                : '<p>' + Ext.util.Format.htmlEncode(ModTwoAuth.config.securityDiagnosticsNoData || '') + '</p>',
            cls: 'modtwoauth-security-diagnostics',
            style: {margin: '0 0 10px'}
        }]
    });
    ModTwoAuth.panel.Home.superclass.constructor.call(this, config);
};
Ext.extend(ModTwoAuth.panel.Home, MODx.Panel);
Ext.reg('modtwoauth-panel-home', ModTwoAuth.panel.Home);
