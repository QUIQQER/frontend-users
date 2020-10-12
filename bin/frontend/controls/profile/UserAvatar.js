/**
 * Frontend Profile: Change User Avatar
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/UserAvatar
 * @author www.pcsg.de (Patrick Müller)
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/UserAvatar', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
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
            var Elm    = this.getElm();
            var Upload = Elm.getElement('.controls-upload-form');

            if (!Upload) {
                return;
            }

            Elm.getElement('[type="submit"]').addEvent('click', function (e) {
                var Instance = QUI.Controls.getById(Upload.get('data-quiid'));

                if (!Instance) {
                    return;
                }

                var files = Instance.getFiles();

                if (!files.length) {
                    return;
                }

                e.stop();

                Instance.addEvent('finished', function () {
                    var Node = Elm.getParent(
                        '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile"]'
                    );

                    if (Node) {
                        Node.getElement('.quiqqer-fupc-category-items-item--active').click();
                    }
                });

                Instance.submit();
            });
        }
    });
});
