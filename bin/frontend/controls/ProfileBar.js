/**
 * ProfileBar control: Shows different states based on auth status
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/Registration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/ProfileBar', [

    'qui/QUI',
    'qui/controls/Control',

    'controls/users/LoginWindow'

], function (QUI, QUIControl, LoginWindow) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/ProfileBar',

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });

            this.$Loader = null;
        },

        /**
         * event: on inject
         */
        $onImport: function () {
            var Elm      = this.getElm();
            var LoginElm = Elm.getElement('.quiqqer-frontendUsers-profileBar-login');

            if (LoginElm) {
                LoginElm.addEvent('click', function(event) {
                    event.stop();

                    new LoginWindow({
                        events: {
                            onSuccess: function() {
                                window.location = window.location;
                            }
                        }
                    }).open();
                });
            }
        }
    });
});
