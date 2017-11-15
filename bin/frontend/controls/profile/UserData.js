/**
 * Frontend Profile: Change user data
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData', [

    'qui/controls/Control',
    'utils/Controls'

], function (QUIControl, QUIControlUtils) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/UserData',

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

                EmailNewInput.addEvent('')
            }, function () {
                // do nothing
            });
        }
    });
});
