/**
 * Frontend Profile: Delete account
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount', [

    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'Locale',
    'Ajax'

], function (QUIControl, QUIConfirm, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount',

        Binds: [
            '$onImport',
            '$checkDeleteAccount'
        ],

        options: {
            username: ''
        },

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
            var Elm = this.getElm();

            var SubmitBtn = Elm.getElement('button[class="quiqqer-frontendUsers-saveButton"]'),
                confirmed = false;

            if (!SubmitBtn) {
                return;
            }

            var self     = this;
            var username = this.getAttribute('username');

            if (!username) {
                username = '';
            }

            SubmitBtn.addEvent('click', function (event) {
                if (confirmed) {
                    return;
                }

                event.stop();

                new QUIConfirm({
                    maxHeight: 350,
                    autoclose: true,

                    information: QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.information', {
                        username: username
                    }),
                    title      : QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.title'),
                    texticon   : 'fa fa-trash',
                    text       : QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.text'),
                    icon       : 'fa fa-trash',

                    cancel_button: {
                        text     : QUILocale.get('quiqqer/system', 'cancel'),
                        textimage: 'fa fa-remove'
                    },

                    ok_button: {
                        text     : QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.btn'),
                        textimage: 'fa fa-trash'
                    },

                    events: {
                        onOpen  : function (Popup) {
                            var SubmitBtn = Popup.getButton('submit');

                            SubmitBtn.disable();
                            
                            Popup.Loader.show();

                            self.$checkDeleteAccount().then(function () {
                                SubmitBtn.enable();
                                Popup.Loader.hide();
                            }, function (Error) {
                                Popup.setAttribute(
                                    'information',
                                    QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.information_error', {
                                        username: username,
                                        error   : Error.getMessage()
                                    })
                                );

                                Popup.Loader.hide();
                            });
                        },
                        onSubmit: function (Popup) {
                            confirmed = true;
                            Popup.close();
                            SubmitBtn.click();
                        }
                    }
                }).open();
            });
        },

        /**
         * Check if a user account can be deleted
         *
         * @return {Promise}
         */
        $checkDeleteAccount: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_checkDeleteAccount', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError  : reject,
                    showError: false
                });
            });
        }
    });
});
