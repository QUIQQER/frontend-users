/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/login/Login
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
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
    'Ajax',
    'Locale'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, QUIAjax, QUILocale) {
    "use strict";

    var clicked = false;

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/login/Login',

        Binds: [
            'onImport',
            'onInject',
            '$auth',
            '$authBySocial'
        ],

        options: {
            showLoader        : true,
            onSuccess         : false,
            redirect          : true,
            header            : true,
            authenticators    : [],  // fixed list of authenticators shown
            mail              : true,
            passwordReset     : true,
            reload            : true,
            ownRedirectOnLogin: false // function
        },

        initialize: function (options) {
            this.parent(options);

            this.$Elm   = null;
            this.Loader = new QUILoader();

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject
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
                'data-qui'  : this.getType()
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

            var Elm = this.getElm();

            if (this.getAttribute('header') === false) {
                Elm.getElement('h2').destroy();
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
                var Ghost = new Element('div', {
                    html: result
                });

                if (self.getAttribute('header') === false) {
                    Ghost.getElement('h2').destroy();
                }

                self.getElm().set(
                    'html',
                    Ghost.getElement('.quiqqer-fu-login').get('html')
                );

                self.Loader.inject(self.$Elm);

                Ghost.getElements('style').inject(self.getElm());

                self.$parseQuiControls();
            }, {
                'package'     : 'quiqqer/frontend-users',
                authenticators: JSON.encode(this.getAttribute('authenticators')),
                mail          : this.getAttribute('mail') ? 1 : 0,
                passwordReset : this.getAttribute('passwordReset') ? 1 : 0
            });
        },

        /**
         * parse qui controls for loading
         */
        $parseQuiControls: function () {
            var self = this;

            QUI.parse(this.getElm()).then(function () {
                var Login = self.getElm().getElement('.quiqqer-fu-login-container');

                // already logged in
                if (!Login) {
                    self.Loader.hide();
                    self.fireEvent('load', [self]);

                    QUI.fireEvent('quiqqerUserAuthLoginLoad', [self]);
                    return;
                }

                Login.setStyle('opacity', 0);
                Login.setStyle('display', null);

                self.getElm()
                    .getElements('form[name="quiqqer-fu-login-email"]')
                    .addEvent('submit', function (event) {
                        event.stop();
                        self.authByEmail();
                    });


                self.getElm()
                    .getElements('.quiqqer-fu-login-social-entry')
                    .addEvent('click', self.$auth);

                self.getElm().getElements(
                    '.quiqqer-fu-login-forget-password-link a'
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
                var container = self.getElm().getElements('.quiqqer-fu-login-social-entry-control');
                var i, len, Control, ControlDom;

                for (i = 0, len = container.length; i < len; i++) {
                    ControlDom = container[i].getFirst();
                    Control    = QUI.Controls.getById(ControlDom.get('data-quiid'));

                    //Control.addEvent('');
                }

                self.getElm().getElements('form.quiqqer-fu-login-social-entry').addEvents({
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
            });
        },

        /**
         * Authentication via email
         */
        authByEmail: function () {
            var self = this,
                Form = this.getElm().getElement('form[name="quiqqer-fu-login-email"]');

            if (this.getAttribute('showLoader')) {
                this.Loader.show();
            }

            this.fireEvent('authBegin', [this]);
            QUI.fireEvent('quiqqerUserAuthLoginAuthBegin', [this]);

            return new Promise(function (resolve, reject) {
                QUIAjax.post('ajax_users_login', function (result) {
                    window.QUIQQER_USER = result.user;

                    self.fireEvent('success', [self]);
                    QUI.fireEvent('quiqqerUserAuthLoginSuccess', [self]);
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
                    showLogin    : false,
                    authenticator: 'QUI\\Users\\Auth\\QUIQQER',
                    globalauth   : 1,
                    params       : JSON.encode(
                        QUIFormUtils.getFormData(Form)
                    ),
                    onError      : function (e) {
                        self.Loader.hide();
                        self.fireEvent('userLoginError', [self, e]);
                        QUI.fireEvent('onQuiqqerUserAuthLoginUserLoginError', [self, e]);

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
            var self = this;

            this.fireEvent('authBegin', [this]);
            QUI.fireEvent('quiqqerUserAuthLoginAuthBegin', [this]);

            this.$showSocialLoader(Form);

            QUIAjax.post('ajax_users_login', function (result) {
                window.QUIQQER_USER = result.user;

                self.fireEvent('success', [self]);
                QUI.fireEvent('quiqqerUserAuthLoginSuccess', [self]);

                self.$hideSocialLoader(Form);
                self.$onSuccess();

                if (typeof self.getAttribute('onSuccess') === 'function') {
                    self.getAttribute('onSuccess')(self);
                    return;
                }

                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_login_getLoginRedirect', function (redirect) {
                    if (self.getAttribute('reload') === false) {
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
                showLogin    : false,
                authenticator: Form.get('data-authenticator'),
                globalauth   : 1,
                params       : JSON.encode(
                    QUIFormUtils.getFormData(Form)
                ),
                onError      : function (e) {
                    self.$hideSocialLoader(Form);
                    self.Loader.hide();
                    self.fireEvent('userLoginError', [self, e]);
                    QUI.fireEvent('onQuiqqerUserAuthLoginUserLoginError', [self, e]);
                }
            });
        },

        /**
         * Show a loader for the social login
         *
         * @param Form
         */
        $showSocialLoader: function (Form) {
            var Icon   = Form.getElement('.quiqqer-fu-login-social-entry-icon');
            var Loader = Form.getElement('.quiqqer-fu-login-social-entry-loader');

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
            var Icon   = Form.getElement('.quiqqer-fu-login-social-entry-icon');
            var Loader = Form.getElement('.quiqqer-fu-login-social-entry-loader');

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

            if (!Target.hasClass('quiqqer-fu-login-social-entry')) {
                Target = Target.getParent('.quiqqer-fu-login-social-entry');
            }

            var Container  = Target.getElement('.quiqqer-fu-login-social-entry-control');
            var ControlDom = Container.getFirst();
            var Control    = QUI.Controls.getById(ControlDom.get('data-quiid'));

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
            if (this.getAttribute('redirect') === false) {
                return;
            }

            var self = this;

            QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_login_getLoginRedirect', function (result) {
                if (self.getAttribute('reload') === false) {
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
            var Reset = this.getElm().getElement('.quiqqer-fu-login-forget-password-reset');

            if (!Reset) {
                return;
            }

            Reset.setStyle('opacity', 0);
            Reset.setStyle('left', -50);
            Reset.setStyle('display', 'block');

            moofx(Reset).animate({
                left   : 0,
                opacity: 1
            }, {
                callback: function () {

                }
            });
        },

        /**
         * close the password reset
         */
        closeForgottenPassword: function () {
            var Reset = this.getElm().getElement('.quiqqer-fu-login-forget-password-reset');

            if (!Reset) {
                return;
            }

            moofx(Reset).animate({
                left   : -50,
                opacity: 0
            }, {
                callback: function () {
                    Reset.setStyle('display', 'none');
                }
            });
        },

        /**
         * send password reset call
         */
        sendForgottenPassword: function () {
            var self       = this,
                Elm        = this.getElm(),
                SubmitBtn  = Elm.getElement('.quiqqer-fu-login-forget-password-reset [type="submit"]'),
                EmailInput = Elm.getElement('.quiqqer-fu-login-forget-password-reset [name="email"]'),
                Section    = Elm.getElement('.quiqqer-fu-login-forget-password-reset section');

            if (EmailInput.value === '') {
                return Promise.resolve();
            }

            EmailInput.disabled = true;
            SubmitBtn.disabled  = true;

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
                    top    : 0
                }, {
                    duration: 200,
                    callback: function () {
                        (function () {
                            moofx(Message).animate({
                                opacity: 0,
                                top    : -20
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
                    html   : QUILocale.get('quiqqer/system', 'controls.users.auth.quiqqerlogin.send_mail_success'),
                    'class': 'message-success',
                    styles : {
                        left    : 0,
                        opacity : 0,
                        padding : 20,
                        position: 'absolute',
                        top     : 0,
                        width   : '100%',
                        zIndex  : 1
                    }
                }).inject(self.getElm());

                showHideMessage(Message);

                EmailInput.disabled = false;
                SubmitBtn.disabled  = false;
            }, function (e) {
                self.Loader.hide();

                var Message = new Element('div', {
                    html   : QUILocale.get('quiqqer/system', 'controls.users.auth.quiqqerlogin.send_mail_error', {
                        error: e.getMessage()
                    }),
                    'class': 'message-error',
                    styles : {
                        left    : 0,
                        opacity : 0,
                        padding : 20,
                        position: 'absolute',
                        top     : 0,
                        width   : '100%',
                        zIndex  : 1
                    }
                }).inject(self.getElm());

                showHideMessage(Message);

                EmailInput.disabled = false;
                SubmitBtn.disabled  = false;
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
                    email    : email,
                    onError  : reject,
                    showError: false
                });
            });
        }

        //endregion
    });
});
