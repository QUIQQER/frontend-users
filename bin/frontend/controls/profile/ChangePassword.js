/**
 * Frontend Profile: Change password
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword', [

    'qui/controls/Control',
    'utils/Controls',
    'qui/utils/Form'

], function (QUIControl, QUIControlUtils, QUIFormUtils) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/ChangePassword',

        Binds: [
            '$onInject',
            '$showError',
            '$hideError'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$ErrorContainer = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self             = this;
            var Elm              = this.getElm();
            var PasswordOldInput = Elm.getElement('input[name="passwordOld"]');

            if (PasswordOldInput) {
                PasswordOldInput.focus();
            }

            var Form = Elm.getParent('form');

            if (!Form) {
                return;
            }

            this.$ErrorContainer = Elm.getElement('.quiqqer-frontendUsers-changepassword-error');

            QUIControlUtils.getControlByElement(
                Elm.getParent('.quiqqer-frontendUsers-controls-profile')
            ).then(function (ProfileControl) {
                ProfileControl.addEvents({
                    onSave     : function () {
                        QUIFormUtils.setDataToForm({
                            'passwordOld'       : '',
                            'passwordNew'       : '',
                            'passwordNewConfirm': ''
                        }, Form);
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
            this.$ErrorContainer.set('html', msg);
            this.$ErrorContainer.setStyle('display', 'block');
        },

        /**
         * Show error msg
         */
        $hideError: function () {
            this.$ErrorContainer.setStyle('display', 'none');
        }
    });
});
