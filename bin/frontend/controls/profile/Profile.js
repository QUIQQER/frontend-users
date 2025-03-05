/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 * @event onSave [self]
 * @event onSaveBegin [self]
 * @event onSaveEnd [self] - Fires after the profile control has been reloaded after save
 * @event onSaveError [self, error]
 */

require.config({
    paths: {
        'HistoryEvents': URL_OPT_DIR + 'bin/quiqqer-asset/history-events/history-events/dist/history-events.min'
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

            this.$FX = null;

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
                Animation = Elm.querySelector('[data-name="content-animated"]');

            return new Promise(function (resolve) {
                self.$parseContent().then(() => {
                    if (self.$FX === null) {
                        self.$FX = moofx(self.$Elm);
                    }

                    self.$FX.animate({
                        height: self.$Elm.getSize().y
                    }, {
                        duration: 100,
                        callback: function () {
                            moofx(Animation).animate({
                                opacity: 1,
                                left   : 0
                            }, {
                                duration: 100,
                                callback: function () {
                                    self.$Elm.setStyle('height', null);

                                    resolve();
                                    self.$FX = null;
                                }
                            });
                        }
                    });
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
                var Form     = Elm.querySelector('[data-name="form"]');
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

                var Form = Elm.querySelector('[data-name="form"]');

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

                    self.openSetting(self.$category, self.$settings).then(function () {
                        self.fireEvent('saveEnd', [self]);
                    });
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

            if (url.indexOf('http://') !== -1 || url.indexOf('https://') !== -1) {
                pathName = window.location.href;
            }

            if (!this.$settings || !this.$category) {
                return;
            }

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

            var Animation = Elm.querySelector('[data-name="content-animated"]');

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
                        var Form   = self.$Elm.querySelector('[data-name="form"]');

                        if (!result) {
                            result = '<div class="quiqqer-frontendUsers-controls-profile-categoryContentAnimation" ' +
                                'data-name="content-animated">' +
                                QUILocale.get(lg, 'controls.profile.Profile.setting_error') +
                                '</div>';
                        }

                        var Ghost = new Element('div', {
                            html: result
                        });

                        // build the form
                        if (!Form) {
                            var Control = Ghost.querySelector(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile"]'
                            );

                            if (!Control) {
                                return;
                            }

                            self.$Elm.set('html', Control.get('html'));
                            Animation = Elm.querySelector('[data-name="content-animated"]');
                            self.$bindCategoriesEvents();
                        }

                        var styles  = Ghost.getElements('style');
                        var scripts = Ghost.getElements('script');

                        var Content = Ghost.querySelector('[data-name="content-animated"]');

                        if (!Content) {
                            return;
                        }

                        Animation.set({
                            html: Content.get('html')
                        });

                        styles.inject(Animation);

                        for (var i = 0, len = scripts.length; i < len; i++) {
                            if (scripts[i].src) {
                                scripts[i].inject(Animation);
                                continue;
                            }

                            new Element('script', {
                                html: scripts[i].get('html')
                            }).inject(Animation);
                        }

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
            let i, len, Header;
            const self       = this;
            const Elm        = this.getElm();
            const categories = Elm.querySelectorAll('[data-name="nav-category"]');

            var toggle = function () {
                var Category = this;

                if (this.getAttribute('data-name') !== 'nav-category') {
                    Category = this.getParent('[data-name="nav-category"]');
                }

                if (Category.getAttribute('data-open') === "1") {
                    Category.setAttribute('data-open', 0);
                    return;
                }

                Category.setAttribute('data-open', 1);
            };

            for (i = 0, len = categories.length; i < len; i++) {
                Header = categories[i].querySelector('[data-name="header"]');

                Header.addEvent('click', toggle);
            }

            // category settings click
            const items = Elm.getElements('[data-name="nav-category-item"]');

            items.addEvent('click', function (event) {
                event.stop();

                let Target = event.target;

                if (Target.getAttribute('data-name') !== 'nav-category-item') {
                    Target = Target.getParent('[data-name="nav-category-item"]');
                }

                const category = Target.getParent('[data-name="nav-category"]').get('data-category'),
                    setting  = Target.get('data-setting');

                self.$setMenuItemActive(category, setting);

                self.openSetting(category, setting);
            });


            // mobile
            const MobileCategories = Elm.getElement('[name="profile-categories-mobile"]');
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
            const self = this;

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
                    onError  : function (error) {
                        self.fireEvent('saveError', [self, error]);
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
         * Set a menu item active
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

            this.$Elm.querySelectorAll('[data-active]').forEach((ActiveItem) => {
                ActiveItem.removeAttribute('data-active');
            });

            Item.setAttribute('data-active', 1);
        }
    });
});
