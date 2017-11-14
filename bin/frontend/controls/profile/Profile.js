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

    'Ajax',
    'qui/utils/Form'

], function (QUI, QUIControl, QUILoader, QUIAjax, QUIFormUtils) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/profile/Profile',

        Binds: [
            '$onInject'
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

            this.openCategory().then(function () {
                if (self.getAttribute('category')) {
                    var categories = Elm.getElements(
                        '.quiqqer-frontendUsers-controls-profile-category'
                    );

                    categories = categories.filter(function (Category) {
                        return Category.get('data-name') === self.getAttribute('category');
                    });

                    if (categories.length) {
                        self.$category = categories[0];
                    }
                }

                if (!self.$category) {
                    var FirstCategory = Elm.getElement(
                        '.quiqqer-frontendUsers-controls-profile-category'
                    );

                    if (FirstCategory) {
                        self.$category = FirstCategory.get('data-name');
                    }
                }

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

            this.$parseContent(this.getElm()).then(function () {
                var Category = Elm.getElement(
                    '.quiqqer-frontendUsers-controls-profile-category-active'
                );

                if (!Category) {
                    Category = Elm.getElement(
                        '.quiqqer-frontendUsers-controls-profile-category'
                    );
                }

                self.$category = Category.get('data-name');

                Elm.getElements('[data-name="' + self.$category + '"]').addClass(
                    'quiqqer-frontendUsers-controls-profile-category-active'
                );
            });
        },

        /**
         * parse the content and set the event handling
         */
        $parseContent: function () {
            var self = this;

            return QUI.parse(this.$Elm).then(function () {
                var Elm        = self.getElm(),
                    categories = Elm.getElements('.quiqqer-frontendUsers-controls-profile-categories a'),
                    forms      = Elm.getElements('form');

                // category click
                categories.addEvent('click', function (event) {
                    event.stop();

                    var Target = event.target;

                    if (!Target.hasClass('quiqqer-frontendUsers-controls-profile-category')) {
                        Target = Target.getParent('.quiqqer-frontendUsers-controls-profile-category');
                    }

                    Elm.getElements(
                        '.quiqqer-frontendUsers-controls-profile-category-active'
                    ).removeClass('quiqqer-frontendUsers-controls-profile-category-active');

                    Target.addClass(
                        'quiqqer-frontendUsers-controls-profile-category-active'
                    );

                    self.openCategory(Target.get('data-name'));
                });

                forms.addEvent('submit', function (event) {
                    event.stop();

                    self.Loader.show();

                    self.save().then(function () {
                        self.Loader.hide();
                    }, function () {
                        self.Loader.hide();
                    });
                });
            });
        },

        /**
         * Open a category
         *
         * @param {String} [category]
         */
        openCategory: function (category) {
            var self = this,
                Elm  = this.getElm();

            category = category || false;

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

            return Animate.then(function () {
                return new Promise(function (resolve, reject) {

                    QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_getControl', function (result) {
                        var Ghost = new Element('div', {
                            html: result
                        });

                        self.$Elm.setStyle('height', self.$Elm.getSize().y);
                        self.$Elm.set(
                            'html',
                            Ghost.getElement('.quiqqer-frontendUsers-controls-profile').get('html')
                        );

                        self.$Elm.getElements('[data-name="' + category + '"]').addClass(
                            'quiqqer-frontendUsers-controls-profile-category-active'
                        );

                        var profileSize = self.$Elm.getElement(
                            '.quiqqer-frontendUsers-controls-profile-categoryContent'
                        ).getSize().y;

                        var menuSize = self.$Elm.getElement(
                            '.quiqqer-frontendUsers-controls-profile-categories'
                        ).getSize().y;

                        moofx(self.$Elm).animate({
                            height: Math.max(profileSize, menuSize)
                        }, {
                            duration: 250,
                            callback: function () {
                                self.$parseContent().then(resolve);
                            }
                        });

                        self.$category = category;
                    }, {
                        'package': 'quiqqer/frontend-users',
                        category : category,
                        onError  : reject
                    });
                });
            });
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
        }
    });
});
