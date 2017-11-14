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
            '$onInject'
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
            var Elm              = this.getElm();
            var PasswordOldInput = Elm.getElement('input[name="passwordOld"]');

            if (PasswordOldInput) {
                PasswordOldInput.focus();
            }

            var Form = Elm.getParent('form');

            if (!Form) {
                return;
            }

            QUIControlUtils.getControlByElement(
                Elm.getParent('.quiqqer-frontendUsers-controls-profile')
            ).then(function (ProfileControl) {
                ProfileControl.addEvent('onSave', function () {
                    QUIFormUtils.setDataToForm({
                        'passwordOld'       : '',
                        'passwordNew'       : '',
                        'passwordNewConfirm': ''
                    }, Form);
                });
            }, function () {
                // nothing
            });
        }
    });
});
