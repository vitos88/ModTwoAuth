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

use MODX\Revolution\modExtraManagerController;
use ModTwoAuth\Localization;

/**
 * The home manager controller for ModTwoAuth.
 *
 */
class ModTwoAuthHomeManagerController extends modExtraManagerController
{
    /** @var ModTwoAuth\ModTwoAuth $ModTwoAuth */
    public $ModTwoAuth;


    /**
     *
     */
    public function initialize()
    {
        $this->ModTwoAuth = $this->modx->services->get('ModTwoAuth');
        parent::initialize();
    }


    /**
     * @return array
     */
    public function getLanguageTopics()
    {
        return ['modtwoauth:default'];
    }


    /**
     * @return bool
     */
    public function checkPermissions()
    {
        return true;
    }


    /**
     * @return null|string
     */
    public function getPageTitle()
    {
        return $this->modx->lexicon('modtwoauth');
    }


    /**
     * @return void
     */
    public function loadCustomCssJs()
    {
        $this->addCss($this->ModTwoAuth->config['cssUrl'] . 'mgr/main.css');
        $this->addJavascript($this->ModTwoAuth->config['jsUrl'] . 'mgr/modtwoauth.js');
        $this->addJavascript($this->ModTwoAuth->config['jsUrl'] . 'mgr/misc/utils.js');
        $this->addJavascript($this->ModTwoAuth->config['jsUrl'] . 'mgr/misc/combo.js');
        $this->addJavascript($this->ModTwoAuth->config['jsUrl'] . 'mgr/widgets/home.panel.js');
        $this->addJavascript($this->ModTwoAuth->config['jsUrl'] . 'mgr/sections/home.js');

        $this->ModTwoAuth->config['securityDiagnosticsHtml'] = $this->getSecurityDiagnosticsHtml();
        $this->ModTwoAuth->config['securityDiagnosticsNoData'] = Localization::get(
            $this->modx,
            'modtwoauth_diag_no_data'
        );
        $this->addHtml('<script type="text/javascript">
        ModTwoAuth.config = ' . json_encode($this->ModTwoAuth->config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';
        ModTwoAuth.config.connector_url = "' . $this->ModTwoAuth->config['connectorUrl'] . '";
        Ext.onReady(function() {MODx.load({ xtype: "modtwoauth-page-home"});});
        </script>');
    }


    /**
     * @return string
     */
    public function getTemplateFile()
    {
        return $this->ModTwoAuth->config['corePath'] . 'templates/home.tpl';
    }

    private function getSecurityDiagnosticsHtml()
    {
        $lex = function ($key, $placeholders = []) {
            return $this->modx->lexicon($key, $placeholders);
        };
        $checks = [];
        require_once MODX_CORE_PATH . 'components/modtwoauth/src/SecurityCrypto.php';
        $keyFile = \ModTwoAuth\SecurityCrypto::keyPath($this->modx);
        $keyOk = $keyFile !== '' && is_file($keyFile) && is_readable($keyFile);
        $checks[] = [$keyOk, $lex('modtwoauth_diag_key'), $keyOk ? $lex('modtwoauth_diag_key_ok') : $lex('modtwoauth_diag_key_fail')];
        $sodium = extension_loaded('sodium');
        $checks[] = [$sodium, $lex('modtwoauth_diag_sodium'), $sodium ? $lex('modtwoauth_diag_sodium_ok') : $lex('modtwoauth_diag_sodium_fail')];
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $checks[] = [$https, $lex('modtwoauth_diag_https'), $https ? $lex('modtwoauth_diag_https_ok') : $lex('modtwoauth_diag_https_fail')];
        $secureCookie = (bool) ini_get('session.cookie_secure');
        $checks[] = [$secureCookie, $lex('modtwoauth_diag_cookie_secure'), $secureCookie ? $lex('modtwoauth_diag_cookie_secure_ok') : $lex('modtwoauth_diag_cookie_secure_fail')];
        $httpOnly = (bool) ini_get('session.cookie_httponly');
        $checks[] = [$httpOnly, $lex('modtwoauth_diag_cookie_httponly'), $httpOnly ? $lex('modtwoauth_diag_cookie_httponly_ok') : $lex('modtwoauth_diag_cookie_httponly_fail')];
        $csp = (bool) $this->modx->getOption('modtwoauth_csp_report_only', null, true);
        $checks[] = [$csp, $lex('modtwoauth_diag_csp'), $csp ? $lex('modtwoauth_diag_csp_ok') : $lex('modtwoauth_diag_csp_fail')];
        try {
            $table = $this->modx->getTableName('ModTwoAuth\\Model\\TwoAuthUserSetting');
            $stmt = $this->modx->prepare("SELECT COUNT(*) FROM {$table} WHERE provider = 'totp' AND secret IS NOT NULL AND secret <> '' AND secret NOT LIKE 'm2aenc:v1:%'");
            $stmt->execute();
            $legacySecrets = (int) $stmt->fetchColumn();
            $checks[] = [$legacySecrets === 0, $lex('modtwoauth_diag_migration'), $legacySecrets === 0 ? $lex('modtwoauth_diag_migration_ok') : $lex('modtwoauth_diag_migration_fail', ['count' => $legacySecrets])];
        } catch (\Throwable $e) {
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR, '[ModTwoAuth] Security diagnostics failed: ' . $e->getMessage());
            $checks[] = [false, $lex('modtwoauth_diag_migration'), $lex('modtwoauth_diag_migration_error')];
        }
        foreach (['modTwoAuthSessionGuard', 'modTwoAuthFrontendSessions'] as $pluginName) {
            $plugin = $this->modx->getObject('modPlugin', ['name' => $pluginName]);
            $active = $plugin && (int) $plugin->get('disabled') === 0;
            $isFrontend = $pluginName === 'modTwoAuthFrontendSessions';
            $labelKey = $isFrontend ? 'modtwoauth_diag_plugin_frontend' : 'modtwoauth_diag_plugin_guard';
            $detailKey = $isFrontend ? 'modtwoauth_diag_plugin_frontend_ok' : 'modtwoauth_diag_plugin_guard_ok';
            $checks[] = [$active, $lex($labelKey), $active ? $lex($detailKey) : $lex('modtwoauth_diag_plugin_fail')];
        }
        $okCount = count(array_filter($checks, static function ($check) { return !empty($check[0]); }));
        $html = '<section class="m2a-dashboard">'
            . '<header class="m2a-dashboard__header"><div><div class="m2a-eyebrow">' . htmlspecialchars($lex('modtwoauth_diag_eyebrow'), ENT_QUOTES, 'UTF-8') . '</div><h2>' . htmlspecialchars($lex('modtwoauth_diag_title'), ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p>' . htmlspecialchars($lex('modtwoauth_diag_subtitle'), ENT_QUOTES, 'UTF-8') . '</p></div>'
            . '<div class="m2a-summary"><span class="m2a-summary__number">' . $okCount . '</span><span>' . htmlspecialchars($lex('modtwoauth_diag_summary', ['total' => count($checks)]), ENT_QUOTES, 'UTF-8') . '</span></div></header>'
            . '<div class="m2a-checks">';
        foreach ($checks as $check) {
            $label = htmlspecialchars($check[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $detail = htmlspecialchars($check[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $isOk = !empty($check[0]);
            $status = $isOk ? $lex('modtwoauth_diag_status_ok') : $lex('modtwoauth_diag_status_warn');
            $html .= '<article class="m2a-check ' . ($isOk ? 'is-ok' : 'is-warning') . '">'
                . '<span class="m2a-check__icon" aria-hidden="true">' . ($isOk ? '&#10003;' : '!') . '</span>'
                . '<div class="m2a-check__body"><div class="m2a-check__title">' . $label . '</div><p>' . $detail . '</p></div>'
                . '<span class="m2a-check__status">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span></article>';
        }
        $html .= '</div><footer class="m2a-dashboard__footer"><strong>' . htmlspecialchars($lex('modtwoauth_diag_important'), ENT_QUOTES, 'UTF-8') . '</strong><span>' . htmlspecialchars($lex('modtwoauth_diag_footer'), ENT_QUOTES, 'UTF-8') . '</span></footer></section>';
        return $html;
    }
}
