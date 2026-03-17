/**
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
    'package/quiqqer/frontend-users/bin/frontend/classes/Registration',

    'package/quiqqer/frontend-users/bin/Registration',
    'package/quiqqer/frontend-users/bin/frontend/controls/login/Login',
    'package/quiqqer/frontend-users/bin/frontend/controls/login/Window',

    'package/quiqqer/controls/bin/site/Window',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp.css'

], function (
    QUI,
    QUIControl,
    QUILoader,
    QUIFormUtils,
    QUIAjax,
    QUILocale,
    URI,
    ResendActivationLinkBtn,
    Registration,
    QUILogin,
    QUILoginWindow,
    QUISiteWindow
) {
    "use strict";

    const lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/RegistrationSignUp',

        Binds: [
            '$onInject',
            '$onImport',
            '$onMailCreateClick',
            '$onMailPasswordClick',
            '$onPasswordNextClick',
            '$initResendActivationLink',
            '$initLoginButton',
            '$openLoginWindow',
            '$cacheRegistrationViews',
            '$showLoginControl',
            '$showRegistrationControl',
            '$animateRegistrationViewChange',
            '$destroyLoginControl'
        ],

        options: {
            registrars: [],    // list of registrar that are displayed in this controls
            useCaptcha: false,
            emailIsUsername: false,
            submitregistrar: false, // instantly submit the registration form of this registrar if provided
            reloadOnSuccess: true,  // reload the page, if the user successfully registrated
            termsAccepted: false
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();
            this.$loaded = false;

            this.$RegistrationSection = null;
            this.$captchaResponse = false;
            this.$tooltips = {};
            this.$LoginControl = null;
            this.$openSection = 'email';
            this.$RegistrationInner = null;
            this.$RegistrationContainer = null;
            this.$TermsContainer = null;
            this.$TermsView = null;
            this.$LoginContainer = null;
            this.$LoginView = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
            });
        },

        /**
         * event: on import
         */
        $onImport: function (onInject) {
            const self = this,
                Node = this.getElm();
            let isStatus = false;

            this.Loader.inject(Node);
            this.Loader.show();

            QUI.fireEvent('quiqqerFrontendUsersRegisterStart', [this]);

            // redirect
            const Redirect = this.$Elm.getElement('[data-name="redirect-msg"]');

            if (Redirect) {
                const redirectUrl = Redirect.get('data-redirecturl');

                (function () {
                    window.location = redirectUrl;
                }).delay(10000);
            }

            if (Node.querySelector('[data-name="status"]')) {
                isStatus = true;
            }

            // if user, sign in is not possible
            if (window.QUIQQER_USER.id || isStatus) {
                Node.setStyle('opacity', 0);

                moofx(Node).animate({
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
            this.$TextSection = this.getElm().getElement('.quiqqer-fu-registrationSignUp-info');
            this.$SocialLogins = this.getElm().getElement('.quiqqer-fu-registrationSignUp-registration-social');
            this.$cacheRegistrationViews();

            this.$RegistrationSection.setStyle('opacity', 0);
            this.$RegistrationSection.setStyle('display', 'block');

            if (!this.$TextSection) {
                this.$TextSection = new Element('div'); // fake element
            }

            this.$TextSection.setStyle('opacity', 0);
            this.$TextSection.setStyle('display', null);

            moofx([
                this.$RegistrationSection,
                this.$TextSection
            ]).animate({
                opacity: 1
            });


            Node.getElements('[data-name="terms-view"] a').set('target', '_blank');

            // social login click
            if (this.$SocialLogins) {
                this.$SocialLogins.getElements(
                    '.quiqqer-fu-registrationSignUp-registration-social-entry'
                ).addEvent('click', function (event) {
                    let Target = event.target;

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
            this.$initResendActivationLink();
            this.$initLoginButton();

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
            const self = this;

            QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_getSignInControl', function (result) {
                const Ghost = new Element('div', {
                    html: result
                });

                const Control = Ghost.getElement('.quiqqer-fu-registrationSignUp-registration');

                self.$RegistrationSection = self.getElm().getElement('.quiqqer-fu-registrationSignUp-registration');
                self.$RegistrationSection.setStyle('opacity', 0);
                self.$RegistrationSection.set('html', Control.get('html'));

                QUI.parse(self.$RegistrationSection).then(function () {
                    self.$onImport(true);

                    self.$TextSection.setStyles({
                        height: null,
                        width: null
                    });

                    moofx(self.$RegistrationSection).animate({
                        opacity: 1
                    });

                    self.$loaded = true;
                    self.fireEvent('loaded', [this]);
                });
            }, {
                'package': 'quiqqer/frontend-users',
                onError: function (err) {
                    console.error(err);
                }
            });
        },

        /**
         * Show activation mail resend action for expired activation links.
         */
        $initResendActivationLink: function () {
            var ResendElm = this.getElm().getElement('.quiqqer-fu-registrationSignUp-resend');
            var BtnElm = this.getElm().getElement('.quiqqer-fu-registrationSignUp-resend-btn');

            if (!ResendElm || !BtnElm) {
                return;
            }

            var MsgElm = this.getElm().getElement('.quiqqer-fu-registrationSignUp-resend-msg');
            var EmailInput = this.getElm().getElement('input[name="email"]');
            var email = ResendElm.get('data-email');

            if (!email && EmailInput) {
                email = EmailInput.value;
            }

            if (!email) {
                return;
            }

            new ResendActivationLinkBtn({
                email: email,
                events: {
                    onResendSuccess: function () {
                        MsgElm.set(
                            'html',
                            QUILocale.get(
                                lg,
                                'RegistrationSignUp.message.error.activation_expired.resend_success'
                            )
                        );
                    },
                    onResendFail: function () {
                        MsgElm.set(
                            'html',
                            QUILocale.get(
                                lg,
                                'RegistrationSignUp.message.error.activation_expired.resend_fail'
                            )
                        );
                    }
                }
            }).inject(BtnElm);
        },

        /**
         * Register click handler for login CTA in error state.
         */
        $initLoginButton: function () {
            var LoginBtn = this.getElm().getElement('.quiqqer-fu-registrationSignUp-login-btn');

            if (!LoginBtn) {
                return;
            }

            LoginBtn.removeEvents('click');
            LoginBtn.addEvent('click', this.$openLoginWindow);
        },

        /**
         * Open login popup.
         */
        $openLoginWindow: function (event) {
            if (event) {
                event.stop();
            }

            new QUILoginWindow({
                'show-registration-link': true
            }).open();
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
         * Cache the registration views.
         */
        $cacheRegistrationViews: function () {
            const Elm = this.getElm();

            this.$RegistrationInner = Elm.getElement('[data-name="registration-inner"]');
            this.$RegistrationContainer = Elm.getElement('[data-name="registration"]');
            this.$TermsContainer = Elm.getElement('[data-name="terms"]');
            this.$TermsView = Elm.getElement('[data-name="terms-view"]');
            this.$LoginContainer = Elm.getElement('[data-name="login"]');
            this.$LoginView = Elm.getElement('[data-name="login-view"]');
            this.$Address = Elm.getElement('[data-name="address"]');
        },

        /**
         * Animate between registration states.
         *
         * @param {HTMLElement} fromView
         * @param {HTMLElement} toView
         * @return {Promise}
         */
        $animateRegistrationViewChange: function (fromView, toView) {
            if (!this.$RegistrationInner || !fromView || !toView || fromView === toView) {
                return Promise.resolve();
            }

            const inner = this.$RegistrationInner;
            const currentHeight = fromView.getBoundingClientRect().height ||
                fromView.offsetHeight ||
                fromView.getSize().y ||
                inner.getSize().y;

            const fromViewDisplay = fromView.style.display;
            const fromViewOpacity = fromView.style.opacity;
            const toViewDisplay = toView.style.display;
            const toViewOpacity = toView.style.opacity;
            const toViewVisibility = toView.style.visibility;
            const toViewPosition = toView.style.position;
            const toViewLeft = toView.style.left;
            const toViewTop = toView.style.top;
            const toViewWidth = toView.style.width;

            fromView.setStyles({
                display: 'none',
                opacity: 0
            });

            toView.setStyles({
                display: 'block',
                opacity: 0,
                visibility: 'hidden',
                position: '',
                left: '',
                top: '',
                width: ''
            });

            const nextHeight = inner.getBoundingClientRect().height ||
                toView.getBoundingClientRect().height ||
                toView.offsetHeight ||
                toView.scrollHeight ||
                currentHeight;

            fromView.style.display = fromViewDisplay;
            fromView.style.opacity = fromViewOpacity;

            toView.style.display = toViewDisplay;
            toView.style.opacity = toViewOpacity;
            toView.style.visibility = toViewVisibility;
            toView.style.position = toViewPosition;
            toView.style.left = toViewLeft;
            toView.style.top = toViewTop;
            toView.style.width = toViewWidth;

            inner.setStyles({
                height: currentHeight,
                overflow: 'hidden'
            });

            return new Promise((resolve) => {
                moofx(fromView).animate({
                    opacity: 0
                }, {
                    duration: 250,
                    callback: () => {
                        fromView.setStyles({
                            display: 'none',
                            opacity: 0
                        });

                        toView.setStyles({
                            display: 'block',
                            opacity: 0
                        });

                        moofx(inner).animate({
                            height: nextHeight
                        }, {
                            duration: 250
                        });

                        moofx(toView).animate({
                            opacity: 1
                        }, {
                            duration: 250,
                            callback: () => {
                                inner.setStyles({
                                    height: '',
                                    overflow: ''
                                });

                                resolve();
                            }
                        });
                    }
                });
            });
        },

        /**
         * Destroy the current login control and clear the login view.
         */
        $destroyLoginControl: function () {
            if (this.$LoginControl) {
                this.$LoginControl.destroy();
                this.$LoginControl = null;
            }

            if (this.$LoginContainer) {
                this.$LoginContainer.setStyles({
                    display: 'none',
                    opacity: ''
                });
            }

            if (this.$LoginView) {
                this.$LoginView.empty();
                this.$LoginView.setStyles({
                    display: 'none',
                    opacity: '',
                    position: '',
                    visibility: '',
                    left: '',
                    top: '',
                    width: ''
                });
            }
        },

        /**
         * Show the inline login control.
         *
         * @param {string} email
         * @return {Promise}
         */
        $showLoginControl: function (email) {
            if (!this.$RegistrationContainer || !this.$LoginContainer || !this.$LoginView) {
                return Promise.resolve(false);
            }

            this.$destroyLoginControl();

            const container = this.$LoginView;

            const existMessage = new Element('div', {
                'class': 'q-message q-message-warning',
                html: QUILocale.get(lg, 'control.registration.sign.up.email_already_exists')
            }).inject(container);

            const closeBtn = new Element('button', {
                'class': 'quiqqer-fu-registrationSignUp-login-close btn btn-close btn-rounded',
                title: QUILocale.get(lg, 'control.registration.sign.up.back_to_registration'),
                html: '<i class="fa fa-close"></i>',
                events: {
                    click: () => {
                        this.$showRegistrationControl();
                    }
                }
            }).inject(container);

            container.setStyles({
                display: 'block',
                opacity: 0,
                position: 'absolute',
                visibility: 'hidden',
                left: 0,
                top: 0,
                width: '100%'
            });

            return new Promise((resolve) => {
                this.$LoginControl = new QUILogin({
                    emailAddress: email,
                    events: {
                        onLoad: () => {
                            container.setStyles({
                                opacity: 1,
                                position: '',
                                visibility: '',
                                left: '',
                                top: '',
                                width: ''
                            });

                            this.Loader.hide();

                            this.$animateRegistrationViewChange(
                                this.$RegistrationContainer,
                                this.$LoginContainer
                            ).then(function () {
                                resolve(false);
                            });
                        }
                    }
                });

                this.$LoginControl.inject(container);
            });
        },

        /**
         * Switch back to the registration control.
         *
         * @return {Promise}
         */
        $showRegistrationControl: function () {
            if (!this.$RegistrationContainer || !this.$LoginContainer ||
                this.$LoginContainer.getStyle('display') === 'none') {
                return Promise.resolve();
            }

            return this.$animateRegistrationViewChange(
                this.$LoginContainer,
                this.$RegistrationContainer
            ).then(() => {
                this.$destroyLoginControl();
            });
        },

        /**
         * load a social registrator
         *
         * @param {string} registrar
         */
        loadSocialRegistration: function (registrar) {
            const self = this;

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
            const self = this,
                Terms = self.getElm().getElement('[data-name="terms-view"]');

            let Form = Terms.getElement('form');

            return this.$getRegistrar(registrar).then(function (result) {
                if (!Form) {
                    Form = new Element('form', {
                        method: 'POST'
                    });
                }

                Form.set('html', result);

                // mail registrar
                const MailRegistrar = Form.getElement(
                    '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/registrars/Email"]'
                );

                if (MailRegistrar) {
                    const Button = new Element('button', {
                        'class': 'quiqqer-fu-registrationSignUp-terms-mail btn btn-primary ',
                        html: QUILocale.get(lg, 'control.registration.sign.up.create.button')
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

                const Container = Form.getFirst();

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

            const formData = QUIFormUtils.getFormData(Form);

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

            const self = this;

            console.log('sendRegistration');

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (Data) {
                    const Section = self.$RegistrationSection;

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
                            const Ghost = new Element('div', {
                                html: Data.html
                            });

                            const Registration = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                            );

                            // we need no login?
                            const Login = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin"]'
                            );

                            if (Login) {
                                Login.destroy();
                            }

                            const StatusContainer = new Element('div', {
                                'class': 'quiqqer-fu-registrationSignUp-status'
                            });

                            if (Ghost.getElement('.content-message-error')) {
                                Section.set('html', '');
                                Ghost.getElement('.content-message-error').inject(StatusContainer);
                                StatusContainer.inject(Section);
                            } else if (Registration) {
                                StatusContainer.set('html', Registration.get('html'));
                                StatusContainer.inject(Section);
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
                                    const html = Section.getElement('.content-message-success').get('html').trim();

                                    if (html === '' && self.getAttribute('reloadOnSuccess')) {
                                        window.location.reload();
                                    }
                                }

                                const Redirect = Section.getElement('.quiqqer-frontendUsers-redirect');

                                if (Redirect && self.getAttribute('reloadOnSuccess')) {
                                    if (Redirect.get('data-reload')) {
                                        window.location.reload();
                                        return;
                                    }

                                    if (Redirect.get('data-instant')) {
                                        window.location = Redirect.get('data-url');
                                    }
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
                    'package': 'quiqqer/frontend-users',
                    registrar: registrar,
                    registration_id: registration_id,
                    data: JSON.encode(formData),
                    isSignUpRegistration: 1,
                    onError: function (err) {
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
            if (this.getAttribute('termsAccepted')) {
                return Promise.resolve();
            }

            const Terms = this.getElm().getElement('[data-name="terms-view"]');

            this.Loader.show();
            return this.$loadRegistrar(registrar).then((Control) => {
                const links = Terms.getElements('a');

                links.removeEvents('click');
                links.addEvent('click', function (event) {
                    let Target = event.target;

                    if (Target.nodeName !== 'A') {
                        Target = Target.getParent('a');
                    }

                    const href = Target.get('href');

                    if (href.indexOf('/') !== 0) {
                        return;
                    }

                    event.stop();

                    new QUISiteWindow({
                        closeButtonText: QUILocale.get(lg, 'btn.close'),
                        showTitle: true,
                        project: QUIQQER_PROJECT.name,
                        lang: QUIQQER_PROJECT.lang,
                        siteUrl: href
                    }).open();
                });

                this.$TermsView.setStyles({
                    display: null,
                    opacity: ''
                });

                return this.$animateRegistrationViewChange(
                    this.$RegistrationContainer,
                    this.$TermsContainer
                ).then(() => {
                    this.$TermsView.setStyle('display', '');
                    return Control;
                });
            }).then((Control) => {
                if (typeOf(Control) === 'element') {
                    // mail registration
                    this.Loader.hide();

                    return new Promise((resolve, reject) => {
                        const DeclineBtn = Terms.getElement('button[name="decline"]');
                        const SubmitBtn = Terms.getElement('.quiqqer-fu-registrationSignUp-terms-mail');

                        DeclineBtn.removeEvents('click');
                        SubmitBtn.removeEvents('click');

                        DeclineBtn.addEvent('click', reject);
                        SubmitBtn.addEvent('click', resolve);
                    });
                }

                // create the social login form
                const Form = Terms.getElement('form');
                const SocialForm = this.getElm().getElement('form[data-registrar="' + registrar + '"]');
                const hidden = SocialForm.getElements('[type="hidden"]');

                Form.set('method', SocialForm.get('method'));
                Form.set('data-registrar', SocialForm.get('data-registrar'));
                Form.set('data-registration_id', SocialForm.get('data-registration_id'));

                for (let i = 0, len = hidden.length; i < len; i++) {
                    hidden[i].clone().inject(Form);
                }

                this.Loader.hide();

                return new Promise((resolve) => {
                    Form.removeEvents('submit');
                    Form.addEvent('submit', (event) => {
                        event.stop();
                        this.$sendForm(Form).then(resolve);
                    });
                });
            });
        },

        /**
         * hide the terms
         *
         * @return {Promise}
         */
        hideTerms: function () {
            if (!this.$TermsView || !this.$TermsContainer || this.$TermsContainer.getStyle('display') === 'none') {
                return Promise.resolve();
            }

            return this.$animateRegistrationViewChange(
                this.$TermsContainer,
                this.$RegistrationContainer
            ).then(() => {
                this.$TermsContainer.setStyle('display', 'none');
                this.$TermsView.setStyle('display', 'none');
            });
        },

        //endregion

        // region social

        $submitRegistrar: function (registrar) {
            const self = this;

            this.Loader.show();

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (html) {
                    const Section = self.$RegistrationSection;

                    moofx(Section).animate({
                        opacity: 0
                    }, {
                        duration: 250,
                        callback: function () {
                            const Ghost = new Element('div', {
                                html: html
                            });

                            const Registration = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                            );

                            // we need no login?
                            const Login = Ghost.getElement(
                                '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin"]'
                            );

                            if (Login) {
                                Login.destroy();
                            }

                            const StatusContainer = new Element('div', {
                                'class': 'quiqqer-fu-registrationSignUp-status'
                            });

                            if (Ghost.getElement('.content-message-error')) {
                                Section.set('html', '');
                                Ghost.getElement('.content-message-error').inject(StatusContainer);
                                StatusContainer.inject(Section);
                            } else if (Registration) {
                                StatusContainer.set('html', Registration.get('html'));
                                StatusContainer.inject(Section);
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
                                    const html = Section.getElement('.content-message-success').get('html').trim();

                                    if (html === '') {
                                        window.location.reload();
                                    }
                                }

                                const Redirect = Section.getElement('.quiqqer-frontendUsers-redirect');

                                if (Redirect && self.getAttribute('reloadOnSuccess')) {
                                    if (Redirect.get('data-reload')) {
                                        window.location.reload();
                                        return;
                                    }

                                    if (Redirect.get('data-instant')) {
                                        window.location = Redirect.get('data-url');
                                    }
                                }

                                const ErrorBox = Section.getElement('.content-message-error');

                                if (ErrorBox) {
                                    new Element('a', {
                                        'class': 'quiqqer-fu-registrationSignUp-back',
                                        href: '#',
                                        html: QUILocale.get(lg, 'controls.RegistrationSignUp.error.back'),
                                        events: {
                                            click: function (event) {
                                                event.stop();

                                                const Url = URI(window.location);

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
                    data: JSON.encode([]),
                    onError: function (err) {
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
            return new Promise((resolve) => {
                if (this.Loader) {
                    this.Loader.show();
                    resolve(this.Loader);
                    return;
                }

                require(['qui/controls/loader/Loader'], (Loader) => {
                    this.Loader = new Loader({
                        type: 'fa-spinner',
                        styles: {
                            background: 'transparent'
                        }
                    }).inject(this.$RegistrationSection);

                    this.Loader.show();
                    resolve(this.Loader);
                });
            });
        },

        /**
         * Hides the content / text section
         *
         * @return {Promise}
         */
        hideTextSection: function () {
            const self = this;

            return new Promise(function (resolve) {
                moofx(self.$TextSection).animate({
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        moofx(self.$TextSection).animate({
                            height: 0,
                            padding: 0,
                            width: 0
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

        //region E-Mail

        /**
         * init mail registration
         */
        $initMail: function () {
            const EmailNext = this.getElm().getElement('[name="email-next"]'),
                PasswordNext = this.getElm().getElement('[name="create-account"]'),
                EmailField = this.getElm().getElement('[name="email"]'),
                PasswordField = this.getElm().getElement(
                    '.quiqqer-fu-registrationSignUp-email-passwordSection [name="password"]'
                );

            if (!EmailField) {
                this.Loader.hide();
                return;
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

            if (this.$Address && this.$Address.querySelector('form')) {
                this.$Address.querySelector('form').addEventListener('submit', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                });
            }

            // email validation
            const self = this;

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
            const CaptchaContainer = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-captcha-display'
            );

            if (!CaptchaContainer) {
                return;
            }

            const self = this;

            const CaptchaDisplay = CaptchaContainer.getElement('.quiqqer-captcha-display'),
                Captcha = QUI.Controls.getById(CaptchaDisplay.get('data-quiid'));

            const onCaptchaLoad = function () {
                const CaptchaResponseInput = self.getElm().getElement('input[name="captchaResponse"]'),
                    CaptchaDisplay = CaptchaContainer.getElement('.quiqqer-captcha-display'),
                    Captcha = QUI.Controls.getById(CaptchaDisplay.get('data-quiid'));

                Captcha.getCaptchaControl().then(function (CaptchaControl) {
                    CaptchaControl.addEvents({
                        onSuccess: function (response) {
                            self.$captchaResponse = response;
                            CaptchaResponseInput.value = response;
                        },
                        onExpired: function () {
                            self.$captchaResponse = false;
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

            const MailSection = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-mailSection'),
                EmailNext = this.getElm().getElement('[name="email-next"]');

            const self = this,
                Elm = this.getElm(),
                MailInput = this.getElm().getElement('[name="email"]');

            if (MailInput.value === '') {
                self.fireEvent('error', [self, 'Mail empty']);
                return;
            }

            MailInput.set('disabled', true);
            EmailNext.set('disabled', true);

            Registration.isEmailBlacklisted(MailInput.value).then((isBlacklisted) => {
                if (isBlacklisted) {
                    QUI.getMessageHandler((MH) => {
                        MH.addAttention(QUILocale.get(lg, 'exception.registrars.email.email_blacklisted'), MailInput);
                    });
                    return false;
                }

                return this.emailValidation(MailInput);
            }).then(function (isValid) {
                if (!isValid) {
                    return Promise.reject('isInValid');
                }

                MailSection.setStyle('position', 'relative');

                MailInput.set('disabled', false);
                EmailNext.set('disabled', false);

                moofx(MailSection).animate({
                    left: -50,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        MailSection.setStyle('display', 'none');

                        const FullnameSection = Elm.getElement('.quiqqer-fu-registrationSignUp-email-fullnameSection'),
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

                self.fireEvent('error', [self, err]);
            });
        },

        /**
         * Shows password input section
         */
        $showPasswordSection: function () {
            const Elm = this.getElm(),
                Section = Elm.getElement('.quiqqer-fu-registrationSignUp-email-passwordSection');

            Section.setStyle('opacity', 0);
            Section.setStyle('display', 'inline');
            Section.setStyle('left', 50);
            Section.setStyle('top', 0);

            moofx(Section).animate({
                left: 0,
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

            const self = this,
                Elm = this.getElm(),
                Section = Elm.getElement('.quiqqer-fu-registrationSignUp-email-fullnameSection'),
                NextBtn = Section.getElement('[name="fullname-next"]'),
                FirstnameInput = Section.getElement('[name="firstname"]'),
                LastnameInput = Section.getElement('[name="lastname"]');

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

            const next = function () {
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
                    left: -50,
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
                left: 0,
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
            const PasswordSection = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-passwordSection'
            );

            const PasswordField = PasswordSection.getElement('[type="password"]');

            if (PasswordField.value === '') {
                event.stop();
                PasswordField.focus();
                return;
            }

            const self = this;

            moofx(PasswordSection).animate({
                left: -50,
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

            const CaptchaContainer = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-captcha-display'
            );

            if (!CaptchaContainer) {
                return Promise.resolve();
            }

            if (this.$captchaResponse) {
                return Promise.resolve();
            }

            const self = this;
            const Display = this.getElm().getElement(
                '.quiqqer-fu-registrationSignUp-email-captcha-display'
            );

            return new Promise(function (resolve) {
                if (self.$captchaResponse) {
                    return resolve();
                }

                Display.setStyle('opacity', 0);
                Display.setStyle('display', 'inline-block');

                const checkCaptchaInterval = function () {
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
            const self = this;
            const Mail = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-mailSection');
            let Captcha = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-captcha-display');
            const Password = this.getElm().getElement('.quiqqer-fu-registrationSignUp-email-passwordSection');

            return new Promise(function (resolve) {
                self.$TextSection.setStyles({
                    height: null,
                    width: null
                });

                if (!Captcha) {
                    Captcha = new Element('div');
                }

                moofx([Captcha, Password]).animate({
                    left: -50,
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
                            left: 0,
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
            const PasswordInput = this.getElm().getElement('[name="password"]');

            if (PasswordInput) {
                if (PasswordInput.value === '') {
                    return;
                }

                if (typeof PasswordInput.checkValidity !== 'undefined' && PasswordInput.checkValidity() === false) {
                    return;
                }
            }

            this.showLoader().then(() => {
                return this.showAddress();
            }).then(() => {
                let childNodes = this.$RegistrationSection.getChildren();

                childNodes = childNodes.filter(function (Child) {
                    return !Child.hasClass('qui-loader');
                });

                childNodes.setStyle('display', 'none');

                return this.showLoader().then(() => {
                    const Form = this.getElm().getElement('form[name="quiqqer-fu-registrationSignUp-email"]'),
                        FormData = QUIFormUtils.getFormData(Form);

                    FormData.termsOfUseAccepted = true;

                    if (typeof Form.elements.captchaResponse !== 'undefined') {
                        FormData.captchaResponse = Form.elements.captchaResponse.value;
                    }

                    if (this.$Address && this.$Address.querySelector('form')) {
                        const addressForm = this.$Address.querySelector('form');
                        const addressData = QUIFormUtils.getFormData(addressForm);

                        Object.assign(FormData, addressData);
                    }

                    return Registration.register(
                        Form.get('data-registrar'),
                        FormData
                    );
                });
            }).catch((err) => {
                console.error(err);
                this.$resetMail();

                return new Promise((resolve) => {
                    const registrationMainNode = this.getElm().querySelectorAll(
                        '.quiqqer-fu-registrationSignUp-registration__registration, .quiqqer-fu-registrationSignUp-registration__inner'
                    );

                    Array.from(registrationMainNode).forEach((n) => {
                        n.style.opacity = 0;
                        n.style.display = '';
                    });

                    moofx(registrationMainNode).animate({
                        opacity: 1
                    }, {
                        callback: resolve
                    });
                });
            }).then(() => {
                this.Loader.hide();
            });
        },

        showAddress: function () {
            return new Promise((resolve, reject) => {
                if (!this.$Address || !this.$Address.querySelector('form')) {
                    return resolve();
                }

                require([
                    'qui/controls/windows/SimpleConfirmWindow'
                ], (SimpleConfirmWindow) => {
                    const addressParent = this.$Address.parentNode;

                    new SimpleConfirmWindow({
                        'class' : 'qui-window-simpleWindow--address',
                        maxWidth: 900,
                        maxHeight: 700,
                        autoclose: false,
                        buttonSubmit: {
                            'class': 'btn btn-primary',
                            'icon': 'fa fa-check',
                            'text': QUILocale.get(lg, 'control.registration.sign.up.password.next'),
                            'order': 2
                        },
                        events: {
                            onOpen: (instance) => {
                                instance.getContent().innerHTML = '';
                                instance.getContent().appendChild(this.$Address);
                                this.$Address.style.display = 'block';
                            },
                            onSubmit: (instance) => {
                                const windowForm = instance.getContent().querySelector('form');
                                const form = this.$Address.querySelector('form');

                                if (this.$hasValidityIssues(windowForm)) {
                                    return;
                                }

                                Array.from(windowForm.elements).forEach((sourceElement) => {
                                    if (!sourceElement.name || !form.elements[sourceElement.name]) {
                                        return;
                                    }

                                    const targetElement = form.elements[sourceElement.name];

                                    if (sourceElement.type === 'checkbox' || sourceElement.type === 'radio') {
                                        targetElement.checked = sourceElement.checked;
                                        return;
                                    }

                                    targetElement.value = sourceElement.value;
                                });

                                this.$Address.style.display = 'none';
                                addressParent.appendChild(this.$Address);
                                instance.close();
                                resolve();
                            },
                            onCancel: () => {
                                this.$Address.style.display = 'none';
                                addressParent.appendChild(this.$Address);
                                reject();
                            }
                        }
                    }).open();
                });
            });
        },

        $hasValidityIssues: function (Form) {
            if (!Form) {
                return false;
            }

            if (typeof Form.reportValidity === 'function') {
                const isValid = Form.reportValidity();

                if (isValid === false) {
                    const invalidField = Form.querySelector(':invalid');

                    if (invalidField && typeof invalidField.focus === 'function') {
                        invalidField.focus();
                    }

                    return true;
                }
            }

            if (typeof Form.checkValidity === 'function' && Form.checkValidity() === false) {
                const fields = Form.elements || [];

                for (let i = 0, len = fields.length; i < len; i++) {
                    if (typeof fields[i].checkValidity === 'function' && fields[i].checkValidity() === false) {
                        if (typeof fields[i].focus === 'function') {
                            fields[i].focus();
                        }

                        if (typeof fields[i].reportValidity === 'function') {
                            fields[i].reportValidity();
                        }

                        return true;
                    }
                }

                return true;
            }

            return false;
        },

        /**
         * Validate the email field
         *
         * @param {HTMLInputElement} Field
         * @return {Promise}
         */
        emailValidation: function (Field) {
            const value = Field.value;

            const checkPromises = [
                Registration.emailValidation(value)
            ];

            if (this.getAttribute('emailIsUsername')) {
                checkPromises.push(Registration.usernameValidation(value));
            }

            if (this.getAttribute('emailIsUsername') === false) {
                const wasDisabled = Field.disabled;

                Field.disabled = false;

                if (typeof Field.checkValidity !== 'undefined' && Field.checkValidity() === false) {
                    Field.disabled = wasDisabled;

                    return Promise.resolve(false);
                }

                Field.disabled = wasDisabled;
            }

            this.Loader.show();

            return Promise.all(checkPromises).then(function (result) {
                let isValid = true;

                for (let i = 0, len = result.length; i < len; i++) {
                    if (!result[i]) {
                        isValid = false;
                        break;
                    }
                }

                if (isValid) {
                    this.Loader.hide();
                    return true;
                }

                //this.$handleInputValidation(
                //    Field,
                //    isValid,
                //    QUILocale.get(lg, 'exception.registrars.email.email_already_exists')
                //);

                return this.$showLoginControl(value);
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
            const self = this;

            require(['package/quiqqer/tooltips/bin/html5tooltips'], function () {
                if (!Input.get('data-has-tooltip') && isValid) {
                    return;
                }

                let tipId = Input.get('data-has-tooltip');
                let Tip = null;

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

                Tip = new window.HTML5TooltipUIComponent();
                tipId = String.uniqueID();

                Tip.set({
                    target: Input,
                    maxWidth: "200px",
                    animateFunction: "scalein",
                    color: "#ef5753",
                    stickTo: "top",
                    contentText: errorMsg
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
