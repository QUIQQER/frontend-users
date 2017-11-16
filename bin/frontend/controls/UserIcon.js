/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/Registration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/UserIcon', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/users/LogoutWindow',

    'Ajax',

    'qui/controls/contextmenu/Menu',
    'qui/controls/contextmenu/Item',
    'qui/controls/contextmenu/Separator'

], function (QUI, QUIControl, LogoutWindow, QUIAjax, QUIMenu, QUIMenuItem, QUIMenuSeparator) {
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
            var self    = this;
            var Elm     = this.getElm();
            var IconElm = Elm.getElement('.quiqqer-frontendUsers-userIcon-icon');

            var ProfileMenu = new QUIMenu({
                corner: 'topRight',
                events: {
                    onBlur: function (ProfileMenu) {
                        ProfileMenu.hide();
                    }
                }
            });

            this.$getCategories().then(function (categories) {
                for (var i = 0, len = categories.length; i < len; i++) {
                    var Cat = categories[i];

                    ProfileMenu.appendChild(
                        new QUIMenuItem({
                            name  : Cat.name,
                            title : Cat.title,
                            text  : Cat.title,
                            icon  : Cat.icon,
                            events: {
                                onClick: function (Item) {
                                    console.log(Item.getAttribute('name'));
                                }
                            }
                        })
                    );
                }

                ProfileMenu.appendChild(new QUIMenuSeparator());

                ProfileMenu.appendChild(
                    new QUIMenuItem({
                        name  : 'profile',
                        title : 'Logout', // #locale
                        text  : 'Logout',
                        icon  : 'fa fa-sign-out',
                        events: {
                            onClick: function () {
                                new LogoutWindow().open();
                            }
                        }
                    })
                );

                ProfileMenu.inject(Elm, 'after');

                IconElm.addEvent('click', function(event) {
                    event.stop();
                    ProfileMenu.show();

                    //ProfileMenu.getElm().setStyles({
                    //    left : null,
                    //    right: 10,
                    //    top  : 70
                    //});

                    ProfileMenu.focus();
                });
            });
        },

        /**
         * Get all categories that are to be shown in the Profile menu
         *
         * @return {Promise}
         */
        $getCategories: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_getProfileBarCategories', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError  : reject
                })
            });
        }
    });
});
