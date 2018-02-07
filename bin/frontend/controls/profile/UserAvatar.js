/**
 * Frontend Profile: Change User Avatar
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/UserAvatar
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserAvatar', [

    'qui/controls/Control',
    'utils/Controls',

], function (QUIControl, QUIControlUtils) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/UserAvatar',

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


        }
    });
});
