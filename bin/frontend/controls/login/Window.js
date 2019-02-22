/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/login/Window
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/login/Window', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'package/quiqqer/frontend-users/bin/frontend/controls/login/Login'

], function (QUI, QUIPopup, Login) {
    "use strict";

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/login/Window',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight: 600,
            maxWidth : 800,
            buttons  : false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            new Login().inject(this.getContent());
        }
    });
});