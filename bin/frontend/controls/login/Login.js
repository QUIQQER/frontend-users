/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/login/Login
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 * @event onLoadNoAnimation [self]
 * @event onAuthBegin [self]
 * @event onAuthNext [self]
 * @event onSuccess [self]
 * @event onUserLoginError [error, self]
 *
 * @event onQuiqqerUserAuthLoginLoad [self]
 * @event onQuiqqerUserAuthLoginUserLoginError [error, self]
 * @event onQuiqqerUserAuthLoginAuthBegin [self]
 * @event onQuiqqerUserAuthLoginSuccess [self]
 * @event onQuiqqerUserAuthNext [self]
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/login/Login', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',

    'package/quiqqer/frontend-users/bin/frontend/controls/auth/ResendActivationLinkBtn',

    'URI',
    'Ajax',
    'Locale'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, ResendActivationLinkBtn, URI, QUIAjax, QUILocale) {
    'use strict';

    var lg = 'quiqqer/frontend-users';
    var clicked = false;

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/login/Login',

        Binds: [
            'onImport',
            'onInject',
            '$auth',
            '$authBySocial',
            '$onUserLoginError',
            '$parseQuiControls'
        ],

        options: {
            showLoader: true,
            onSuccess: false,
            redirect: true,
            header: true,
            authenticators: [],  // fixed list of authenticators shown
            mail: true,
            emailAddress: '',
            passwordReset: true,
            reload: true,
            ownRedirectOnLogin: false, // function
            submitauth: false   // md5sum of classname of authenticator that is *immediately* submitted upon control load
        },

        initialize: function (options) {
            this.parent(options);

            this.$Elm = null;
            this.Loader = new QUILoader();

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject,
                onUserLoginError: this.$onUserLoginError
            });
        },

        /**
         * Create the DOMNode Element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-frontendUsers-login');
            this.$Elm.set({
                'data-quiid': this.getId(),
                'data-qui': this.getType()
            });

            this.Loader.inject(this.$Elm);

            if (this.getAttribute('styles')) {
                this.$Elm.setStyles(this.getAttribute('styles'));
            }

            return this.$Elm;
        },

        /**
         * event: on import
         */
        $onImport: function () {
            this.Loader.inject(this.$Elm);

            if (this.getAttribute('showLoader')) {
                this.Loader.show();
            }

            const Elm = this.getElm(),
                HeaderElm = Elm.querySelector('[data-name="header"]');

            if (this.getAttribute('header') === false && HeaderElm) {
                HeaderElm.destroy();
            }

            this.$parseQuiControls();
        },

        /**
         * event: on import
         */
        $onInject: function () {
            var self = this;

            if (this.getAttribute('showLoader')) {
                this.Loader.show();
            }

            QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_login_getControl', function (result) {
                const Ghost = new Element('div', {
                    html: result
                });

                const HeaderElm = Ghost.querySelector('[data-name="header"]');

                if (self.getAttribute('header') === false && HeaderElm) {
                    HeaderElm.destroy();
                }

                self.getElm().set(
                    'html',
                    Ghost.querySelector('.quiqqer-fu-login').get('html')
                );

                self.Loader.inject(self.$Elm);

                Ghost.querySelector('style').inject(self.getElm());

                self.$parseQuiControls();
            }, {
                'package': 'quiqqer/frontend-users',
                authenticators: JSON.encode(this.getAttribute('authenticators')),
                mail: this.getAttribute('mail') ? 1 : 0,
                passwordReset: this.getAttribute('passwordReset') ? 1 : 0
            });
        },

        /**
         * parse qui controls for loading
         */
        $parseQuiControls: function () {
            var self = this;

            QUI.parse(this.getElm()).then(function () {
                var Login = self.getElm().querySelector('[data-name="login-container"]');

                // already logged in
                if (!Login) {
                    self.Loader.hide();
                    self.fireEvent('load', [self]);

                    QUI.fireEvent('quiqqerUserAuthLoginLoad', [self]);
                    return;
                }

                Login.setStyle('opacity', 0);
                Login.setStyle('display', null);
                Login.setStyle('positino', 'relative');

                self.getElm().getElements('form[name="quiqqer-fu-login-email"]').addEvent('submit', function (event) {
                    event.stop();
                    self.authByEmail().catch(function (e) {
                        // nothing
                    });
                });

                var emailAddress = self.getAttribute('emailAddress');

                if (emailAddress) {
                    self.getElm().querySelector('form[name="quiqqer-fu-login-email"]').querySelector(
                        'input[name="username"]').value = emailAddress;

                    self.getElm().querySelector('form[name="quiqqer-fu-login-email"]').querySelector(
                        'input[name="password"]').focus();
                }

                self.getElm().getElements('[data-name="social-login-form"]').addEvent('click', self.$auth);

                self.getElm().getElements(
                    '[data-name="forgot-password-link"] a'
                ).addEvent('click', function (event) {
                    event.stop();
                    self.openForgottenPassword();
                });

                self.getElm().getElements(
                    '.quiqqer-fu-login-forget-password-reset [name="cancel"]'
                ).addEvent('click', function (event) {
                    event.stop();
                    self.closeForgottenPassword();
                });

                self.getElm().getElements(
                    '.quiqqer-fu-login-forget-password-reset [type="submit"]'
                ).addEvent('click', function (event) {
                    event.stop();
                    self.sendForgottenPassword();
                });

                // submit events
                var container = self.getElm().getElements('[data-name="social-login-controlContainer"]');
                var i, len, Control, ControlDom;

                for (i = 0, len = container.length; i < len; i++) {
                    ControlDom = container[i].getFirst();
                    Control = QUI.Controls.getById(ControlDom.get('data-quiid'));

                    //Control.addEvent('');
                }

                self.getElm().getElements('[data-name="social-login-form"]').addEvents({
                    submit: self.$authBySocial
                });

                moofx(Login).animate({
                    opacity: 1
                }, {
                    callback: function () {
                        self.Loader.hide();
                        self.fireEvent('load', [self]);

                        QUI.fireEvent('quiqqerUserAuthLoginLoad', [self]);
                    }
                });

                self.fireEvent('loadNoAnimation', [self]);

                // Immediately submit an authentication form
                // This is used for asynchronous authentication requests via third-party site
                var submitauth = false;

                if (self.getAttribute('submitauth')) {
                    submitauth = self.getAttribute('submitauth');
                } else {
                    var Url = URI(window.location),
                        query = Url.query(true);

                    if ('submitauth' in query) {
                        submitauth = query.submitauth;
                    }
                }

                if (submitauth) {
                    var Form = self.getElm().querySelector('form[data-authenticator-hash="' +
                        self.getAttribute('submitauth') + '"]');

                    if (Form) {
                        self.$authBySocial(Form);
                    }
                }
            });
        },

        /**
         * Authentication via email
         */
        authByEmail: function () {
            var self = this,
                Form = this.getElm().querySelector('form[name="quiqqer-fu-login-email"]');

            if (this.getAttribute('showLoader')) {
                this.Loader.show();
            }

            this.fireEvent('authBegin', [this]);
            QUI.fireEvent('quiqqerUserAuthLoginAuthBegin', [this]);

            var FormData = QUIFormUtils.getFormData(Form);

            return new Promise(function (resolve, reject) {
                QUIAjax.post('ajax_users_login', function (result) {
                    window.QUIQQER_USER = result.user;

                    self.fireEvent('success', [self]);
                    QUI.fireEvent('quiqqerUserAuthLoginSuccess', [self, 'QUI\\Users\\Auth\\QUIQQER']);
                    resolve(self);

                    self.$onSuccess();

                    if (self.getAttribute('ownRedirectOnLogin')) {
                        return;
                    }

                    if (typeof self.getAttribute('onSuccess') === 'function') {
                        self.getAttribute('onSuccess')(self);
                        return;
                    }

                    if (self.getAttribute('reload')) {
                        window.location.reload();
                    }
                }, {
                    showLogin: false,
                    authenticator: 'QUI\\Users\\Auth\\QUIQQER',
                    globalauth: 1,
                    params: JSON.encode(FormData),
                    onError: function (e) {
                        self.Loader.hide();
                        self.fireEvent('userLoginError', [
                            self,
                            e
                        ]);
                        QUI.fireEvent('onQuiqqerUserAuthLoginUserLoginError', [
                            self,
                            e
                        ]);

                        reject(e);
                    }
                });
            });
        },

        /**
         * Social authentication
         *
         * @param Form
         */
        $authBySocial: function (Form) {
            console.log('$authBySocial', Form);

            var self = this;

            this.fireEvent('authBegin', [this]);
            QUI.fireEvent('quiqqerUserAuthLoginAuthBegin', [this]);

            this.$showSocialLoader(Form);

            QUIAjax.post('ajax_users_login', function (result) {
                window.QUIQQER_USER = result.user;

                self.fireEvent('success', [self]);
                QUI.fireEvent('quiqqerUserAuthLoginSuccess', [self, Form.get('data-authenticator')]);

                self.$hideSocialLoader(Form);
                self.$onSuccess();

                if (
                    typeof self.getAttribute('onSuccess') === 'function' &&
                    self.getAttribute('onSuccess')(self)
                ) {
                    return;
                }

                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_login_getLoginRedirect', function (redirect) {
                    if (!self.getAttribute('reload')) {
                        return;
                    }

                    if (self.getAttribute('ownRedirectOnLogin')) {
                        self.getAttribute('ownRedirectOnLogin')();
                        return;
                    }

                    if (redirect) {
                        window.location = redirect;
                        return;
                    }

                    window.location.reload();
                }, {
                    'package': 'quiqqer/frontend-users'
                });
            }, {
                showLogin: false,
                authenticator: Form.get('data-authenticator'),
                globalauth: 1,
                params: JSON.encode(
                    QUIFormUtils.getFormData(Form)
                ),
                onError: function (e) {
                    self.$hideSocialLoader(Form);
                    self.Loader.hide();
                    self.fireEvent('userLoginError', [
                        self,
                        e
                    ]);
                    QUI.fireEvent('onQuiqqerUserAuthLoginUserLoginError', [
                        self,
                        e
                    ]);
                }
            });
        },

        /**
         * Show a loader for the social login
         *
         * @param Form
         */
        $showSocialLoader: function (Form) {
            var Icon = Form.querySelector('[data-name="social-login-entry-icon"]');
            var Loader = Form.querySelector('[data-name="social-login-entry-loader"]');

            if (!Icon || !Loader) {
                return;
            }

            Loader.setStyle('opacity', 0);
            Loader.setStyle('display', 'inline-block');

            moofx(Icon).animate({
                opacity: 0
            }, {
                duration: 250,
                callback: function () {
                    Icon.setStyle('display', 'none');
                }
            });

            moofx(Loader).animate({
                opacity: 1
            }, {
                duration: 250
            });
        },

        /**
         * hide the loader at the social login
         *
         * @param Form
         */
        $hideSocialLoader: function (Form) {
            var Icon = Form.querySelector('[data-name="social-login-entry-icon"]');
            var Loader = Form.querySelector('[data-name="social-login-entry-loader"]');

            if (!Icon || !Loader) {
                return;
            }

            Icon.setStyle('opacity', 0);
            Icon.setStyle('display', 'inline-block');

            moofx(Icon).animate({
                opacity: 1
            }, {
                duration: 250
            });

            moofx(Loader).animate({
                opacity: 1
            }, {
                duration: 250,
                callback: function () {
                    Loader.setStyle('display', 'none');
                }
            });
        },

        /**
         * social authentication
         */
        $auth: function (event) {
            if (clicked) {
                return;
            }

            var Target = event.target;

            if (Target.getAttribute('data-name') !== 'social-login-form') {
                Target = Target.getAttribute('[data-name="social-login-form"]');
            }

            var Container = Target.getElement('[data-name="social-login-controlContainer"]');
            var ControlDom = Container.getFirst();
            var Control = QUI.Controls.getById(ControlDom.get('data-quiid'));

            Control.click();
            clicked = true; // we need that because of control click

            (function () {
                clicked = false;
            }).delay(200);
        },

        /**
         * on success
         */
        $onSuccess: function () {
            if (!this.getAttribute('redirect')) {
                return;
            }

            var self = this;

            QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_login_getLoginRedirect', function (result) {
                if (!self.getAttribute('reload')) {
                    return;
                }

                if (self.getAttribute('ownRedirectOnLogin')) {
                    self.getAttribute('ownRedirectOnLogin')();
                    return;
                }

                if (result) {
                    window.location = result;
                }
            }, {
                'package': 'quiqqer/frontend-users'
            });
        },

        //region password reset

        /**
         * opens the password forgotten sheet
         */
        openForgottenPassword: function () {
            var Reset = this.getElm().querySelector('[data-name="password-reset"]');

            if (!Reset) {
                return;
            }

            // set these styles to make the animation work correctly when using _basic files
            Reset.style.position = 'absolute';
            Reset.style.left = 0;
            Reset.style.top = 0;
            Reset.style.width = '100%';
            Reset.style.zIndex = 1;
            Reset.style.opacity = 0;

            const Login = this.getElm().querySelector('[data-name="login-container"]');
            Login.style.height = Login.offsetHeight + 'px';

            const LoginInner = this.getElm().querySelector('[data-name="login-container-inner"]');

            // set these styles to make the animation work correctly when using _basic files
            Reset.setStyle('opacity', 0);
            Reset.setStyle('left', -50);
            Reset.setStyle('display', 'block');

            if (LoginInner) {
                moofx(LoginInner).animate({
                    opacity: 0
                });
            }

            moofx(Reset).animate({
                left: 0,
                opacity: 1
            }, {
                callback: function () {

                }
            });

            if (LoginInner) {
                moofx(Login).animate({
                    height: Reset.offsetHeight
                });
            }
        },

        /**
         * Event: onUserLoginError
         *
         * @param {Object} error
         */
        $onUserLoginError: function (Control, error) {
            if (!this.$Elm) {
                console.error(error);
                return;
            }
            var ActivationInfoBox = this.$Elm.querySelector('[data-name="activation-info"]');

            ActivationInfoBox.set('html', '');

            var msgBoxStyle = 'content-message-attention';

            switch (error.getCode()) {
                case 429:
                    msgBoxStyle = 'content-message-error';
                    break;
            }

            var MsgElm = new Element('div', {
                'class': 'quiqqer-fu-login-activation-info-message ' + msgBoxStyle,
                html: error.getMessage()
            });

            var showResendError = function () {
                MsgElm.set('html', QUILocale.get(lg, 'controls.frontend.Login.resend_activation_mail_error'));
                MsgElm.removeClass('content-message-attention');
                MsgElm.addClass('content-message-error');
            };

            switch (error.getAttribute('reason')) {
                case 'auth_error_user_deleted':
                    MsgElm.set('html', QUILocale.get(lg, 'controls.frontend.Login.user_deleted_error'));
                    MsgElm.removeClass('content-message-attention');
                    MsgElm.addClass('content-message-error');
                    break;

                case 'auth_error_user_not_active':
                    var email = this.getAttribute('emailAddress');

                    if (!email) {
                        var Form = this.getElm().querySelector('form[name="quiqqer-fu-login-email"]');

                        if (!Form) {
                            showResendError();
                            return;
                        }

                        email = Form.querySelector('input[name="username"]').value.trim();
                    }

                    new ResendActivationLinkBtn({
                        email: email,
                        events: {
                            onResendSuccess: function (Btn) {
                                Btn.disable();
                            },
                            onResendFail: function (Btn) {
                                showResendError();
                                Btn.enable();
                            }
                        }
                    }).inject(ActivationInfoBox);

                    MsgElm.set('html', QUILocale.get(lg, 'controls.frontend.Login.resend_activation_mail_info'));
                    break;
            }

            MsgElm.inject(ActivationInfoBox);
        },

        /**
         * close the password reset
         */
        closeForgottenPassword: function () {
            var Reset = this.getElm().querySelector('[data-name="password-reset"]');

            if (!Reset) {
                return;
            }

            const Login = this.getElm().querySelector('[data-name="login-container"]');
            Login.style.height = Login.offsetHeight + 'px';

            const LoginInner = this.getElm().querySelector('[data-name="login-container-inner"]');

            if (LoginInner) {
                moofx(LoginInner).animate({
                    opacity: 1
                });
            }

            moofx(Reset).animate({
                left: -50,
                opacity: 0
            }, {
                callback: function () {
                    Reset.setStyle('display', 'none');
                }
            });

            if (LoginInner) {
                moofx(Login).animate({
                    height: LoginInner.offsetHeight
                }, {
                    callback: function () {
                        Login.style.height = null;
                    }
                });
            }
        },

        /**
         * send password reset call
         */
        sendForgottenPassword: function () {
            var self = this,
                Elm = this.getElm(),
                PasswordReset = Elm.querySelector('[data-name="password-reset"]'),
                SubmitBtn = PasswordReset.querySelector('[type="submit"]'),
                EmailInput = PasswordReset.querySelector('[name="email"]'),
                Section = PasswordReset.querySelector('[data-name="password-reset-inner"]');

            if (EmailInput.value === '') {
                return Promise.resolve();
            }

            EmailInput.disabled = true;
            SubmitBtn.disabled = true;

            var showHideMessage = function (Message) {
                moofx(Section).animate({
                    opacity: 0
                }, {
                    callback: function () {
                        Section.setStyle('display', 'none');
                    }
                });

                moofx(Message).animate({
                    opacity: 1,
                    top: 0
                }, {
                    duration: 200,
                    callback: function () {
                        (function () {
                            moofx(Message).animate({
                                opacity: 0,
                                top: -20
                            }, {
                                duration: 200,
                                callback: function () {
                                    Message.destroy();

                                    EmailInput.value = '';
                                    EmailInput.setStyle('display', null);

                                    Section.setStyle('display', null);
                                    Section.setStyle('opacity', null);

                                    self.closeForgottenPassword();
                                }
                            });
                        }).delay(4000);
                    }
                });
            };

            this.$sendPasswordResetConfirmMail(EmailInput.value).then(function () {
                self.Loader.hide();

                var Message = new Element('div', {
                    html: QUILocale.get('quiqqer/system', 'controls.users.auth.quiqqerlogin.send_mail_success'),
                    'class': 'message-success',
                    styles: {
                        left: 0,
                        opacity: 0,
                        padding: 20,
                        position: 'absolute',
                        top: 0,
                        width: '100%',
                        zIndex: 1
                    }
                }).inject(self.getElm());

                showHideMessage(Message);

                EmailInput.disabled = false;
                SubmitBtn.disabled = false;
            }, function (e) {
                self.Loader.hide();

                var Message = new Element('div', {
                    html: QUILocale.get('quiqqer/system', 'controls.users.auth.quiqqerlogin.send_mail_error', {
                        error: e.getMessage()
                    }),
                    'class': 'message-error',
                    styles: {
                        left: 0,
                        opacity: 0,
                        padding: 20,
                        position: 'absolute',
                        top: 0,
                        width: '100%',
                        zIndex: 1
                    }
                }).inject(self.getElm());

                showHideMessage(Message);

                EmailInput.disabled = false;
                SubmitBtn.disabled = false;
            });
        },

        /**
         * Send e-mail to user to confirm password reset
         *
         * @param {String} email
         * @return {Promise}
         */
        $sendPasswordResetConfirmMail: function (email) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('ajax_users_authenticator_sendPasswordResetConfirmMail', resolve, {
                    email: email,
                    onError: reject,
                    showError: false
                });
            });
        }

        //endregion
    });
});
