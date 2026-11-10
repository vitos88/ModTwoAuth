<?php
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

/** Adds an opt-in, report-only CSP. It deliberately does not enforce a policy by default. */
$event = (string) ($modx->event->name ?? '');
if (!in_array($event, ['OnWebPageInit', 'OnManagerPageInit'], true)) return;
if (!(bool) $modx->getOption('modtwoauth_csp_report_only', null, true)) return;
$policy = trim((string) $modx->getOption('modtwoauth_csp_policy', null, "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; upgrade-insecure-requests"));
if ($policy === '' || headers_sent()) return;
// Prevent header injection; directives are administrator-controlled configuration.
$policy = str_replace(["\r", "\n"], '', $policy);
header('Content-Security-Policy-Report-Only: ' . $policy, false);
