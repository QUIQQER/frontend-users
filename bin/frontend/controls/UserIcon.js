/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/Registration
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onSelect [itemName, this]
 * @event onMenuShow [self, MenuElm]
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/UserIcon', [

    'qui/QUI',
    'qui/controls/Control',
    'controls/users/LogoutWindow',

    'Ajax',
    'Locale',

    'qui/controls/contextmenu/Menu',
    'qui/controls/contextmenu/Item',
    'qui/controls/contextmenu/Separator'

], function (QUI, QUIControl, LogoutWindow, QUIAjax, QUILocale, QUIMenu, QUIMenuItem, QUIMenuSeparator) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/UserIcon',

        Binds: [
            '$onImport'
        ],

        options: {
            menuPosition: 'bottom', // bottom | top
            showlogout  : true // enable logout entry in the menu
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });

            this.$Loader = null;
            this.$Menu   = null;
        },

        /**
         * event: on inject
         */
        $onImport: function () {
            var self    = this,
                Elm     = this.getElm(),
                IconElm = Elm.getElement('.quiqqer-frontendUsers-userIcon-icon');

            var corner = 'topRight';

            if (this.getAttribute('menuPosition') === 'top') {
                corner = 'bottomRight';
            }

            this.$Menu = new QUIMenu({
                corner: corner,
                events: {
                    onBlur: function (ProfileMenu) {
                        ProfileMenu.hide();
                    }
                }
            });

            this.$getCategories().then(function (categories) {
                var c, i, clen, items, Cat;

                var menuClick = function (Item) {
                    if (Item.getAttribute('url')) {
                        window.location = Item.getAttribute('url');
                    }

                    self.fireEvent('select', [Item.getAttribute('settings'), self]);
                };

                for (i in categories) {
                    if (!categories.hasOwnProperty(i)) {
                        continue;
                    }

                    Cat   = categories[i];
                    items = Cat.items;

                    for (c = 0, clen = items.length; c < clen; c++) {
                        self.$Menu.appendChild(
                            new QUIMenuItem({
                                title   : items[c].title,
                                text    : items[c].title,
                                icon    : items[c].icon,
                                url     : items[c].url,
                                category: Cat.name,
                                settings: items[c].name,
                                events  : {
                                    onClick: menuClick
                                }
                            })
                        );
                    }
                }
                
                if (self.getAttribute('showlogout')) {
                    self.$Menu.appendChild(new QUIMenuSeparator());

                    self.$Menu.appendChild(
                        new QUIMenuItem({
                            name  : 'profile',
                            title : QUILocale.get('quiqqer/system', 'logout'),
                            text  : QUILocale.get('quiqqer/system', 'logout'),
                            icon  : 'fa fa-sign-out',
                            events: {
                                onClick: function () {
                                    new LogoutWindow().open();
                                }
                            }
                        })
                    );
                }

                self.$Menu.inject(Elm, 'after');

                IconElm.addEvent('click', function (event) {
                    event.stop();

                    var menuPos = self.getAttribute('menuPosition'),
                        MenuElm = self.$Menu.getElm();

                    if (menuPos === 'bottom' || !menuPos) {
                        MenuElm.setStyles({
                            left: 0,
                            top : Elm.getSize().y + 10
                        });
                    } else {
                        MenuElm.setStyle('opacity', 0);
                        self.$Menu.show();

                        MenuElm.setStyles({
                            left: 0,
                            top : (MenuElm.getSize().y + 10) * -1
                        });

                        MenuElm.setStyle('opacity', null);
                    }

                    self.fireEvent('menuShow', [self, MenuElm]);

                    self.$Menu.show();
                    self.$Menu.focus();
                });

                self.fireEvent('load', [self]);
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
                });
            });
        }
    });
});
