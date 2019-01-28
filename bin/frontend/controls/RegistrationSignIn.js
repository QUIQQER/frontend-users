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
            '$onImport',
            '$onMailTestClick',
            '$onMailCreateClick',
            '$onMailPasswordClick'
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

            // mail
            this.$initMail();
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

                // mail registrar
                var MailRegistrar = Form.getElement(
                    '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email"]'
                );

                if (MailRegistrar) {
                    var Button = new Element('button', {
                        html: 'Account erstellen'
                    }).inject(MailRegistrar.getParent());

                    MailRegistrar.destroy();

                    Form.inject(Terms);
                    Form.addEvent('submit', function (event) {
                        event.stop();
                    });

                    return Button;
                }

                Form.inject(Terms);

                return QUI.parse(Form);
            }).then(function (Node) {
                if (typeOf(Node) === 'element') {
                    return Node;
                }

                var Container = Form.getFirst();

                if (!Container) {
                    return null;
                }

                return QUI.Controls.getById(Container.get('data-quiid'));
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
                        }).then(function (Control) {
                            Terms.getElement('button[name="decline"]').addEvent('click', reject);
                            Terms.setStyle('display', 'flex');
                            Terms.setStyle('position', 'absolute');

                            if (typeOf(Control) === 'element') {
                                Control.addEvent('click', resolve);
                            } else if (typeof Control === 'object') {
                                // @todo @events - on success and so on
                                Control.addEvent('', function () {

                                });
                            }
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
        },

        //region email

        /**
         * init mail registration
         */
        $initMail: function () {
            var ButtonTest    = this.getElm().getElement('[name="test-account"]'),
                CreateTest    = this.getElm().getElement('[name="go-to-password"]'),
                CreateAccount = this.getElm().getElement('[name="create-account"]');

            ButtonTest.addEvent('click', this.$onMailTestClick);
            CreateTest.addEvent('click', this.$onMailCreateClick);
            CreateAccount.addEvent('click', this.$onMailPasswordClick);

            this.getElm()
                .getElement('.quiqqer-fu-registrationSignIn-registration-email')
                .addEvent('submit', function (event) {
                    event.stop();
                });
        },

        /**
         * create test account
         */
        $onMailTestClick: function () {
            var self = this,
                Form = this.getElm().getElement('[name="quiqqer-fu-registrationSignIn-email"]');

            return this.showLoader().then(function () {
                return self.showTerms(Form.get('data-registrar'));
            }).then(function () {

                console.log(1234);

            });
        },

        /**
         * account creation via mail - next to password step
         */
        $onMailCreateClick: function () {
            var MailSection     = this.getElm().getElement('.quiqqer-fu-registrationSignIn-email-mailSection');
            var PasswordSection = this.getElm().getElement('.quiqqer-fu-registrationSignIn-email-passwordSection');

            var MailInput = this.getElm().getElement('[name="email"]');

            if (MailInput.value === '') {
                return;
            }

            if (typeof MailInput.checkValidity !== 'undefined' && MailInput.checkValidity() === false) {
                return;
            }

            // @todo check mail
            // @todo use captcha

            MailSection.setStyle('position', 'relative');

            moofx(MailSection).animate({
                left   : -50,
                opacity: 0
            }, {
                duration: 250,
                callback: function () {
                    MailSection.setStyle('display', 'none');

                    PasswordSection.setStyle('opacity', 0);
                    PasswordSection.setStyle('display', 'inline');

                    moofx(PasswordSection).animate({
                        left   : 0,
                        opacity: 1
                    }, {
                        duration: 250
                    });
                }
            });
        },

        /**
         * account creation via mail - create the account
         * password is filled out
         */
        $onMailPasswordClick: function () {
            var self          = this,
                PasswordInput = this.getElm().getElement('[name="password"]'),
                Form          = this.getElm().getElement('[name="quiqqer-fu-registrationSignIn-email"]');

            if (PasswordInput.value === '') {
                return;
            }

            if (typeof PasswordInput.checkValidity !== 'undefined' &&
                PasswordInput.checkValidity() === false) {
                return;
            }

            this.showLoader().then(function () {
                return self.showTerms(Form.get('data-registrar'));
            }).then(function () {
                var childNodes = self.getElm()
                                     .getElement('.quiqqer-fu-registrationSignIn-registration')
                                     .getChildren();

                childNodes = childNodes.filter(function (Child) {
                    return !Child.hasClass('qui-loader');
                });

                childNodes.setStyle('display', 'none');

                return self.hideTerms().then(function () {
                    return self.showLoader();
                }).then(function () {
                    return self.sendAccountCreationViaEmail();
                });
            }).catch(function (err) {
                console.error(err);
                self.hideTerms();
            });
        },

        /**
         * Submit the email registration
         *
         * @return {Promise}
         */
        sendAccountCreationViaEmail: function () {
            this.showLoader();

            var self     = this,
                Form     = this.getElm().getElement('form[name="quiqqer-fu-registrationSignIn-email"]'),
                formData = {
                    termsOfUseAccepted: true,
                    email             : Form.elements.email.value,
                    password          : Form.elements.password.value
                };

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (html) {
                    var Section = self.getElm().getElement('.quiqqer-fu-registrationSignIn-registration');

                    moofx(Section).animate({
                        opacity: 0
                    }, {
                        duration: 250,
                        callback: function () {
                            Section.set('html', html);

                            moofx(Section).animate({
                                opacity: 1
                            }, {
                                callback: resolve
                            });
                        }
                    });
                }, {
                    'package'      : 'quiqqer/frontend-users',
                    registrar      : Form.get('data-registrar'),
                    registration_id: Form.get('data-registration_id'),
                    data           : JSON.encode(formData),
                    onError        : reject
                });
            });
        },

        sendRegistratoinTrial: function () {

        }

        //endregion
    });
});
