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
                    Target.get('data-social-reg')
                );
            });
        },

        /**
         * load a social registrator
         *
         * @param {string} registrar
         */
        loadSocialRegistration: function (registrar) {
            return this.showTerms().then(function () {

                console.log(registrar);

                return this.showLoader();
            }.bind(this)).catch(function () {
                return this.hideTerms();
            }.bind(this));
        },

        /**
         *
         * @return {Promise}
         */
        $loadRegistrar: function (registrar) {
            var self         = this;
            var Registration = this.getElm().getElement('.quiqqer-fu-registrationSignIn-registration');

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (html) {
                    var Elm = self.getElm();

                    var Container = new Element('div', {
                        html: html
                    });

                    var RegistrarNode = Container.getElement(
                        '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                    );

                    Registration.set('html', RegistrarNode.get('html'));

                    QUI.parse(Elm).then(function () {
                        if (Elm.getElement('.quiqqer-frontendUsers-success') ||
                            Elm.getElement('.quiqqer-frontendUsers-pending')) {
                            self.fireEvent('register', [self]);
                        }

                        resolve();
                    }, reject);
                }, {
                    'package' : 'quiqqer/frontend-users',
                    registrar : registrar,
                    data      : JSON.encode({
                        termsOfUseAccepted: true
                    }),
                    registrars: self.getAttribute('registrars'),
                    onError   : reject
                });
            });
        },

        //region terms

        /**
         * Show the terms of use
         * - success if accepted
         *
         * @return {Promise}
         */
        showTerms: function () {
            var Terms    = document.getElement('.quiqqer-fu-registrationSignIn-terms');
            var children = document.getElement('.quiqqer-fu-registrationSignIn-registration').getChildren();

            children.setStyle('position', 'relative');

            return new Promise(function (resolve, reject) {
                moofx(children).animate({
                    left   : -30,
                    opacity: 0
                }, {
                    callback: function () {
                        Terms.setStyle('display', 'flex');
                        Terms.setStyle('position', 'absolute');

                        Terms.getElement('button[name="accept"]').addEvent('click', resolve);
                        Terms.getElement('button[name="decline"]').addEvent('click', reject);

                        moofx(Terms).animate({
                            left   : 0,
                            opacity: 1
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
                return !Child.hasClass('quiqqer-fu-registrationSignIn-terms');
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
            var Registration = this.getElm().getElement('.quiqqer-fu-registrationSignIn-registration'),
                children     = Registration.getChildren();

            return new Promise(function () {
                moofx(children).animate({
                    opacity: 0
                }, {
                    callback: function () {
                        require(['qui/controls/loader/Loader'], function (Loader) {
                            new Loader().inject(Registration).show();
                        });
                    }
                });
            });
        }
    });
});
