/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onRegister [this] - fires if the user successfully registers a user account
 * @event onQuiqqerFrontendUsersRegisterStart [this]
 * @event onQuiqqerFrontendUsersRegisterStart [this]
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',
    'Ajax',
    'Locale',
    'URI',

    'package/quiqqer/frontend-users/bin/Registration',
    'package/quiqqer/frontend-users/bin/frontend/controls/login/Login',

    'package/quiqqer/controls/bin/site/Window',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp.css'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, QUIAjax, QUILocale, URI, Registration, QUILogin, QUISiteWindow) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp',

        Binds: [
            '$onInject',
            '$onImport',
            '$onTrialClick',
            '$onMailCreateClick',
            '$onMailPasswordClick',
            '$onPasswordNextClick'
        ],

        options: {
            registrars     : [],    // list of registrar that are displayed in this controls
            useCaptcha     : false,
            emailIsUsername: false,
            submitregistrar: false, // instantly submit the registration form of this registrar if provided
            reloadOnSuccess: true,  // reload the page, if the user successfully registrated
            termsAccepted  : false
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader  = new QUILoader();
            this.$loaded = false;

            this.$RegistrationSection = null;
            this.$captchaResponse     = false;
            this.$tooltips            = {};
            this.$LoginControl        = null;
            this.$openSection         = 'email';

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * event: on import
         */
        $onImport: function (onInject) {
            var self = this,
                Node = this.getElm();

            this.Loader.inject(Node);
            this.Loader.show();

            QUI.fireEvent('quiqqerFrontendUsersRegisterStart', [this]);

            // redirect
            var Redirect = this.$Elm.getElement(
                '.quiqqer-fu-registrationSignUp-registration-redirect'
            );

            if (Redirect) {
                var redirectUrl = Redirect.get('data-redirecturl');

                (function () {
                    window.location = redirectUrl;
                }).delay(10000);
            }

            // if user, sign in is not possible
            if (window.QUIQQER_USER.id) {
                this.$RegistrationSection = this.getElm().getElement(
                    '.quiqqer-fu-registrationSignUp-registration'
                );

                this.$RegistrationSection.setStyle('opacity', 0);
                this.$RegistrationSection.setStyle('display', 'inline');

                moofx(
                    this.getElm().getElement('.quiqqer-fu-registrationSignUp-registration')
                ).animate({
                    opacity: 1
                }, {
                    callback: function () {
                        self.fireEvent('loaded', [self]);
                    }
                });

                this.Loader.hide();

                return;
            }

            if (parseInt(Node.get('data-qui-options-usecaptcha'))) {
                this.setAttribute('useCaptcha', Node.get('data-qui-options-usecaptcha'));
            }

            this.$RegistrationSection = this.getElm().getElement('.quiqqer-fu-registrationSignUp-registration');
            this.$TextSection         = this.getElm().getElement('.quiqqer-fu-registrationSignUp-info');
            this.$SocialLogins        = this.getElm().getElement('.quiqqer-fu-registrationSignUp-registration-social');

            this.$RegistrationSection.setStyle('opacity', 0);
            this.$RegistrationSection.setStyle('display', 'inline');

            if (!this.$TextSection) {
                this.$TextSection = new Element('div'); // fake element
            }

            this.$TextSection.setStyle('opacity', 0);
            this.$TextSection.setStyle('display', 'inline');

            moofx([
                this.$RegistrationSection,
                this.$TextSection
            ]).animate({
                opacity: 1
            });


            Node.getElements('.quiqqer-fu-registrationSignUp-terms a').set('target', '_blank');

            // social login click
            if (this.$SocialLogins) {
                this.$SocialLogins.getElements(
                    '.quiqqer-fu-registrationSignUp-registration-social-entry'
                ).addEvent('click', function (event) {
                    var Target = event.target;

                    if (!Target.hasClass('quiqqer-fu-registrationSignUp-registration-social-entry')) {
                        Target = Target.getParent('.quiqqer-fu-registrationSignUp-registration-social-entry');
                    }

                    self.loadSocialRegistration(
                        Target.get('data-registrar')
                    );
                });

                if (this.getAttribute('submitregistrar')) {
                    this.$submitRegistrar(this.getAttribute('submitregistrar'));
                }
            }

            // init
            this.$initMail();
            this.$initCaptcha();

            if (typeof onInject !== 'undefined' && onInject) {
                this.$loaded = true;
                this.fireEvent('loaded', [this]);

                this.Loader.hide();
            }
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_getSignInControl', function (result) {
                var Ghost = new Element('div', {
                    html: result
                });

                var Control = Ghost.getElement('.quiqqer-fu-registrationSignUp-registration');

                self.$RegistrationSection = self.getElm().getElement('.quiqqer-fu-registrationSignUp-registration');
                self.$RegistrationSection.setStyle('opacity', 0);
                self.$RegistrationSection.set('html', Control.get('html'));

                QUI.parse(self.$RegistrationSection).then(function () {
                    self.$onImport(true);

                    self.$TextSection.setStyles({
                        height: null,
                        width : null
                    });

                    moofx(self.$RegistrationSection).animate({
                        opacity: 1
                    });

                    self.$loaded = true;
                    self.fireEvent('loaded', [this]);
                });
            }, {
                'package': 'quiqqer/frontend-users',
                onError  : function (err) {
                    console.error(err);
                }
            });
        },

        /**
         * Is the control loaded?
         *
         * @return {boolean}
         */
        isLoaded: function () {
            return this.$loaded;
        },

        /**
         * load a social registrator
         *
         * @param {string} registrar
         */
        loadSocialRegistration: function (registrar) {
            var self = this;

            return this.showTerms(registrar, true).catch(function () {
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
                Terms = self.getElm().getElement('.quiqqer-fu-registrationSignUp-terms'),
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
                        'class': 'quiqqer-fu-registrationSignUp-terms-mail',
                        html   : QUILocale.get(lg, 'control.registration.sign.up.create.button')
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

        /**
         * Send the registrar form
         *
         * @param {HTMLFormElement} Form
         * @return {Promise}
         */
        $sendForm: function (Form) {
            this.Loader.show();

            var formData = QUIFormUtils.getFormData(Form);

            formData.termsOfUseAccepted = 1;

            return this.sendRegistration(
                Form.get('data-registrar'),
                Form.get('data-registration_id'),
                formData
            );
        },

        /**
         * Submit the email registration
         *
         * @param {string} registrar
         * @param {string} registration_id
         * @param {object} formData
         *
         * @return {Promise}
         */
        sendRegistration: function (registrar, registration_id, formData) {
            this.showLoader();

            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (Data) {
                    var Section = self.$RegistrationSection;

                    if (Data.userActivated) {
                        QUI.fireEvent('quiqqerFrontendUsersUserActivate', [
                            Data.userId,
                            Data.registrarHash,
                            Data.registrarType
                        ]);
                    }

                    moofx(Section).animate({
                        opacity: 0
                    }, {
                        duration: 250,
                        callback: function () {
                            var Ghost = new Element('div', {
                                html: Data.html
                            });

                            var Registration = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                            );

                            // we need no login?
                            var Login = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin"]'
                            );

                            if (Login) {
                                Login.destroy();
                            }

                            if (Ghost.getElement('.content-message-error')) {
                                Section.set('html', '');
                                Ghost.getElement('.content-message-error').inject(Section);
                            } else if (Registration) {
                                Section.set('html', Registration.get('html'));
                            } else {
                                Section.set('html', '');
                            }

                            QUI.parse(Section).then(function () {
                                if (Section.getElement('.content-message-success') ||
                                    Section.getElement('.content-message-information')) {

                                    self.fireEvent('register', [self, Data]);
                                    QUI.fireEvent('quiqqerFrontendUsersRegisterSuccess', [self, Data]);
                                }

                                if (Section.getElement('.content-message-success')) {
                                    var html = Section.getElement('.content-message-success').get('html').trim();

                                    if (html === '' && self.getAttribute('reloadOnSuccess')) {
                                        window.location.reload();
                                    }
                                }

                                var Redirect = Section.getElement('.quiqqer-frontendUsers-redirect');

                                if (Redirect && Redirect.get('data-instant') && self.getAttribute('reloadOnSuccess')) {
                                    window.location = Redirect.get('data-url');
                                }

                                if (Section.getElement('.content-message-error')) {
                                    (function () {
                                        self.$onInject();
                                    }).delay(5000);
                                }

                                self.hideTextSection().then(function () {
                                    self.Loader.hide();

                                    moofx(Section).animate({
                                        opacity: 1
                                    }, {
                                        callback: resolve
                                    });
                                });
                            }, reject);
                        }
                    });
                }, {
                    'package'           : 'quiqqer/frontend-users',
                    registrar           : registrar,
                    registration_id     : registration_id,
                    data                : JSON.encode(formData),
                    isSignUpRegistration: 1,
                    onError             : function (err) {
                        console.error(err);
                        reject(err);
                    }
                });
            });
        },

        //region terms

        /**
         * Show the terms of use
         * - success if accepted
         *
         * @param {String} registrar - registrar id
         * @param {Boolean} [isSocial] - is social registrar
         * @return {Promise}
         */
        showTerms: function (registrar, isSocial) {
            console.log('show terms', this.getAttribute('termsAccepted'));

            if (this.getAttribute('termsAccepted')) {
                return Promise.resolve();
            }

            var self     = this,
                Terms    = this.getElm().getElement('.quiqqer-fu-registrationSignUp-terms'),
                children = this.$RegistrationSection.getChildren();

            children = children.filter(function (Child) {
                return !Child.hasClass('quiqqer-fu-registrationSignUp-terms') &&
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

                            var links = Terms.getElements('a');

                            links.removeEvents('click');

                            links.addEvent('click', function (event) {
                                var Target = event.target;

                                if (Target.nodeName !== 'A') {
                                    Target = Target.getParent('a');
                                }

                                var href = Target.get('href');

                                if (href.indexOf('/') !== 0) {
                                    return;
                                }

                                event.stop();

                                new QUISiteWindow({
                                    closeButtonText: QUILocale.get(lg, 'btn.close'),
                                    showTitle      : true,
                                    project        : QUIQQER_PROJECT.name,
                                    lang           : QUIQQER_PROJECT.lang,
                                    siteUrl        : href
                                }).open();
                            });

                            if (typeOf(Control) === 'element') {
                                // mail
                                Control.addEvent('click', resolve);
                                return;
                            }

                            // create the social login form
                            var Form       = Terms.getElement('form');
                            var SocialForm = self.getElm().getElement('form[data-registrar="' + registrar + '"]');
                            var hidden     = SocialForm.getElements('[type="hidden"]');

                            Form.set('method', SocialForm.get('method'));
                            Form.set('data-registrar', SocialForm.get('data-registrar'));
                            Form.set('data-registration_id', SocialForm.get('data-registration_id'));

                            for (var i = 0, len = hidden.length; i < len; i++) {
                                hidden[i].clone().inject(Form);
                            }

                            Form.addEvent('submit', function (event) {
                                event.stop();

                                //if (!isSocial) {
                                self.$sendForm(Form).then(resolve);
                                //}
                            });
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
            var Terms    = this.getElm().getElement('.quiqqer-fu-registrationSignUp-terms');
            var children = this.$RegistrationSection.getChildren();

            children = children.filter(function (Child) {
                return !Child.hasClass('quiqqer-fu-registrationSignUp-terms') &&
                    !Child.hasClass('qui-loader');
            });

            if (!Terms) {
                return Promise.resolve();
            }

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

        // region social

        $submitRegistrar: function (registrar) {
            var self = this;

            this.Loader.show();

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (html) {
                    var Section = self.$RegistrationSection;

                    moofx(Section).animate({
                        opacity: 0
                    }, {
                        duration: 250,
                        callback: function () {
                            var Ghost = new Element('div', {
                                html: html
                            });

                            var Registration = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                            );

                            // we need no login?
                            var Login = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin"]'
                            );

                            if (Login) {
                                Login.destroy();
                            }

                            if (Ghost.getElement('.content-message-error')) {
                                Section.set('html', '');
                                Ghost.getElement('.content-message-error').inject(Section);
                            } else if (Registration) {
                                Section.set('html', Registration.get('html'));
                            } else {
                                Section.set('html', html);
                            }

                            QUI.parse(Section).then(function () {
                                if (Section.getElement('.content-message-success') ||
                                    Section.getElement('.content-message-information')) {

                                    self.fireEvent('register', [self]);
                                    QUI.fireEvent('quiqqerFrontendUsersRegisterSuccess', [self]);
                                }

                                if (Section.getElement('.content-message-success')) {
                                    var html = Section.getElement('.content-message-success').get('html').trim();

                                    if (html === '') {
                                        window.location.reload();
                                    }
                                }

                                var Redirect = Section.getElement('.quiqqer-frontendUsers-redirect');

                                if (Redirect && Redirect.get('data-instant')) {
                                    window.location = Redirect.get('data-url');
                                }

                                var ErrorBox = Section.getElement('.content-message-error');

                                if (ErrorBox) {
                                    new Element('a', {
                                        'class': 'quiqqer-fu-registrationSignUp-back',
                                        href   : '#',
                                        html   : QUILocale.get(lg, 'controls.RegistrationSignUp.error.back'),
                                        events : {
                                            click: function (event) {
                                                event.stop();

                                                var Url = URI(window.location);

                                                window.location = Url.origin() + Url.pathname();
                                            }
                                        }
                                    }).inject(ErrorBox);
                                }

                                self.hideTextSection().then(function () {
                                    moofx(Section).animate({
                                        opacity: 1
                                    }, {
                                        callback: resolve
                                    });
                                });

                                self.Loader.hide();
                            }, reject);
                        }
                    });
                }, {
                    'package': 'quiqqer/frontend-users',
                    registrar: registrar,
                    data     : JSON.encode([]),
                    onError  : function (err) {
                        console.error(err);
                        reject(err);
                    }
                });
            });
        },

        // endregion

        /**
         * Hide all elements and shows a loader
         *
         * @return {Promise}
         */
        showLoader: function () {
            var self     = this,
                children = this.$RegistrationSection.getChildren();

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
                            self.Loader = new Loader({
                                type  : 'fa-spinner',
                                styles: {
                                    background: 'transparent'
                                }
                            }).inject(self.$RegistrationSection);

                            self.Loader.show();
                            resolve(self.Loader);
                        });
                    }
                });
            });
        },

        /**
         * Hides the content / text section
         *
         * @return {Promise}
         */
        hideTextSection: function () {
            var self = this;

            return new Promise(function (resolve) {
                moofx(self.$TextSection).animate({
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        moofx(self.$TextSection).animate({
                            height : 0,
                            padding: 0,
                            width  : 0
                        }, {
                            duration: 250,
                            callback: function () {
                                self.$TextSection.setStyle('display', 'none');
                                resolve();
                            }
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
            var ButtonTrial   = this.getElm().getElement('[name="trial-account"]'),
                EmailNext     = this.getElm().getElement('[name="email-next"]'),
                PasswordNext  = this.getElm().getElement('[name="create-account"]'),
                EmailField    = this.getElm().getElement('[name="email"]'),
                PasswordField = this.getElm().getElement(
                    '.quiqqer-fu-registrationSignUp-email-passwordSection [name="password"]'
                );

            if (!EmailField) {
                this.Loader.hide();
                return;
            }

            if (ButtonTrial) {
                ButtonTrial.addEvent('click', this.$onTrialClick);
            }

            EmailNext.addEvent('click', this.$onMailCreateClick);

            if (PasswordNext) {
                PasswordNext.addEvent('click', this.$onPasswordNextClick);
            }

            this.getElm()
                .getElement('.quiqqer-fu-registrationSignUp-registration-email')
                .addEvent('submit', function (event) {
                    event.stop();
                });


            // email validation
            var self        = this,
                mailTimeout = null;

            //EmailField.addEvent('blur', function () {
            //    if (EmailField.get('data-no-blur-check')) {
            //        return;
            //    }
            //
            //    if (mailTimeout) {
            //        clearTimeout(mailTimeout);
            //    }
            //
            //    self.emailValidation(EmailField);
            //});
            //
            //EmailField.addEvent('keyup', function (event) {
            //    if (mailTimeout) {
            //        clearTimeout(mailTimeout);
            //    }
            //
            //    // workaround
            //    if (typeof event.code === 'undefined') {
            //        self.emailValidation(EmailField);
            //        event.stop();
            //        return;
            //    }
            //
            //    mailTimeout = (function () {
            //        self.emailValidation(EmailField);
            //    }).delay(2000);
            //});

            EmailField.addEvent('keydown', function (event) {
                if (event.key === 'enter') {
                    event.stop();
                    self.emailValidation(EmailField).then(function (isValid) {
                        if (isValid) {
                            self.$onMailCreateClick();
                        }
                    });
                }
            });

            if (PasswordField) {
                PasswordField.addEvent('keydown', function (event) {
                    if (event.key !== 'enter') {
                        return;
                    }

                    event.stop();
                    PasswordNext.click();
                });
            }
        },

        /**
         * init captcha
         */
        $initCaptcha: function () {
            var CaptchaContainer = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-captcha-display'
            );

            if (!CaptchaContainer) {
                return;
            }

            var self = this;

            var CaptchaDisplay = CaptchaContainer.getElement('.quiqqer-captcha-display'),
                Captcha        = QUI.Controls.getById(CaptchaDisplay.get('data-quiid'));

            var onCaptchaLoad = function () {
                var CaptchaResponseInput = self.getElm().getElement('input[name="captchaResponse"]'),
                    CaptchaDisplay       = CaptchaContainer.getElement('.quiqqer-captcha-display'),
                    Captcha              = QUI.Controls.getById(CaptchaDisplay.get('data-quiid'));

                Captcha.getCaptchaControl().then(function (CaptchaControl) {
                    CaptchaControl.addEvents({
                        onSuccess: function (response) {
                            self.$captchaResponse      = response;
                            CaptchaResponseInput.value = response;
                        },
                        onExpired: function () {
                            self.$captchaResponse      = false;
                            CaptchaResponseInput.value = '';

                            self.$resetMail();
                        }
                    });
                });
            };

            if (!Captcha) {
                CaptchaDisplay.addEvent('load', onCaptchaLoad);
                return;
            }

            onCaptchaLoad();
        },

        /**
         * create trial account
         */
        $onTrialClick: function () {
            var self         = this,
                Form         = this.getElm().getElement('[name="quiqqer-fu-registrationSignUp-email"]'),
                Email        = self.getElm().getElement('[name="email"]'),
                ButtonTrial  = this.getElm().getElement('[name="trial-account"]'),
                GoToPassword = this.getElm().getElement('[name="email-next"]');

            Email.set('disabled', true);
            ButtonTrial.set('disabled', true);
            GoToPassword.set('disabled', true);

            this.emailValidation(Email).then(function (isValid) {
                if (!isValid) {
                    return Promise.reject('isInValid');
                }

                Email.set('disabled', false);
                ButtonTrial.set('disabled', false);
                GoToPassword.set('disabled', false);

                var MailSection = self.getElm().getElement(
                    '.quiqqer-fu-registrationSignUp-email-mailSection'
                );

                return new Promise(function (resolve) {
                    moofx(MailSection).animate({
                        left   : -50,
                        opacity: 0
                    }, {
                        duration: 250,
                        callback: function () {
                            MailSection.setStyle('display', 'none');
                            self.$captchaCheck().then(resolve);
                        }
                    });
                });
            }).then(function () {
                return self.showTerms(Form.get('data-registrar'));
            }).then(function () {
                var Form     = self.getElm().getElement('form[name="quiqqer-fu-registrationSignUp-email"]'),
                    formData = {
                        termsOfUseAccepted: true,
                        trial_email       : Form.elements.email.value
                    };

                if (typeof Form.elements.captchaResponse !== 'undefined') {
                    formData.captchaResponse = Form.elements.captchaResponse.value;
                }

                return self.sendRegistration(
                    Form.elements['registration-trial-registrator'].value,
                    Form.get('data-registration_id'),
                    formData
                ).then(function () {
                    if (self.getElm().getElement('.content-message-error')) {
                        (function () {
                            moofx(self.$RegistrationSection).animate({
                                opacity: 0
                            }, {
                                duration: 250,
                                callback: function () {
                                    self.$onInject();
                                }
                            });
                        }).delay(2000);
                    }
                });
            }).catch(function (err) {
                if (err !== 'isInValid') {
                    self.hideTerms().then(function () {
                        self.$resetMail();
                    });
                }

                Email.set('disabled', false);
                ButtonTrial.set('disabled', false);
                GoToPassword.set('disabled', false);

                if (self.Loader) {
                    self.Loader.hide();
                }
            });
        },

        /**
         * account creation via mail
         * - 1. show password step
         * - 2. show captcha step
         * - 3. show term check
         */
        $onMailCreateClick: function (event) {
            // Stop form validation if this section is not shown
            if (event && this.$openSection !== 'email') {
                event.stop();
                return;
            }

            var MailSection = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-mailSection'),
                ButtonTrial = this.getElm().getElement('[name="trial-account"]'),
                EmailNext   = this.getElm().getElement('[name="email-next"]');

            var self      = this,
                Elm       = this.getElm(),
                MailInput = this.getElm().getElement('[name="email"]');

            if (MailInput.value === '') {
                self.fireEvent('error', [self, 'Mail empty']);
                return;
            }

            MailInput.set('disabled', true);
            EmailNext.set('disabled', true);

            if (ButtonTrial) {
                ButtonTrial.set('disabled', true);
            }

            this.emailValidation(MailInput).then(function (isValid) {
                if (!isValid) {
                    return Promise.reject('isInValid');
                }

                MailSection.setStyle('position', 'relative');

                MailInput.set('disabled', false);
                EmailNext.set('disabled', false);

                if (ButtonTrial) {
                    ButtonTrial.set('disabled', false);
                }

                moofx(MailSection).animate({
                    left   : -50,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        MailSection.setStyle('display', 'none');

                        var FullnameSection = Elm.getElement('.quiqqer-fu-registrationSignUp-email-fullnameSection'),
                            PasswordSection = Elm.getElement('.quiqqer-fu-registrationSignUp-email-passwordSection');

                        if (FullnameSection) {
                            self.$showFullnameSection();
                        } else if (PasswordSection) {
                            self.$showPasswordSection();
                        } else {
                            self.$captchaCheck().then(function () {
                                self.$onMailPasswordClick();
                            });
                        }
                    }
                });
            }).catch(function (err) {
                if (err !== 'isInValid') {
                    self.hideTerms();
                }

                MailInput.set('disabled', false);
                EmailNext.set('disabled', false);

                if (ButtonTrial) {
                    ButtonTrial.set('disabled', false);
                }

                self.fireEvent('error', [self, err]);
            });
        },

        /**
         * Shows password input section
         */
        $showPasswordSection: function () {
            var Elm     = this.getElm(),
                Section = Elm.getElement('.quiqqer-fu-registrationSignUp-email-passwordSection');

            Section.setStyle('opacity', 0);
            Section.setStyle('display', 'inline');
            Section.setStyle('left', 50);
            Section.setStyle('top', 0);

            moofx(Section).animate({
                left   : 0,
                opacity: 1
            }, {
                duration: 250,
                callback: function () {
                    Section.getElement('[type="password"]').focus();
                }
            });
        },

        /**
         * Shows full name input section
         */
        $showFullnameSection: function () {
            this.$openSection = 'fullname';

            var self           = this,
                Elm            = this.getElm(),
                Section        = Elm.getElement('.quiqqer-fu-registrationSignUp-email-fullnameSection'),
                NextBtn        = Section.getElement('[name="fullname-next"]'),
                FirstnameInput = Section.getElement('[name="firstname"]'),
                LastnameInput  = Section.getElement('[name="lastname"]');

            if (FirstnameInput) {
                FirstnameInput.addEvent('keyup', function (event) {
                    event.stop();

                    if (event.key === 'enter') {
                        next();
                    }
                });

                if (FirstnameInput.get('data-required')) {
                    FirstnameInput.required = "required";
                }
            }

            if (LastnameInput) {
                LastnameInput.addEvent('keyup', function (event) {
                    event.stop();

                    if (event.key === 'enter') {
                        next();
                    }
                });

                if (LastnameInput.get('data-required')) {
                    LastnameInput.required = "required";
                }
            }

            var next = function () {
                if (FirstnameInput) {
                    if (typeof FirstnameInput.checkValidity !== 'undefined' && FirstnameInput.checkValidity() === false) {
                        return;
                    }
                }

                if (LastnameInput) {
                    if (typeof LastnameInput.checkValidity !== 'undefined' && LastnameInput.checkValidity() === false) {
                        return;
                    }
                }

                moofx(Section).animate({
                    left   : -50,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        Section.setStyle('display', 'none');
                        self.$showPasswordSection();
                    }
                });
            };

            NextBtn.addEvent('click', function (event) {
                // Stop form validation if this section is not shown
                if (self.$openSection !== 'fullname') {
                    event.stop();
                }

                next();
            });

            Section.setStyle('opacity', 0);
            Section.setStyle('display', 'inline');
            Section.setStyle('left', 50);
            Section.setStyle('top', 0);

            moofx(Section).animate({
                left   : 0,
                opacity: 1
            }, {
                duration: 250,
                callback: function () {
                    if (FirstnameInput) {
                        FirstnameInput.focus();
                    } else if (LastnameInput) {
                        LastnameInput.focus();
                    }
                }
            });
        },

        /**
         * event: on password next click
         * - check captcha
         * - create account
         */
        $onPasswordNextClick: function (event) {
            var PasswordSection = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-passwordSection'
            );

            var PasswordField = PasswordSection.getElement('[type="password"]');

            if (PasswordField.value === '') {
                event.stop();
                PasswordField.focus();
                return;
            }

            var self = this;

            moofx(PasswordSection).animate({
                left   : -50,
                opacity: 0
            }, {
                duration: 250,
                callback: function () {
                    PasswordSection.setStyle('display', 'none');

                    self.$captchaCheck().then(function () {
                        self.$onMailPasswordClick();
                    });
                }
            });
        },

        /**
         * Check the captcha status
         * resolve = if captcha is solved
         *
         * @return {Promise}
         */
        $captchaCheck: function () {
            if (!this.getAttribute('useCaptcha')) {
                return Promise.resolve();
            }

            var CaptchaContainer = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-captcha-display'
            );

            if (!CaptchaContainer) {
                return Promise.resolve();
            }

            if (this.$captchaResponse) {
                return Promise.resolve();
            }

            var self    = this;
            var Display = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-captcha-display'
            );

            return new Promise(function (resolve) {
                if (self.$captchaResponse) {
                    return resolve();
                }

                Display.setStyle('opacity', 0);
                Display.setStyle('display', 'inline-block');

                var checkCaptchaInterval = function () {
                    return new Promise(function (resolve) {
                        if (self.$captchaResponse) {
                            resolve();
                            return;
                        }

                        (function () {
                            checkCaptchaInterval().then(resolve);
                        }).delay(200);
                    });
                };

                moofx(Display).animate({
                    opacity: 1
                }, {
                    duration: 250,
                    callback: function () {
                        checkCaptchaInterval().then(function () {
                            moofx(Display).animate({
                                opacity: 0
                            }, {
                                duration: 250,
                                callback: function () {
                                    Display.setStyle('opacity', 0);
                                    Display.setStyle('display', 'none');

                                    resolve();
                                }
                            });
                        });
                    }
                });
            });
        },

        /**
         * reset the mail stuff and shows the mail input
         *
         * @return {Promise}
         */
        $resetMail: function () {
            var self     = this;
            var Mail     = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-mailSection');
            var Captcha  = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-captcha-display');
            var Password = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-passwordSection');

            return new Promise(function (resolve) {
                self.$TextSection.setStyles({
                    height: null,
                    width : null
                });

                if (!Captcha) {
                    Captcha = new Element('div');
                }

                moofx([Captcha, Password]).animate({
                    left   : -50,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        Captcha.setStyle('display', 'none');

                        if (Password) {
                            Password.setStyle('display', 'none');
                        }

                        Mail.setStyle('opacity', 0);
                        Mail.setStyle('display', 'inline');

                        moofx(Mail).animate({
                            left   : 0,
                            opacity: 1
                        }, {
                            duration: 250,
                            callback: resolve
                        });
                    }
                });
            });
        },

        /**
         * account creation via mail - create the account
         * password is filled out
         */
        $onMailPasswordClick: function () {
            var self          = this,
                PasswordInput = this.getElm().getElement('[name="password"]'),
                Form          = this.getElm().getElement('[name="quiqqer-fu-registrationSignUp-email"]');

            if (PasswordInput) {
                if (PasswordInput.value === '') {
                    return;
                }

                if (typeof PasswordInput.checkValidity !== 'undefined' && PasswordInput.checkValidity() === false) {
                    return;
                }
            }

            this.showLoader().then(function () {
                return self.showTerms(Form.get('data-registrar'));
            }).then(function () {
                var childNodes = self.$RegistrationSection.getChildren();

                childNodes = childNodes.filter(function (Child) {
                    return !Child.hasClass('qui-loader');
                });

                childNodes.setStyle('display', 'none');

                return self.hideTerms().then(function () {
                    return self.showLoader();
                }).then(function () {
                    var Form     = self.getElm().getElement('form[name="quiqqer-fu-registrationSignUp-email"]'),
                        FormData = QUIFormUtils.getFormData(Form);

                    FormData.termsOfUseAccepted = true;

                    if (typeof Form.elements.captchaResponse !== 'undefined') {
                        FormData.captchaResponse = Form.elements.captchaResponse.value;
                    }

                    return self.sendRegistration(
                        Form.get('data-registrar'),
                        Form.get('data-registration_id'),
                        FormData
                    );
                });
            }).catch(function () {
                self.hideTerms().then(function () {
                    self.$resetMail();
                });
            });
        },

        /**
         * Validate the email field
         *
         * @param {HTMLInputElement} Field
         * @return {Promise}
         */
        emailValidation: function (Field) {
            var value = Field.value;
            var self  = this;

            var checkPromises = [
                Registration.emailValidation(value)
            ];

            if (this.getAttribute('emailIsUsername')) {
                checkPromises.push(Registration.usernameValidation(value));
            }

            if (this.getAttribute('emailIsUsername') === false) {
                var wasDisabled = Field.disabled;

                Field.disabled = false;

                if (typeof Field.checkValidity !== 'undefined' && Field.checkValidity() === false) {
                    Field.disabled = wasDisabled;

                    return Promise.resolve(false);
                }

                Field.disabled = wasDisabled;
            }

            return Promise.all(checkPromises).then(function (result) {
                var isValid = true;

                for (var i = 0, len = result.length; i < len; i++) {
                    if (!result[i]) {
                        isValid = false;
                        break;
                    }
                }

                if (isValid) {
                    return true;
                }

                //this.$handleInputValidation(
                //    Field,
                //    isValid,
                //    QUILocale.get(lg, 'exception.registrars.email.email_already_exists')
                //);

                if (this.$LoginControl) {
                    this.$LoginControl.destroy();
                }

                var RegistrationElm       = document.getElement('.quiqqer-fu-registrationSignUp-registration');
                var RegistrationElmParent = RegistrationElm.getParent();
                var RegistrationInfo      = document.getElement('.quiqqer-fu-registrationSignUp-info');

                var RegistrationElmSize = RegistrationElm.getComputedSize();
                var width               = RegistrationElmSize.width;
                var height              = RegistrationElmSize.height;
                var infoMarginSet       = false;

                RegistrationElmParent.setStyle('height', height);

                moofx(
                    RegistrationElm
                ).animate({
                    opacity: 0
                }, {
                    callback: function () {
                        if (!infoMarginSet) {
                            if (RegistrationInfo) {
                                RegistrationInfo.setStyle('margin-left', width);
                            }

                            infoMarginSet = true;
                        }

                        if (RegistrationElm) {
                            RegistrationElm.setStyle('display', 'none');
                        }
                    }
                });

                var loginHeight, LoginControlElm;

                this.$LoginControl = new QUILogin({
                    emailAddress: value,
                    events      : {
                        onLoadNoAnimation: function () {
                            LoginControlElm.setStyle('display', null);
                            LoginControlElm.addClass('quiqqer-fu-login-container-width');

                            if (RegistrationInfo) {
                                RegistrationInfo.setStyle('margin-left', null);
                            }

                            infoMarginSet = true;

                            loginHeight = LoginControlElm.getElement('.quiqqer-fu-login-container').getComputedSize().height;
                            RegistrationElmParent.setStyle('height', loginHeight);

                            // User info
                            QUI.getMessageHandler().then(function (MH) {
                                MH.addAttention(
                                    QUILocale.get(lg, 'control.registration.sign.up.email_already_exists')
                                );
                            });
                        },
                        onLoad           : function (LoginControl) {
                            RegistrationElmParent.setStyle('height', null);

                            LoginControlElm.getElement('.quiqqer-fu-login-email-mailSection input[type="password"]').focus();

                            var CloseBtn = new Element('span', {
                                'class': 'fa fa-close quiqqer-fu-registrationSignUp-login-close',
                                title  : QUILocale.get(lg, 'control.registration.sign.up.back_to_registration'),
                                events : {
                                    click: function () {
                                        self.$LoginControl.destroy();
                                        CloseBtn.destroy();

                                        RegistrationElm.setStyle('display', '');

                                        moofx(
                                            RegistrationElm
                                        ).animate({
                                            opacity: 1
                                        });
                                    }

                                }
                            }).inject(LoginControl.getElm());
                        }
                    }
                }).inject(RegistrationElm, 'before');

                LoginControlElm = this.$LoginControl.getElm();
                LoginControlElm.setStyle('display', 'none');

                return false;
            }.bind(this));
        },

        /**
         * Display error msg on invalid input
         *
         * @param {HTMLInputElement} Input
         * @param {Boolean} isValid
         * @param {String} [errorMsg]
         */
        $handleInputValidation: function (Input, isValid, errorMsg) {
            var self = this;

            require(['package/quiqqer/tooltips/bin/html5tooltips'], function () {
                if (!Input.get('data-has-tooltip') && isValid) {
                    return;
                }

                var tipId = Input.get('data-has-tooltip');
                var Tip   = null;

                if (typeof self.$tooltips[tipId] !== 'undefined') {
                    Tip = self.$tooltips[tipId];
                }

                if (Tip) {
                    Tip.hide();
                    Tip.destroy();
                    Input.set('data-has-tooltip', '');
                    delete self.$tooltips[tipId];
                }

                if (isValid) {
                    Input.removeClass('quiqqer-registration-field-error');
                    Input.set('data-has-tooltip', '');
                    return;
                }

                Tip   = new window.HTML5TooltipUIComponent();
                tipId = String.uniqueID();

                Tip.set({
                    target         : Input,
                    maxWidth       : "200px",
                    animateFunction: "scalein",
                    color          : "#ef5753",
                    stickTo        : "top",
                    contentText    : errorMsg
                });

                Tip.mount();
                Tip.show();

                Input.set('data-has-tooltip', tipId);
                Input.addClass('quiqqer-registration-field-error');

                self.$tooltips[tipId] = Tip;

                // destroy after 5 seconds
                (function () {
                    Tip.hide();
                    Tip.destroy();
                    Input.set('data-has-tooltip', '');
                    delete self.$tooltips[this];
                }).delay(3000, tipId);
            });
        }

        //endregion
    });
});
