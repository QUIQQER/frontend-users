/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 * @event onSave [self]
 * @event onSaveBegin [self]
 * @event onSaveError [self]
 */

require.config({
    paths: {
        'HistoryEvents': URL_OPT_DIR + 'bin/history-events/dist/history-events.min'
    }
});

define('package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'URI',

    'Locale',
    'Ajax',
    'qui/utils/Form'

], function (QUI, QUIControl, QUILoader, URI, QUILocale, QUIAjax, QUIFormUtils) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile',

        Binds: [
            '$onInject',
            '$setUri',
            '$onChangeState',
            '$addFormEvents'
        ],

        options: {
            category     : false,
            windowHistory: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$category = null;
            this.$settings = null;
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
         * Resize the profile control
         *
         * @return {Promise}
         */
        resize: function () {
            var self      = this,
                Elm       = this.getElm(),
                Animation = Elm.getElement('.quiqqer-frontendUsers-controls-profile-categoryContentAnimation'),
                Menu      = Elm.getElement('.quiqqer-frontendUsers-controls-profile-categories');

            var newHeight = Math.max(
                Animation.getSize().y,
                Animation.getScrollSize().y,
                Menu.getSize().y
            );

            return new Promise(function (resolve) {
                moofx(self.$Elm).animate({
                    height: newHeight
                }, {
                    duration: 100,
                    callback: function () {
                        moofx(Animation).animate({
                            opacity: 1,
                            left   : 0
                        }, {
                            duration: 100,
                            callback: function () {
                                self.$parseContent().then(resolve);
                            }
                        });
                    }
                });
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this,
                Elm  = this.getElm();

            window.addEventListener('changestate', this.$onChangeState, false);

            this.Loader.inject(Elm);

            this.$bindCategoriesEvents();

            this.openSetting().then(function () {
                var Form     = Elm.getElement('.quiqqer-frontendUsers-controls-profile-categoryContent');
                var category = Form.get('data-category');
                var settings = Form.get('data-setting');

                self.$category = category;
                self.$settings = settings;

                self.$addFormEvents();
                self.fireEvent('load', [self]);
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm();

            window.addEventListener('changestate', this.$onChangeState, false);

            this.Loader.inject(Elm);
            this.$bindCategoriesEvents();

            this.$parseContent().then(function () {
                self.$addFormEvents();

                var Form = Elm.getElement('.quiqqer-frontendUsers-controls-profile-categoryContent');

                if (!Form) {
                    return;
                }

                var category = Form.get('data-category');
                var settings = Form.get('data-setting');

                self.$category = category;
                self.$settings = settings;

                self.$setMenuItemActive(category, settings);
            });
        },

        /**
         * Add events to forms
         */
        $addFormEvents: function () {
            var self = this;
            var Elm  = this.getElm();

            // setting form events
            var forms = Elm.getElements('form');

            forms.addEvent('submit', function (event) {
                event.stop();

                self.Loader.show();

                self.save().then(function () {
                    self.Loader.hide();
                    self.openSetting(self.$category, self.$settings);
                }, function () {
                    self.Loader.hide();
                });
            });
        },

        /**
         * event : on change location state
         */
        $onChangeState: function () {
            var pathName = window.location.pathname,
                url      = QUIQQER_SITE.url + '/' + this.$category + '/' + this.$settings;

            if (pathName !== url) {
                var requestPart = pathName.replace(QUIQQER_SITE.url, '');

                requestPart = requestPart.split('/');
                requestPart = requestPart.clean().filter(function (val) {
                    return val !== '';
                });

                if (typeof requestPart[0] !== 'undefined' && typeof requestPart[1] !== 'undefined') {
                    this.openSetting(
                        requestPart[0],
                        requestPart[1],
                        false
                    );
                }
            }
        },

        /**
         * parse the content and set the event handling
         *
         * @return {Promise}
         */
        $parseContent: function () {
            return QUI.parse(this.$Elm);
        },

        /**
         * Open a category setting
         *
         * @param {String} [category]
         * @param {String} [settings]
         * @param {Boolean} [setUrl]
         * @return Promise
         */
        openSetting: function (category, settings, setUrl) {
            var self = this,
                Elm  = this.getElm();

            if (typeof setUrl === 'undefined') {
                setUrl = true;
            }

            category = category || false;
            settings = settings || false;

            //if (self.$category === category && self.$settings === settings) {
            //    return Promise.resolve();
            //}

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

                        if (!result) {
                            result = '<div class="quiqqer-frontendUsers-controls-profile-categoryContentAnimation">' +
                                QUILocale.get(lg, 'controls.profile.Profile.setting_error') +
                                '</div>';
                        }

                        var Ghost = new Element('div', {
                            html: result
                        });

                        // build the form
                        if (!Form) {
                            var Control = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile"]'
                            );

                            self.$Elm.set('html', Control.get('html'));

                            Animation = Elm.getElement(
                                '.quiqqer-frontendUsers-controls-profile-categoryContentAnimation'
                            );

                            Form = self.$Elm.getElement(
                                '.quiqqer-frontendUsers-controls-profile-categoryContent'
                            );

                            self.$bindCategoriesEvents();
                        }

                        var styles  = Ghost.getElements('style');
                        var Content = Ghost.getElement(
                            '.quiqqer-frontendUsers-controls-profile-categoryContentAnimation'
                        );

                        Form.setStyle('height', height);

                        Animation.set({
                            html: Content.get('html')
                        });

                        styles.inject(Animation);

                        self.$setMenuItemActive(category, settings);

                        self.$category = category;
                        self.$settings = settings;

                        if (setUrl) {
                            self.$setUri();
                        }

                        (function () {
                            self.resize().then(resolve);
                        }).delay(100);

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
            var self       = this;
            var Elm        = this.getElm();
            var categories = Elm.getElements('.quiqqer-fupc-category');

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

            if (MobileCategories) {
                if (self.$category && self.$settings) {
                    MobileCategories.value = self.$category + ':' + self.$settings;
                }

                MobileCategories.addEvent('change', function () {
                    var value = MobileCategories.value;

                    if (value === '') {
                        return;
                    }

                    value = value.split(':');

                    if (value.length === 2) {
                        self.openSetting(value[0], value[1]);
                    }
                });
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
                    settings : self.$settings,
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
            if (this.getAttribute('windowHistory') === false) {
                return;
            }

            var newUrl = '/' + this.$category + '/' + this.$settings;

            if (QUIQQER_SITE.url !== '/') {
                newUrl = QUIQQER_SITE.url + newUrl;
            }

            if (newUrl.indexOf('false') !== -1) {
                return;
            }

            if ("history" in window) {
                window.history.pushState({}, "", newUrl);
                window.fireEvent('popstate');
            } else {
                window.location = newUrl;
            }
        },

        /**
         * Set an menu item active
         *
         * @param {String} category
         * @param {String} settings
         */
        $setMenuItemActive: function (category, settings) {
            var Item = this.$Elm.getElement(
                '[data-category="' + category + '"] [data-setting="' + settings + '"]'
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
