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

            // activate without settings
            Array.from(container.querySelectorAll('[name="activate"]')).forEach((button) => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    e.preventDefault();

                    let button = e.target.nodeName === 'BUTTON' ? e.target : e.target.closest('button');
                    button.disabled = true;

                    this.activate2FAAuthenticator(
                        button.getAttribute('data-authenticator')
                    ).then(() => {
                        button.disabled = false;
                        this.refresh();
                    }).catch(() => {
                        button.disabled = false;
                    });
                });
            });

            // activate with authenticator settings
            Array.from(container.querySelectorAll('[name="activate-settings"]')).forEach((button) => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    e.preventDefault();

                    let button = e.target.nodeName === 'BUTTON' ? e.target : e.target.closest('button');
                    button.disabled = true;

                    this.activate2FAAuthenticatorWithSettings(
                        button.getAttribute('data-authenticator')
                    ).then(() => {
                        button.disabled = false;
                        this.refresh();
                    }).catch(() => {
                        button.disabled = false;
                    });
                });
            });

            // deactivate
            Array.from(container.querySelectorAll('[name="deactivate"]')).forEach((button) => {
                button.addEventListener('click', (e) => {
                    e.stopPropagation();
                    e.preventDefault();

                    let button = e.target.nodeName === 'BUTTON' ? e.target : e.target.closest('button');
                    button.disabled = true;

                    this.disable2FAAuthenticator(
                        button.getAttribute('data-authenticator')
                    ).then(() => {
                        button.disabled = false;
                        this.refresh();
                    }).catch(() => {
                        button.disabled = false;
                    });
                });
            });
        },

        refresh: function() {
            const profileQui = 'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile';
            const container = this.getElm();

            const profileNode = container.closest('[data-qui="' + profileQui +'"]');

            if (profileNode) {
                profileNode.querySelector('a[data-active="1"]').click();
                return;
            }

            console.log('NOT IN PROFILE');
        },

        disable2FAAuthenticator: function (authenticator) {
            return new Promise((resolve) => {
                QUIAjax.post('ajax_users_authenticator_disableByUser', resolve, {
                    authenticator: authenticator
                });
            });
        },

        activate2FAAuthenticator: function (authenticator) {
            return new Promise((resolve) => {
                QUIAjax.post('ajax_users_authenticator_enableByUser', resolve, {
                    authenticator: authenticator
                });
            });
        },

        activate2FAAuthenticatorWithSettings: function (authenticator) {
            return new Promise((resolve, reject) => {
                require([
                    'controls/users/auth/EnableSecondaryAuthenticatorWindow'
                ], (EnableSecondaryAuthenticatorWindow) => {
                    new EnableSecondaryAuthenticatorWindow({
                        authenticator: authenticator,
                        events: {
                            onCompleted: () => {
                                resolve();
                            }
                        }
                    }).open();
                });
            });
        }
    });
});
