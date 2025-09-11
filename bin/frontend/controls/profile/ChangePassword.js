/**
 * Frontend Profile: Change password
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword', [

    'qui/controls/Control',
    'utils/Controls',
    'qui/utils/Form',

    'Locale'

], function (QUIControl, QUIControlUtils, QUIFormUtils, QUILocale) {
    'use strict';

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword',

        Binds: [
            '$onInject',
            '$showError',
            '$hideError',
            '$showSuccess',
            '$hideSuccess'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$ErrorContainer = null;
            this.$SuccessContainer = null;
            this.$ProfileControl = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;
            var Elm = this.getElm();
            var PasswordOldInput = Elm.getElement('input[name="passwordOld"]');

            if (PasswordOldInput) {
                PasswordOldInput.focus();
            }

            var Form = Elm.getParent('form');

            if (!Form) {
                return;
            }

            this.$ErrorContainer = Elm.querySelector('[data-name="msg-error"]');
            this.$SuccessContainer = Elm.querySelector('[data-name="msg-success"]');

            QUIControlUtils.getControlByElement(
                Elm.getParent('.quiqqer-frontendUsers-controls-profile')
            ).then(function (ProfileControl) {
                self.$ProfileControl = ProfileControl;

                self.$ProfileControl.addEvents({
                    onSave: function () {
                        QUIFormUtils.setDataToForm({
                            'passwordOld': '',
                            'passwordNew': '',
                            'passwordNewConfirm': ''
                        }, Form);
                    },
                    onSaveEnd: function () {
                        self.$showSuccess();
                    },
                    onSaveError: function (Control, error) {
                        self.$showError(error.getMessage());
                    }
                });
            }, function () {
                // nothing
            });
        },

        /**
         * Hide error msg
         *
         * @param {String} msg
         */
        $showError: function (msg) {
            this.$hideSuccess();

            this.$ErrorContainer.set('html', msg);
            this.$ErrorContainer.setStyle('display', 'block');

            this.$ProfileControl.resize();
        },

        /**
         * Show error msg
         */
        $hideError: function () {
            this.$ErrorContainer.setStyle('display', 'none');
            this.$ProfileControl.resize();
        },

        /**
         * Show success msg
         */
        $showSuccess: function () {
            this.$hideError();

            this.$SuccessContainer.set(
                'html',
                QUILocale.get(lg, 'controls.profile.ChangePassword.success')
            );

            this.$SuccessContainer.setStyle('display', 'block');
            this.$ProfileControl.resize();
        },

        /**
         * Hide success msg
         */
        $hideSuccess: function () {
            this.$SuccessContainer.setStyle('display', 'none');
            this.$ProfileControl.resize();
        }
    });
});
