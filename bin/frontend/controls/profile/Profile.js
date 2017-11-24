/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 * @event onSave [self]
 * @event onSaveBegin [self]
 * @event onSaveError [self]
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'URI',

    'Ajax',
    'qui/utils/Form'

], function (QUI, QUIControl, QUILoader, URI, QUIAjax, QUIFormUtils) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile',

        Binds: [
            '$onInject',
            '$setUri'
        ],

        options: {
            category: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$category = null;
            this.Loader    = new QUILoader();

            this.addEvents({
                onInject: this.$onInject,
                onImport: this.$onImport
            });
        },

        /**
         * Create the DomNode element
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = this.parent();


            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this,
                Elm  = this.getElm();

            this.Loader.inject(Elm);

            this.$bindCategoriesEvents();

            this.openSetting().then(function () {
                // if (self.getAttribute('category')) {
                //     var categories = Elm.getElements(
                //         '.quiqqer-frontendUsers-controls-profile-category'
                //     );
                //
                //     categories = categories.filter(function (Category) {
                //         return Category.get('data-name') === self.getAttribute('category');
                //     });
                //
                //     if (categories.length) {
                //         self.$category = categories[0];
                //     }
                // }
                //
                // if (!self.$category) {
                //     var FirstCategory = Elm.getElement(
                //         '.quiqqer-frontendUsers-controls-profile-category'
                //     );
                //
                //     if (FirstCategory) {
                //         self.$category = FirstCategory.get('data-name');
                //     }
                // }

                self.fireEvent('load', [self]);
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm();

            this.Loader.inject(Elm);
            this.$bindCategoriesEvents();

            this.$parseContent(this.getElm()).then(function () {
                var Form     = Elm.getElement('.quiqqer-frontendUsers-controls-profile-categoryContent');
                var category = Form.get('data-category');
                var setting  = Form.get('data-setting');

                self.$setMenuItemActive(category, setting);
            });
        },

        /**
         * parse the content and set the event handling
         */
        $parseContent: function () {
            var self = this;

            return QUI.parse(this.$Elm).then(function () {
                var Elm = self.getElm();

                // category settings click
                var items = Elm.getElements('.quiqqer-fupc-category-items-item');

                items.addEvent('click', function (event) {
                    event.stop();

                    var Target = event.target;

                    if (!Target.hasClass('quiqqer-fupc-category-items-item')) {
                        Target = Target.getParent('.quiqqer-fupc-category-items-item');
                    }


                    var category = Target.getParent('.quiqqer-fupc-category').get('data-category'),
                        setting  = Target.get('data-setting');

                    self.$setMenuItemActive(category, setting);
                    self.openSetting(category, setting);
                });


                // mobile
                var MobileCategories = Elm.getElement(
                    '.quiqqer-frontendUsers-controls-profile-categories-mobile select'
                );

                if (self.$category) {
                    MobileCategories.value = self.$category;
                }

                MobileCategories.addEvent('change', function () {
                    self.openCategory(MobileCategories.value);
                });


                // setting form events
                var forms = Elm.getElements('form');

                forms.addEvent('submit', function (event) {
                    event.stop();

                    self.Loader.show();

                    self.save().then(function () {
                        self.Loader.hide();
                        self.openCategory(self.$category);
                    }, function () {
                        self.Loader.hide();
                    });
                });
            });
        },

        /**
         * Open a category setting
         *
         * @param {String} [category]
         * @param {String} [settings]
         */
        openSetting: function (category, settings) {
            var self = this,
                Elm  = this.getElm();

            category = category || false;
            settings = settings || false;

            var Animation = Elm.getElement(
                '.quiqqer-frontendUsers-controls-profile-categoryContentAnimation'
            );

            var Animate = new Promise(function (resolve) {
                if (!Animation) {
                    resolve();
                    return;
                }

                moofx(Animation).animate({
                    opacity: 0,
                    left   : -20
                }, {
                    duration: 250,
                    callback: resolve
                });
            });

            self.$setMenuItemActive(category, settings);

            return Animate.then(function () {
                return new Promise(function (resolve, reject) {
                    QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_getControl', function (result) {
                        var height = self.$Elm.getSize().y;
                        var Form   = self.$Elm.getElement(
                            '.quiqqer-frontendUsers-controls-profile-categoryContent'
                        );

                        var Ghost = new Element('div', {
                            html: result
                        });

                        Form.setStyle('height', height);

                        Animation.set({
                            html: Ghost.getElement('.quiqqer-frontendUsers-controls-profile-categoryContentAnimation').get('html')
                        });

                        self.$setMenuItemActive(category, settings);


                        var profileSize = Animation.getSize().y,
                            menuSize    = self.$Elm.getElement(
                                '.quiqqer-frontendUsers-controls-profile-categories'
                            ).getSize().y;


                        moofx(self.$Elm).animate({
                            height: Math.max(profileSize, menuSize)
                        }, {
                            duration: 200,
                            callback: function () {
                                moofx(Animation).animate({
                                    opacity: 1,
                                    left   : 0
                                }, {
                                    duration: 250,
                                    callback: function () {
                                        self.$parseContent().then(resolve);
                                    }
                                });
                            }
                        });

                        self.$category = category;
                        self.$setUri();
                    }, {
                        'package': 'quiqqer/frontend-users',
                        category : category,
                        settings : settings,
                        project  : JSON.encode({
                            name: QUIQQER_PROJECT.name,
                            lang: QUIQQER_PROJECT.lang
                        }),
                        siteId   : QUIQQER_SITE.id,
                        onError  : reject
                    });
                });
            });
        },

        /**
         * init category events
         */
        $bindCategoriesEvents: function () {
            var i, len, Header;
            var categories = this.getElm().getElements('.quiqqer-fupc-category');

            var toggle = function () {
                var Category = this;

                if (!this.hasClass('quiqqer-fupc-category')) {
                    Category = this.getParent('.quiqqer-fupc-category');
                }

                var Opener = Category.getElement('.quiqqer-fupc-category-header-opener');

                Opener.removeClass('fa-arrow-circle-o-right');
                Opener.removeClass('fa-arrow-circle-o-down');

                if (Category.hasClass('quiqqer-fupc-category--open')) {
                    Category.removeClass('quiqqer-fupc-category--open');
                    Opener.addClass('fa-arrow-circle-o-right');
                    return;
                }

                Category.addClass('quiqqer-fupc-category--open');
                Opener.addClass('fa-arrow-circle-o-down');
            };

            for (i = 0, len = categories.length; i < len; i++) {
                Header = categories[i].getElement('.quiqqer-fupc-category-header');

                new Element('span', {
                    'class': 'fa fa-arrow-circle-o-down quiqqer-fupc-category-header-opener'
                }).inject(Header);

                Header.addEvent('click', toggle);
            }
        },

        /**
         * Save
         *
         * @return {Promise}
         */
        save: function () {
            var self = this;

            this.fireEvent('saveBegin', [this]);

            var forms = this.getElm().getElements('form'),
                data  = {};

            forms.each(function (Form) {
                data = Object.merge(data, QUIFormUtils.getFormData(Form));
            });

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_profile_save', function () {
                    self.fireEvent('save', [self]);
                    resolve();
                }, {
                    'package': 'quiqqer/frontend-users',
                    category : self.$category,
                    data     : JSON.encode(data),
                    onError  : function () {
                        self.fireEvent('saveError', [self]);
                        reject();
                    }
                });
            });
        },

        /**
         * Set URI based on currently opened category
         */
        $setUri: function () {
            var Uri       = new URI();
            var UriParams = {
                c: this.$category
            };

            Uri.search(UriParams);

            var url = Uri.toString();

            if ("history" in window) {
                window.history.pushState({}, "", url);
                window.fireEvent('popstate');
            } else {
                window.location = url;
            }
        },

        /**
         * Set an menu item active
         * @param category
         * @param setting
         */
        $setMenuItemActive: function (category, setting) {
            var Item = this.$Elm.getElement(
                '[data-category="' + category + '"] [data-setting="' + setting + '"]'
            );

            if (!Item) {
                return;
            }

            this.$Elm.getElements('.quiqqer-fupc-category-items-item--active')
                .removeClass('quiqqer-fupc-category-items-item--active');

            Item.addClass('quiqqer-fupc-category-items-item--active');
        }
    });
});
