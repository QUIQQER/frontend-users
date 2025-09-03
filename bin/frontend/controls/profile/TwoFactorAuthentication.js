define('package/quiqqer/frontend-users/bin/frontend/controls/profile/TwoFactorAuthentication', [

    'qui/controls/Control',
    'Locale',
    'Ajax'

], function (QUIControl, QUILocale, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/profile/TwoFactorAuthentication',

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            const container = this.getElm();

            let twoFactorAuthIsEnabled = false;
            let section = container.querySelector('[data-name="2fa-is-disabled"]');

            if (container.querySelector('[data-name="2fa-is-enabled"]')) {
                twoFactorAuthIsEnabled = true;
                section = container.querySelector('[data-name="2fa-is-enabled"]');
            }

            Array.from(container.querySelectorAll('[name="activate"]')).forEach((button) => {
                button.addEventListener('click', (e) => {
                    let button = e.target;

                    if (button.nodeName === 'BUTTON') {
                        button = button.closest('button');
                    }

                    button.disabled = true;

                    this.activate2FAAuthenticator(
                        button.getAttribute('data-authenticator')
                    ).then(() => {
                        button.disabled = false;
                    });

                });
            });

            Array.from(container.querySelectorAll('[name="activate-settings"]')).forEach((button) => {
                button.addEventListener('click', (e) => {
                    let button = e.target;

                    if (button.nodeName === 'BUTTON') {
                        button = button.closest('button');
                    }

                    button.disabled = true;

                    this.activate2FAAuthenticatorWithSettings(
                        button.getAttribute('data-authenticator')
                    ).then(() => {
                        button.disabled = false;
                    }).catch(() => {
                        button.disabled = false;
                    });
                });
            });
        },

        activate2FAAuthenticator: function (authenticator) {
            return new Promise(() => {
                // QUIAjax.post('');
            });

        },

        activate2FAAuthenticatorWithSettings: function (authenticator) {
            return new Promise((resolve, reject) => {
                require([
                    'qui/controls/windows/Popup',
                    'Ajax'
                ], (Popup, Ajax) => {
                    new Popup({
                        maxHeight: 600,
                        maxWidth: 800,
                        title: 'Settings for ',
                        icon: 'fa fa-gears',
                        buttons: false,
                        events: {
                            onOpen: function (win) {
                                win.Loader.show();
                                win.getContent().innerHTML = '';

                                Ajax.get('ajax_users_authenticator_settings', (settingHtml) => {
                                    win.getContent().innerHTML = settingHtml;

                                    QUI.parse(win.getContent()).then(() => {
                                        win.Loader.hide();
                                        resolve();
                                    });
                                }, {
                                    authenticator: authenticator,
                                    uid: QUIQQER_USER.id,
                                    onError: (err) => {
                                        console.error(err);
                                        win.close();
                                        reject();
                                    }
                                });
                            }
                        }
                    }).open();
                });
            });
        }
    });
});
