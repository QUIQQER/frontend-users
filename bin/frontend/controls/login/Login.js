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
            '$auth'
        ],

        options: {
            showLoader: true,
            onSuccess : false
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
            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * event: on import
         */
        $onImport: function () {

            console.log(111);

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
                    //
                    self.getElm()
                        .getElements('.quiqqer-fu-login-social-entry')
                        .addEvent('click', self.$auth);

                    // self.getElm()
                    //     .getElements('.quiqqer-fu-login-social-entry')
                    //     .addEvent('click', function() {
                    //         console.log(11);
                    //     });


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

                    onError: function (e) {
                        self.Loader.hide();
                        self.fireEvent('userLoginError', [self]);
                        QUI.fireEvent('onQuiqqerUserAuthLoginUserLoginError', [self]);

                        reject(e);
                    }
                });
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

            clicked = true; // we need that because of control click
            Target.getElement('.quiqqer-fu-login-social-entry-control').click();

            (function () {
                clicked = false;
            }).delay(200);
        }
    });
});
