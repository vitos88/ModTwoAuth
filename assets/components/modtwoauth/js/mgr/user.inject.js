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

var ModTwoAuthL = function(key) {
    var lexicon = window.ModTwoAuthUserConfig && ModTwoAuthUserConfig.lexicon
        ? ModTwoAuthUserConfig.lexicon
        : {};
    return lexicon[key] || key;
};

Ext.ComponentMgr.onAvailable('modx-user-tabs', function() {
    var tabs = this.items;

    var twoAuthTab = {
        title: '🔐 2FA',
        layout: 'form',
        bodyCssClass: 'main-wrapper',
        cls: 'modx-extrafields-tab',
        hideMode: 'offsets',
        items: [{
            xtype: 'fieldset',
            title: ModTwoAuthL('modtwoauth_verify_heading'),
            items: [{
                xtype: 'combo-boolean',
                fieldLabel: ModTwoAuthL('modtwoauth_mgr_enable'),
                name: 'modtwoauth_is_enabled',
                id: 'modtwoauth_is_enabled',
                value: ModTwoAuthUserConfig.is_enabled ? 1 : 0,
                anchor: '100%',
                disabled: !ModTwoAuthUserConfig.is_verified,
                listeners: {
                    change: { fn: function(field, value) {
                        var enabled = (value === true || value === 1 || value === '1');
                        ModTwoAuthUserConfig.is_enabled = enabled;

                        var providerField = Ext.getCmp('modtwoauth_provider');
                        if (providerField) {
                            providerField.setDisabled(enabled);
                        }

                        ModTwoAuth.renderSetupPanel(
                            providerField ? providerField.getValue() : ModTwoAuthUserConfig.provider
                        );
                    }, scope: this }
                }
            }, {
                xtype: 'displayfield',
                fieldLabel: ModTwoAuthL('modtwoauth_mgr_status'),
                value: ModTwoAuthUserConfig.is_verified
                    ? '<span style="color:green">✅ ' + ModTwoAuthL('modtwoauth_mgr_configured') + ' (' + (ModTwoAuthUserConfig.provider === 'totp' ? 'TOTP' : (ModTwoAuthUserConfig.provider === 'webauthn' ? 'WebAuthn / Passkey' : ModTwoAuthUserConfig.provider)) + ')</span>'
                    : '<span style="color:orange">⚠️ ' + ModTwoAuthL('modtwoauth_mgr_not_configured') + '</span>',
                anchor: '100%'
            }, {
                xtype: 'modx-combo',
                fieldLabel: ModTwoAuthL('modtwoauth_mgr_provider'),
                name: 'modtwoauth_provider',
                id: 'modtwoauth_provider',
                store: [['totp', 'TOTP (Google Authenticator)'], ['email', 'Email'], ['telegram', 'Telegram'], ['webauthn', 'WebAuthn / Passkey / FIDO2']],
                value: ModTwoAuthUserConfig.provider,
                editable: false,
                anchor: '100%',
                disabled: ModTwoAuthUserConfig.is_enabled,
                listeners: {
                    select: { fn: function(combo) {
                        ModTwoAuth.renderSetupPanel(combo.getValue());
                    }, scope: this }
                }
            }, {
                xtype: 'panel',
                id: 'modtwoauth_setup_panel',
                border: false,
                bodyStyle: 'padding-top:10px;',
                html: ''
            }]
        }, {
            xtype: 'fieldset',
            title: ModTwoAuthL('modtwoauth_mgr_sessions_title'),
            collapsible: true,
            collapsed: false,
            items: [{
                xtype: 'panel',
                border: false,
                html: '<div id="modtwoauth_sessions_panel" style="padding:10px;"><span style="color:#666;">' + ModTwoAuthL('modtwoauth_mgr_sessions_loading') + '</span></div>'
            }, {
                xtype: 'button',
                text: ModTwoAuthL('modtwoauth_mgr_sessions_refresh'),
                handler: function() { ModTwoAuth.loadSessions(ModTwoAuthUserConfig.user_id); }
            }]
        }]
    };

    tabs.push(twoAuthTab);

    // Рендерим панель настройки после загрузки таба
    Ext.defer(function() {
        var providerField = Ext.getCmp('modtwoauth_provider');
        var enabledField = Ext.getCmp('modtwoauth_is_enabled');
        if (providerField && enabledField) {
            providerField.setDisabled(enabledField.getValue() === 1 || enabledField.getValue() === true || enabledField.getValue() === '1');
            ModTwoAuth.renderSetupPanel(providerField.getValue());
            ModTwoAuth.loadSessions(ModTwoAuthUserConfig.user_id);
        }
    }, 700);
});

