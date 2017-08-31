/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/Registration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/UserIcon', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/UserIcon',

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
            var self = this;

            this.$Loader = new Element('span', {
                'class': 'quiqqer-frontendUsers-userIcon-loader',
                html   : '<span class="fa fa-spinner fa-spin"></span>'
            }).inject(this.getElm());

            this.getElm().addEvent('click', function () {
                self.$Loader.setStyle('display', null);

                require(['package/quiqqer/frontend-users/bin/frontend/controls/profile/Window'], function (Win) {
                    new Win().open();

                    self.$Loader.setStyle('display', 'none');
                });
            });

            this.$Loader.setStyle('display', 'none');
        }
    });
});
