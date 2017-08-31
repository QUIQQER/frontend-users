/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/Window
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/Window', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'Locale',
    'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile'

], function (QUI, QUIPopup, QUILocale, Profile) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile',

        options: {
            maxHeight: 600,
            maxWidth : 800,
            buttons  : false
        },

        initialize: function (options) {
            this.setAttributes({
                icon : 'fa fa-user',
                title: QUILocale.get(lg, 'control.profile.window.title')
            });

            this.parent(options);

            this.$Profile = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            this.getContent().set('html', '');
            this.Loader.show();

            var hideLoader = function () {
                this.Loader.hide();
            }.bind(this);

            var showLoader = function () {
                this.Loader.show();
            }.bind(this);

            this.$Profile = new Profile({
                events: {
                    saveBegin  : showLoader,
                    onLoad     : hideLoader,
                    onSave     : hideLoader,
                    onSaveError: hideLoader
                }
            }).inject(this.getContent());
        }
    });
});