// Глобальный объект для 2FA
window.ModTwoAuth = {
    _qrReady: null,
    ensureQr: function(callback) {
        if (window.ModTwoAuthQR) { callback(); return; }
        if (!this._qrReady) {
            this._qrReady = new Promise(function(resolve, reject) {
                var script = document.createElement('script');
                script.src = (MODx.config.assets_url || '/assets/') + 'components/modtwoauth/js/qrcode.local.js';
                script.onload = resolve;
                script.onerror = function(){ reject(new Error(ModTwoAuthL('modtwoauth_mgr_qr_library_failed'))); };
                document.head.appendChild(script);
            });
        }
        this._qrReady.then(callback).catch(function(e) {
            var el = document.getElementById('modtwoauth_totp_result') || document.getElementById('modtwoauth_tg_result');
            if (el) el.textContent = e.message;
        });
    },
    renderQr: function(el, data, size) {
        if (window.ModTwoAuthQR && el) window.ModTwoAuthQR.render(el, data, size || 260);
    },

    escapeHtml: function(value) {
        return String(value == null ? '' : value).replace(/[&<>\"']/g, function(ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;'}[ch];
        });
    },

    getConnectorUrl: function() {
        var assets = (MODx.config && MODx.config.assets_url) ? MODx.config.assets_url : '/assets/';
        return assets.replace(/\/$/, '') + '/components/modtwoauth/connector.php';
    },

    loadSessions: function(userId) {
        var target = document.getElementById('modtwoauth_sessions_panel');
        if (!target) return;
        target.innerHTML = '<span style="color:#666;">' + ModTwoAuthL('modtwoauth_mgr_sessions_loading') + '</span>';
        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {action: 'ModTwoAuth\\Processors\\GetSessionList', user_id: userId},
            listeners: {
                success: {fn: function(r) {
                    var rows = Array.isArray(r.results) ? r.results : (Array.isArray(r.object) ? r.object : []);
                    if (!rows.length) {
                        target.innerHTML = '<p style="color:#666;">' + ModTwoAuthL('modtwoauth_mgr_sessions_empty') + '</p>';
                        return;
                    }
                    var html = '<table style="width:100%;border-collapse:collapse;"><thead><tr>' +
                        '<th style="text-align:left;padding:7px;border-bottom:1px solid #ddd;">' + ModTwoAuthL('modtwoauth_mgr_device_browser') + '</th>' +
                        '<th style="text-align:left;padding:7px;border-bottom:1px solid #ddd;">IP</th>' +
                        '<th style="text-align:left;padding:7px;border-bottom:1px solid #ddd;">' + ModTwoAuthL('modtwoauth_mgr_created') + '</th>' +
                        '<th style="text-align:left;padding:7px;border-bottom:1px solid #ddd;">' + ModTwoAuthL('modtwoauth_mgr_last_active') + '</th>' +
                        '<th style="padding:7px;border-bottom:1px solid #ddd;">' + ModTwoAuthL('modtwoauth_mgr_action') + '</th></tr></thead><tbody>';
                    rows.forEach(function(row) {
                        var ua = ModTwoAuth.escapeHtml(row.user_agent || ModTwoAuthL('modtwoauth_mgr_unknown_device'));
                        var ip = ModTwoAuth.escapeHtml(row.ip_address || '—');
                        var created = ModTwoAuth.escapeHtml(row.created_at || '—');
                        var active = ModTwoAuth.escapeHtml(row.last_active || '—');
                        var current = !!row.is_current;
                        html += '<tr><td style="padding:7px;border-bottom:1px solid #eee;max-width:300px;overflow-wrap:anywhere;">' + ua +
                            (current ? '<br><strong style="color:#2e7d32;">' + ModTwoAuthL('modtwoauth_mgr_current_session') + '</strong>' : '') +
                            '</td><td style="padding:7px;border-bottom:1px solid #eee;">' + ip +
                            '</td><td style="padding:7px;border-bottom:1px solid #eee;">' + created +
                            '</td><td style="padding:7px;border-bottom:1px solid #eee;">' + active +
                            '</td><td style="padding:7px;border-bottom:1px solid #eee;text-align:center;">' +
                            (current ? '<span style="color:#777;">—</span>' : '<button type="button" onclick="ModTwoAuth.removeSession(' + Number(row.id) + ',' + Number(userId) + ')" style="padding:5px 8px;color:#fff;background:#c62828;border:0;border-radius:3px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_revoke') + '</button>') +
                            '</td></tr>';
                    });
                    target.innerHTML = html + '</tbody></table><p style="font-size:12px;color:#777;">' + ModTwoAuthL('modtwoauth_mgr_sessions_revoke_hint') + '</p>';
                }, scope: this},
                failure: {fn: function(r) {
                    target.innerHTML = '<span style="color:#c62828;">' + ModTwoAuthL('modtwoauth_mgr_sessions_load_failed') + ' ' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_request_error')) + '</span>';
                }, scope: this}
            }
        });
    },

    removeSession: function(sessionId, userId) {
        if (!confirm(ModTwoAuthL('modtwoauth_mgr_session_revoke_confirm'))) return;
        var target = document.getElementById('modtwoauth_sessions_panel');
        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {action: 'ModTwoAuth\\Processors\\RemoveSession', id: sessionId, user_id: userId},
            listeners: {
                success: {fn: function(r) {
                    if (target) target.insertAdjacentHTML('afterbegin', '<p style="color:#2e7d32;">' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_session_revoked')) + '</p>');
                    ModTwoAuth.loadSessions(userId);
                }, scope: this},
                failure: {fn: function(r) {
                    if (target) target.insertAdjacentHTML('afterbegin', '<p style="color:#c62828;">' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_session_revoke_failed')) + '</p>');
                }, scope: this}
            }
        });
    },

    renderSetupPanel: function(provider) {
        var panel = Ext.getCmp('modtwoauth_setup_panel');
        if (!panel) return;

        var isVerified = ModTwoAuthUserConfig.is_verified;
        var userId = ModTwoAuthUserConfig.user_id;

        if (isVerified && ModTwoAuthUserConfig.is_enabled) {
            panel.update('<div style="padding:10px;background:#e8f5e9;border-radius:4px;color:#2e7d32;">' + ModTwoAuthL('modtwoauth_mgr_already_configured') + '</div>');
            return;
        }

        if (provider === 'totp') {
            panel.update([
                '<div style="padding:15px;background:#f5f5f5;border-radius:4px;">',
                '<p><strong>' + ModTwoAuthL('modtwoauth_mgr_totp_setup') + '</strong></p>',
                '<ol style="margin:10px 0;padding-left:20px;">',
                '<li>' + ModTwoAuthL('modtwoauth_mgr_totp_step_generate') + '</li>',
                '<li>' + ModTwoAuthL('modtwoauth_mgr_totp_step_scan') + '</li>',
                '<li>' + ModTwoAuthL('modtwoauth_mgr_totp_step_verify') + '</li>',
                '</ol>',
                '<button type="button" onclick="ModTwoAuth.generateTotp(' + userId + ')" style="padding:8px 16px;background:#1976d2;color:#fff;border:none;border-radius:4px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_generate_qr') + '</button>',
                '<div id="modtwoauth_totp_qr" style="margin-top:15px;"></div>',
                '<div id="modtwoauth_totp_verify" style="margin-top:15px;display:none;">',
                '<p>' + ModTwoAuthL('modtwoauth_mgr_enter_app_code') + '</p>',
                '<input type="text" id="modtwoauth_totp_code" maxlength="6" style="padding:8px;width:120px;font-size:16px;letter-spacing:4px;text-align:center;border:1px solid #ccc;border-radius:4px;" placeholder="000000" />',
                '<button type="button" onclick="ModTwoAuth.verifyTotp(' + userId + ')" style="margin-left:10px;padding:8px 16px;background:#388e3c;color:#fff;border:none;border-radius:4px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_confirm') + '</button>',
                '</div>',
                '<div id="modtwoauth_totp_result" style="margin-top:10px;"></div>',
                '</div>'
            ].join(''));
        } else if (provider === 'email') {
            panel.update([
                '<div style="padding:15px;background:#f5f5f5;border-radius:4px;">',
                '<p><strong>' + ModTwoAuthL('modtwoauth_mgr_email_setup') + '</strong></p>',
                '<p>' + ModTwoAuthL('modtwoauth_mgr_email_instruction') + '</p>',
                '<input type="email" id="modtwoauth_email_input" value="' + ModTwoAuth.escapeHtml(ModTwoAuthUserConfig.contact_value || '') + '" style="padding:8px;width:250px;border:1px solid #ccc;border-radius:4px;" placeholder="your@email.com" />',
                '<button type="button" onclick="ModTwoAuth.sendEmailCode(' + userId + ')" style="margin-left:10px;padding:8px 16px;background:#1976d2;color:#fff;border:none;border-radius:4px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_send_code') + '</button>',
                '<div id="modtwoauth_email_verify" style="margin-top:15px;display:none;">',
                '<p>' + ModTwoAuthL('modtwoauth_mgr_enter_email_code') + '</p>',
                '<input type="text" id="modtwoauth_email_code" maxlength="6" style="padding:8px;width:120px;font-size:16px;letter-spacing:4px;text-align:center;border:1px solid #ccc;border-radius:4px;" placeholder="000000" />',
                '<button type="button" onclick="ModTwoAuth.verifyEmail(' + userId + ')" style="margin-left:10px;padding:8px 16px;background:#388e3c;color:#fff;border:none;border-radius:4px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_confirm') + '</button>',
                '</div>',
                '<div id="modtwoauth_email_result" style="margin-top:10px;"></div>',
                '</div>'
            ].join(''));
        } else if (provider === 'webauthn') {
            panel.update([
                '<div style="padding:15px;background:#f5f5f5;border-radius:4px;">',
                '<p><strong>' + ModTwoAuthL('modtwoauth_mgr_webauthn_setup') + '</strong></p>',
                '<p>' + ModTwoAuthL('modtwoauth_mgr_webauthn_instruction') + '</p>',
                '<button type="button" onclick="ModTwoAuth.registerWebAuthn(' + userId + ')" style="padding:9px 18px;background:#1976d2;color:#fff;border:none;border-radius:4px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_register_passkey') + '</button>',
                '<div id="modtwoauth_webauthn_result" style="margin-top:10px;"></div>',
                '</div>'
            ].join(''));
        } else if (provider === 'telegram') {
            panel.update([
                '<div style="padding:15px;background:#f5f5f5;border-radius:4px;">',
                '<p><strong>' + ModTwoAuthL('modtwoauth_mgr_telegram_setup') + '</strong></p>',
                '<p>' + ModTwoAuthL('modtwoauth_mgr_telegram_instruction') + '</p>',
                '<div id="modtwoauth_tg_qr" style="text-align:center;margin:15px 0;"><button type="button" onclick="ModTwoAuth.createTelegramLink(' + userId + ')" style="padding:9px 18px;background:#0088cc;color:#fff;border:none;border-radius:4px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_create_qr') + '</button></div>',
                '<div id="modtwoauth_tg_result" style="margin-top:10px;"></div>',
                '<div id="modtwoauth_tg_status" style="margin-top:10px;"></div>',
                '</div>'
            ].join(''));
        }
    },

    // TOTP methods
    generateTotp: function(userId) {
        var self = this;
        var qrDiv = document.getElementById('modtwoauth_totp_qr');
        var verifyDiv = document.getElementById('modtwoauth_totp_verify');
        var resultDiv = document.getElementById('modtwoauth_totp_result');

        qrDiv.innerHTML = '<p>' + ModTwoAuthL('modtwoauth_mgr_generating') + '</p>';

        // Все процессоры modTwoAuth вызываем через собственный коннектор компонента:
        // он требует POST и сверяет Origin/Referer для операций, меняющих состояние.
        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {
                action: 'ModTwoAuth\\Processors\\GenerateTotp',
                user_id: userId
            },
            listeners: {
                success: {fn: function(r) {
                    qrDiv.innerHTML = [
                        '<p><strong>' + ModTwoAuthL('modtwoauth_mgr_secret') + '</strong> <code style="background:#fff;padding:2px 6px;border-radius:3px;">' + ModTwoAuth.escapeHtml(r.object.secret) + '</code></p>',
                        '<p>' + ModTwoAuthL('modtwoauth_mgr_scan_qr') + '</p>',
                        '<div id="modtwoauth_totp_qr_svg" style="display:inline-block;border:1px solid #ddd;border-radius:4px;background:#fff;padding:4px;" aria-label="QR Code"></div>',
                        '<p style="font-size:12px;color:#666;margin-top:5px;">' + ModTwoAuthL('modtwoauth_mgr_qr_manual') + '</p>'
                    ].join('');
                    verifyDiv.style.display = 'block';
                    resultDiv.innerHTML = '';
                    self.ensureQr(function(){ self.renderQr(document.getElementById('modtwoauth_totp_qr_svg'), r.object.qr_data, 260); });
                }, scope: this},
                failure: {fn: function(r) {
                    qrDiv.innerHTML = '';
                    resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_error')) + '</span>';
                }, scope: this}
            }
        });
    },

    verifyTotp: function(userId) {
        var code = document.getElementById('modtwoauth_totp_code').value.trim();
        var resultDiv = document.getElementById('modtwoauth_totp_result');

        if (!/^\d{6}$/.test(code)) {
            resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuthL('modtwoauth_enter_six_digits') + '</span>';
            return;
        }

        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {
                action: 'ModTwoAuth\\Processors\\VerifyTotp',
                user_id: userId,
                code: code
            },
            listeners: {
                success: {fn: function(r) {
                    resultDiv.innerHTML = '<span style="color:#2e7d32;">✅ ' + ModTwoAuth.escapeHtml(r.message) + '</span>';
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }, scope: this},
                failure: {fn: function(r) {
                    resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_error')) + '</span>';
                }, scope: this}
            }
        });
    },

    // Email methods
    sendEmailCode: function(userId) {
        var email = document.getElementById('modtwoauth_email_input').value.trim();
        var resultDiv = document.getElementById('modtwoauth_email_result');
        var verifyDiv = document.getElementById('modtwoauth_email_verify');

        if (!email || !email.includes('@')) {
            resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuthL('modtwoauth_email_invalid') + '</span>';
            return;
        }

        resultDiv.innerHTML = '<span style="color:#1976d2;">⏳ ' + ModTwoAuthL('modtwoauth_mgr_sending') + '</span>';

        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {
                action: 'ModTwoAuth\\Processors\\SendEmailCode',
                user_id: userId,
                email: email
            },
            listeners: {
                success: {fn: function(r) {
                    resultDiv.innerHTML = '<span style="color:#2e7d32;">✅ ' + ModTwoAuth.escapeHtml(r.message) + '</span>';
                    verifyDiv.style.display = 'block';
                }, scope: this},
                failure: {fn: function(r) {
                    resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_error')) + '</span>';
                }, scope: this}
            }
        });
    },

    verifyEmail: function(userId) {
        var code = document.getElementById('modtwoauth_email_code').value.trim();
        var resultDiv = document.getElementById('modtwoauth_email_result');

        if (!/^\d{6}$/.test(code)) {
            resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuthL('modtwoauth_enter_six_digits') + '</span>';
            return;
        }

        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {
                action: 'ModTwoAuth\\Processors\\VerifyEmail',
                user_id: userId,
                code: code
            },
            listeners: {
                success: {fn: function(r) {
                    resultDiv.innerHTML = '<span style="color:#2e7d32;">✅ ' + ModTwoAuth.escapeHtml(r.message) + '</span>';
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }, scope: this},
                failure: {fn: function(r) {
                    resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_error')) + '</span>';
                }, scope: this}
            }
        });
    },

    // WebAuthn uses the extra's own connector. MODX's core connector only routes core processors.
    getWebAuthnConnectorUrl: function() {
        var assets = (MODx.config && MODx.config.assets_url) ? MODx.config.assets_url : '/assets/';
        return assets.replace(/\/$/, '') + '/components/modtwoauth/connector.php';
    },

    getWebAuthnRegisterUrl: function() {
        var assets = (MODx.config && MODx.config.assets_url) ? MODx.config.assets_url : '/assets/';
        return assets.replace(/\/$/, '') + '/components/modtwoauth/webauthn-register.php';
    },

    // WebAuthn / FIDO2 methods
    registerWebAuthn: function(userId) {
        var result = document.getElementById('modtwoauth_webauthn_result');
        if (!window.PublicKeyCredential || !navigator.credentials) {
            result.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuthL('modtwoauth_mgr_browser_no_webauthn') + '</span>';
            return;
        }

        var endpoint = ModTwoAuth.getWebAuthnRegisterUrl();
        result.innerHTML = '<span style="color:#1976d2;">⏳ ' + ModTwoAuthL('modtwoauth_mgr_webauthn_preparing') + '</span>';

        fetch(endpoint, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function(response) {
                return response.text().then(function(text) {
                    var data = null;
                    try { data = JSON.parse(text); } catch (e) {}
                    if (!response.ok || !data || !data.ok) {
                        throw new Error(data && data.message ? data.message : ModTwoAuthL('modtwoauth_mgr_http_no_json').replace('[[+status]]', response.status));
                    }
                    return data.publicKey;
                });
            })
            .then(function(p) {
                function b64urlToBuffer(s) {
                    if (typeof s !== 'string' || !/^[A-Za-z0-9_-]*$/.test(s)) {
                        throw new Error(ModTwoAuthL('modtwoauth_webauthn_invalid_base64'));
                    }
                    s = s.replace(/-/g, '+').replace(/_/g, '/');
                    while (s.length % 4) s += '=';
                    var x = atob(s), a = new Uint8Array(x.length);
                    for (var i = 0; i < x.length; i++) a[i] = x.charCodeAt(i);
                    return a.buffer;
                }
                if (!p.rp || typeof p.rp.id !== 'string' || !p.rp.id) {
                    throw new Error(ModTwoAuthL('modtwoauth_mgr_webauthn_missing_rp'));
                }
                // Safari is strict about the RP ID. It must be a host, never a URL or host:port.
                if (!/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i.test(p.rp.id)) {
                    throw new Error(ModTwoAuthL('modtwoauth_mgr_webauthn_invalid_rp').replace('[[+id]]', p.rp.id));
                }
                p.challenge = b64urlToBuffer(p.challenge);
                p.user.id = b64urlToBuffer(p.user.id);
                if (Array.isArray(p.excludeCredentials)) {
                    p.excludeCredentials = p.excludeCredentials.map(function(credential) {
                        credential.id = b64urlToBuffer(credential.id);
                        return credential;
                    });
                }
                // Safari has broad WebAuthn support for ES256 and RS256. Keep the
                // registration list conservative; Ed25519 (-8) is not universally supported.
                p.pubKeyCredParams = [
                    {type: 'public-key', alg: -7},
                    {type: 'public-key', alg: -257}
                ];
                return navigator.credentials.create({publicKey: p});
            })
            .then(function(c) {
                if (!c || !c.rawId || !c.response) {
                    throw new Error(ModTwoAuthL('modtwoauth_mgr_registration_cancelled'));
                }
                function s64(b) {
                    var a = new Uint8Array(b), x = '';
                    for (var i = 0; i < a.length; i++) x += String.fromCharCode(a[i]);
                    return btoa(x).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
                }
                var credential = {
                    id: c.id,
                    rawId: s64(c.rawId),
                    type: c.type,
                    response: {
                        clientDataJSON: s64(c.response.clientDataJSON),
                        attestationObject: s64(c.response.attestationObject)
                    },
                    name: 'Passkey / FIDO2'
                };
                result.innerHTML = '<span style="color:#1976d2;">⏳ ' + ModTwoAuthL('modtwoauth_mgr_webauthn_checking') + '</span>';
                return fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify({credential: credential})
                });
            })
            .then(function(response) {
                return response.text().then(function(text) {
                    var data = null;
                    try { data = JSON.parse(text); } catch (e) {}
                    if (!response.ok || !data || !data.ok) {
                        throw new Error(data && data.message ? data.message : ModTwoAuthL('modtwoauth_mgr_http_no_json').replace('[[+status]]', response.status));
                    }
                    return data;
                });
            })
            .then(function(data) {
                result.innerHTML = '<span style="color:#2e7d32;">✅ ' + ModTwoAuth.escapeHtml(data.message || ModTwoAuthL('modtwoauth_mgr_key_registered')) + '</span>';
                setTimeout(function(){ location.reload(); }, 1200);
            })
            .catch(function(e) {
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('[ModTwoAuth] WebAuthn registration failed:', e);
                }
                var msg = e && e.message ? e.message : ModTwoAuthL('modtwoauth_mgr_registration_cancelled');
                if (msg === 'The string did not match the expected pattern.') {
                    msg = ModTwoAuthL('modtwoauth_mgr_safari_rp_error');
                }
                result.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuth.escapeHtml(msg) + '</span>';
            });
    },

    // Telegram methods
    createTelegramLink: function(userId) {
        var self = this;
        var qrDiv = document.getElementById('modtwoauth_tg_qr');
        var resultDiv = document.getElementById('modtwoauth_tg_result');
        var statusDiv = document.getElementById('modtwoauth_tg_status');

        if (!qrDiv) return;
        qrDiv.innerHTML = '<p style="color:#1976d2;">⏳ ' + ModTwoAuthL('modtwoauth_mgr_qr_creating') + '</p>';
        resultDiv.innerHTML = '';
        statusDiv.innerHTML = '';

        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {
                action: 'ModTwoAuth\\Processors\\CreateTelegramLink',
                user_id: userId
            },
            listeners: {
                success: {fn: function(r) {
                    qrDiv.innerHTML = [
                        '<p><strong>' + ModTwoAuthL('modtwoauth_mgr_telegram_step1') + '</strong></p>',
                        '<div id="modtwoauth_tg_qr_svg" style="display:inline-block;border:1px solid #ddd;border-radius:6px;background:#fff;padding:5px;" aria-label="Telegram QR"></div>',
                        '<p style="margin:10px 0 4px;">' + ModTwoAuthL('modtwoauth_mgr_telegram_step2') + '</p>',
                        '<p style="font-size:12px;color:#777;">' + ModTwoAuthL('modtwoauth_mgr_qr_valid_5m') + '</p>'
                    ].join('');
                    self.ensureQr(function(){ self.renderQr(document.getElementById('modtwoauth_tg_qr_svg'), r.object.qr_data, 260); });
                    statusDiv.innerHTML = '<span style="color:#1976d2;">' + ModTwoAuthL('modtwoauth_verify_wait_telegram') + '</span>';
                    ModTwoAuth.pollTelegramLink(userId);
                }, scope: this},
                failure: {fn: function(r) {
                    qrDiv.innerHTML = '<button type="button" onclick="ModTwoAuth.createTelegramLink(' + userId + ')" style="padding:9px 18px;background:#0088cc;color:#fff;border:none;border-radius:4px;cursor:pointer;">' + ModTwoAuthL('modtwoauth_mgr_create_qr') + '</button>';
                    resultDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_qr_create_failed')) + '</span>';
                }, scope: this}
            }
        });
    },

    pollTelegramLink: function(userId) {
        var statusDiv = document.getElementById('modtwoauth_tg_status');
        if (!statusDiv) return;

        if (this.telegramLinkTimer) {
            clearTimeout(this.telegramLinkTimer);
        }

        var self = this;
        MODx.Ajax.request({
            url: this.getConnectorUrl(),
            params: {
                action: 'ModTwoAuth\\Processors\\TelegramLinkStatus',
                user_id: userId
            },
            listeners: {
                success: {fn: function(r) {
                    var status = r.object && r.object.status ? r.object.status : 'pending';
                    if (status === 'confirmed') {
                        statusDiv.innerHTML = '<span style="color:#2e7d32;font-weight:bold;">✅ ' + ModTwoAuthL('modtwoauth_telegram_connected') + '</span>';
                        setTimeout(function() { location.reload(); }, 800);
                        return;
                    }
                    if (status === 'expired') {
                        statusDiv.innerHTML = '<span style="color:#c62828;">❌ ' + ModTwoAuthL('modtwoauth_mgr_qr_expired') + '</span>';
                        return;
                    }
                    statusDiv.innerHTML = '<span style="color:#1976d2;">⏳ ' + ModTwoAuth.escapeHtml(r.message || ModTwoAuthL('modtwoauth_mgr_waiting_confirmation')) + '</span>';
                    self.telegramLinkTimer = setTimeout(function() {
                        self.pollTelegramLink(userId);
                    }, 2000);
                }, scope: this},
                failure: {fn: function() {
                    self.telegramLinkTimer = setTimeout(function() {
                        self.pollTelegramLink(userId);
                    }, 3000);
                }, scope: this}
            }
        });
    }

};
