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
    'Ajax',
    'qui/utils/Form'

], function (QUI, QUIControl, QUIAjax, QUIFormUtils) {
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

                    self.$category = FirstCategory.get('data-name');
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


                });


                forms.addEvent('submit', function (event) {
                    event.stop();
                    self.save();
                });
            });
        },

        /**
         * Open a category
         *
         * @param {String} [category]
         */
        openCategory: function (category) {
            var self = this;
            category = category || false;

            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_profile_getControl', function (result) {
                    self.$Elm.set('html', result);
                    self.$parseContent().then(resolve);
                }, {
                    'package': 'quiqqer/frontend-users',
                    category : category
                });
            });
        },

        /**
         * Save
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
                        resolve();
                    }
                });
            });
        }
    });
});
