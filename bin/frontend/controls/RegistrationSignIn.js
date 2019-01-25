/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignIn
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignIn', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax'

], function (QUI, QUIControl, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignIn',

        Binds: [
            '$onImport'
        ],

        options: {
            registrars: [] // list of registrar that are displayed in this controls
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Node = this.getElm();

            Node.getElements('.quiqqer-fu-registrationSignIn-terms a')
                .set('target', '_blank');

            // social login click
            Node.getElements(
                '.quiqqer-fu-registrationSignIn-registration-social-entry'
            ).addEvent('click', function (event) {
                var Target = event.target;

                if (!Target.hasClass('quiqqer-fu-registrationSignIn-registration-social-entry')) {
                    Target = Target.getParent('.quiqqer-fu-registrationSignIn-registration-social-entry');
                }

                self.loadSocialRegistration(
                    Target.get('data-registrar')
                );
            });
        },

        /**
         * load a social registrator
         *
         * @param {string} registrar
         */
        loadSocialRegistration: function (registrar) {
            var self = this;

            return this.showTerms(registrar).catch(function () {
                self.Loader.hide();
                return self.hideTerms();
            });
        },

        /**
         * Load the registrar
         *
         * @return {Promise}
         */
        $loadRegistrar: function (registrar) {
            var self  = this,
                Terms = self.getElm().getElement('.quiqqer-fu-registrationSignIn-terms'),
                Form  = Terms.getElement('form');

            return this.$getRegistrar(registrar).then(function (result) {
                if (!Form) {
                    Form = new Element('form', {
                        method: 'POST'
                    });
                }

                Form.set('html', result);
                Form.inject(Terms);

                return QUI.parse(Form);
            }).then(function () {
                var Container = Form.getFirst(),
                    Control   = QUI.Controls.getById(Container.get('data-quiid'));

                console.warn(Control);
            });
        },

        //region terms

        /**
         * Show the terms of use
         * - success if accepted
         *
         * @param {String} registrar - registrar id
         * @return {Promise}
         */
        showTerms: function (registrar) {
            var self     = this,
                Terms    = this.getElm().getElement('.quiqqer-fu-registrationSignIn-terms'),
                children = this.getElm().getElement('.quiqqer-fu-registrationSignIn-registration').getChildren();

            children = children.filter(function (Child) {
                return !Child.hasClass('quiqqer-fu-registrationSignIn-terms') &&
                    !Child.hasClass('qui-loader');
            });

            children.setStyle('position', 'relative');

            return new Promise(function (resolve, reject) {
                moofx(children).animate({
                    left   : -30,
                    opacity: 0
                }, {
                    callback: function () {
                        self.showLoader().then(function () {
                            return self.$loadRegistrar(registrar);
                        }).then(function () {
                            Terms.getElement('button[name="decline"]').addEvent('click', reject);
                            Terms.setStyle('display', 'flex');
                            Terms.setStyle('position', 'absolute');
                        }).then(function () {
                            self.Loader.hide();

                            moofx(Terms).animate({
                                left   : 0,
                                opacity: 1
                            });
                        });
                    }
                });
            });
        },

        /**
         * hide the terms
         *
         * @return {Promise}
         */
        hideTerms: function () {
            var Terms    = this.getElm().getElement('.quiqqer-fu-registrationSignIn-terms');
            var children = this.getElm().getElement('.quiqqer-fu-registrationSignIn-registration').getChildren();

            children = children.filter(function (Child) {
                return !Child.hasClass('quiqqer-fu-registrationSignIn-terms') &&
                    !Child.hasClass('qui-loader');
            });

            return new Promise(function (resolve) {
                moofx(Terms).animate({
                    left   : -30,
                    opacity: 0
                }, {
                    callback: function () {
                        Terms.setStyle('display', 'none');

                        moofx(children).animate({
                            left   : 0,
                            opacity: 1
                        }, {
                            callback: resolve
                        });
                    }
                });
            });
        },

        //endregion

        /**
         * Hide all elements and shows a loader
         *
         * @return {Promise}
         */
        showLoader: function () {
            var self         = this,
                Registration = this.getElm().getElement('.quiqqer-fu-registrationSignIn-registration'),
                children     = Registration.getChildren();

            return new Promise(function (resolve) {
                moofx(children).animate({
                    opacity: 0
                }, {
                    callback: function () {
                        if (self.Loader) {
                            self.Loader.show();
                            resolve(self.Loader);
                            return;
                        }

                        require(['qui/controls/loader/Loader'], function (Loader) {
                            self.Loader = new Loader().inject(Registration);
                            self.Loader.show();
                            resolve(self.Loader);
                        });
                    }
                });
            });
        },

        /**
         * return the wanted registrar control
         *
         * @param registrar
         * @return {Promise}
         */
        $getRegistrar: function (registrar) {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_getControl', resolve, {
                    'package': 'quiqqer/frontend-users',
                    registrar: registrar
                });
            });
        }
    });
});
