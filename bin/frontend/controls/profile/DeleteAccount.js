/**
 * Frontend Profile: Delete account
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount', [

    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'Locale'

], function (QUIControl, QUIConfirm, QUILocale) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/DeleteAccount',

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
            var Elm = this.getElm();

            var SubmitBtn = Elm.getElement('button[class="quiqqer-frontendUsers-saveButton"]'),
                confirmed = false;

            if (!SubmitBtn) {
                return;
            }

            SubmitBtn.addEvent('click', function (event) {
                if (confirmed) {
                    return;
                }

                event.stop();

                new QUIConfirm({
                    maxHeight: 350,
                    autoclose: true,

                    information: QUILocale.get(lg, 'controls.profile.DeleteAccount.confirm.information'),
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
                        onSubmit: function (Popup) {
                            confirmed = true;
                            Popup.close();
                            SubmitBtn.click();
                        }
                    }
                }).open();
            });
        }
    });
});
