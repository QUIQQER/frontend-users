/**
 * Frontend Profile: Change user data
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData', [

    'qui/QUI',
    'qui/controls/Control',
    'utils/Controls',
    'qui/utils/Functions',

    'Locale',

    'package/quiqqer/frontend-users/bin/Registration',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData.css'

], function(QUI, QUIControl, QUIControlUtils, QUIFunctionUtils, QUILocale, Registration) {
    'use strict';

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData',

        Binds: [
            '$onInject',
            '$showEmailErrorMsg',
            '$clearEmailErrorMsg'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$EmailErrorMsgElm = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            const self = this;
            const Elm = this.getElm();
            const ChangeEmailElm = Elm.querySelector('[data-name="email-edit"]');
            const EmailNewElm = Elm.querySelector('[data-name="email-new"]');
            const EmailNewInput = Elm.querySelector('input[name="emailNew"]');
            let ProfileNode = Elm.querySelector(
                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile"]'
            );

            if (ProfileNode) {
                QUIControlUtils.getControlByElement(ProfileNode).then(function(ProfileControl) {
                    ProfileControl.addEvents({
                        onSave: function() {
                            EmailNewElm.addAttribute('data-hidden');
                            // EmailNewElm.addClass('quiqqer-frontendUsers-userdata-email__hidden');
                            EmailNewInput.value = '';
                        },
                        onSaveError: function() {
                            EmailNewInput.value = '';
                            EmailNewInput.focus();
                        }
                    });
                }, function() {
                    // do nothing
                });
            }

            ChangeEmailElm.addEvent('click', function() {
                // EmailNewElm.removeClass('quiqqer-frontendUsers-userdata-email__hidden');
                EmailNewElm.removeAttribute('data-hidden');
                EmailNewInput.focus();

                // resize profile
                ProfileNode = self.getElm().getParent(
                    '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile"]'
                );

                if (!ProfileNode) {
                    return;
                }

                const Profile = QUI.Controls.getById(ProfileNode.get('data-quiid'));

                if (Profile) {
                    Profile.resize();
                }
            });

            const CheckMail = function(event) {
                const email = event.target.value.trim();

                Promise.all([
                    Registration.emailSyntaxValidation(email),
                    Registration.emailValidation(email)
                ]).then(function(result) {
                    var emailSyntaxValid = result[0];
                    var emailValid = result[1];

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

            EmailNewInput.addEvent(
                'keydown',
                QUIFunctionUtils.debounce(CheckMail, 500)
            );
        },

        /**
         * Show error msg for e-mail change
         *
         * @param {String} msg
         */
        $showEmailErrorMsg: function(msg) {
            if (!this.$EmailErrorMsgElm) {
                this.$EmailErrorMsgElm = new Element('div', {
                    'class': 'content-message-error'
                }).inject(this.getElm().getElement('input[name="emailNew"]'), 'after');
            }

            this.$EmailErrorMsgElm.set('html', msg);
        },

        /**
         * Hide error msg for e-mail change
         */
        $clearEmailErrorMsg: function() {
            if (this.$EmailErrorMsgElm) {
                this.$EmailErrorMsgElm.destroy();
                this.$EmailErrorMsgElm = null;
            }
        }
    });
});
