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
    'Ajax'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, QUIAjax) {
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
            showLoader: true,
            onSuccess : false,
            redirect  : true
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
            console.log('Not implemented yet');
        },

        /**
         * event: on import
         */
        $onInject: function () {
            var self = this;

            // @todo loader
            if (this.getAttribute('showLoader')) {
                this.Loader.show();
            }

            QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_login_getControl', function (result) {
                var Ghost = new Element('div', {
                    html: result
                });

                self.getElm().set(
                    'html',
                    Ghost.getElement('.quiqqer-fu-login').get('html')
                );

                self.Loader.inject(self.$Elm);

                Ghost.getElements('style').inject(self.getElm());

                QUI.parse(self.getElm()).then(function () {
                    var Login = self.getElm().getElement('.quiqqer-fu-login-container');

                    Login.setStyle('opacity', 0);
                    Login.setStyle('display', null);

                    self.getElm()
                        .getElement('form[name="quiqqer-fu-login-email"]')
                        .addEvent('submit', function (event) {
                            event.stop();
                            self.authByEmail();
                        });


                    self.getElm()
                        .getElements('.quiqqer-fu-login-social-entry')
                        .addEvent('click', self.$auth);


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
            }, {
                'package': 'quiqqer/frontend-users'
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

                    if (typeof self.getAttribute('onSuccess') === 'function') {
                        self.getAttribute('onSuccess')(self);
                        return;
                    }

                    window.location.reload();
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

                window.location.reload();
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

            QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_login_getLoginRedirect', function (result) {
                if (result) {
                    window.location = result;
                }
            }, {
                'package': 'quiqqer/frontend-users'
            });
        }
    });
});
