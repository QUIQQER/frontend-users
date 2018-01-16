/**
 * Frontend Profile: Change user data
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData', [

    'qui/controls/Control',
    'utils/Controls',
    'qui/utils/Functions',

    'Locale',

    'package/quiqqer/frontend-users/bin/Registration',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData.css'

], function (QUIControl, QUIControlUtils, QUIFunctionUtils, QUILocale, Registration) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData',

        Binds: [
            '$onInject',
            '$showEmailErrorMsg',
            '$clearEmailErrorMsg'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$EmailErrorMsgElm = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self           = this;
            var Elm            = this.getElm();
            var ChangeEmailElm = Elm.getElement('.quiqqer-frontendUsers-userdata-email-edit');
            var EmailNewElm    = Elm.getElement('.quiqqer-frontendUsers-userdata-email-new');
            var EmailNewInput  = Elm.getElement('input[name="emailNew"]');

            QUIControlUtils.getControlByElement(
                Elm.getParent('.quiqqer-frontendUsers-controls-profile')
            ).then(function (ProfileControl) {
                ProfileControl.addEvents({
                    onSave     : function () {
                        EmailNewElm.addClass('quiqqer-frontendUsers-userdata-email__hidden');
                        EmailNewInput.value = '';
                    },
                    onSaveError: function () {
                        EmailNewInput.value = '';
                        EmailNewInput.focus();
                    }
                });

                ChangeEmailElm.addEvent('click', function () {
                    EmailNewElm.removeClass('quiqqer-frontendUsers-userdata-email__hidden');
                    EmailNewInput.focus();
                });

                var CheckMail = function (event) {
                    var email = event.target.value.trim();

                    Promise.all([
                        Registration.emailSyntaxValidation(email),
                        Registration.emailValidation(email)
                    ]).then(function (result) {
                        var emailSyntaxValid = result[0];
                        var emailValid       = result[1];

                        if (emailSyntaxValid && emailValid) {
                            self.$clearEmailErrorMsg();
                            return;
                        }

                        if (!emailSyntaxValid) {
                            self.$showEmailErrorMsg(
                                QUILocale.get(lg, 'controls.profile.userdata.email_invalid')
                            );
                        }

                        if (!emailValid) {
                            self.$showEmailErrorMsg(
                                QUILocale.get(lg, 'controls.profile.userdata.email_already_taken')
                            );
                        }
                    });
                };

                var checkMail = QUIFunctionUtils.debounce(CheckMail, 500);

                EmailNewInput.addEvent('keydown', checkMail);
            }, function () {
                // do nothing
            });
        },

        /**
         * Show error msg for e-mail change
         *
         * @param {String} msg
         */
        $showEmailErrorMsg: function (msg) {
            if (!this.$EmailErrorMsgElm) {
                this.$EmailErrorMsgElm = new Element('span', {
                    'class': 'quiqqer-frontendUsers-error'
                }).inject(this.getElm().getElement('input[name="emailNew"]'), 'after');
            }

            this.$EmailErrorMsgElm.set('html', msg);
        },

        /**
         * Hide error msg for e-mail change
         */
        $clearEmailErrorMsg: function () {
            if (this.$EmailErrorMsgElm) {
                this.$EmailErrorMsgElm.destroy();
                this.$EmailErrorMsgElm = null;
            }
        }
    });
});
