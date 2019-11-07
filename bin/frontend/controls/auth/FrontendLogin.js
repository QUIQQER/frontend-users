/**
 * Frontend Login
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onLogin [this] - fires if the user successfully authenticates
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'package/quiqqer/frontend-users/bin/frontend/controls/auth/ResendActivationLinkBtn',

    'utils/Controls',

    'Ajax',
    'Locale',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin.css'

], function (QUIControl, QUILoader, ResendActivationLinkBtn, QUIControlUtils, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/auth/FrontendLogin',

        Binds: [
            '$onImport',
            '$onLogin',
            '$checkForUnverifiedActivationVerification'
        ],

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();
            this.$Elm   = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self  = this;
            this.$Elm = this.getElm();

            var LoginControlElm = this.$Elm.getElement('div[data-qui="controls/users/Login"]');

            if (!LoginControlElm) {
                return;
            }

            var MsgElm = this.$Elm.getElement('.quiqqer-frontendUsers-frontendlogin-message');

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            QUIControlUtils.getControlByElement(LoginControlElm).then(function (LoginControl) {
                self.Loader.hide();

                LoginControl.setAttribute('onSuccess', self.$onLogin);

                LoginControl.addEvents({
                    onUserLoginError: function (error) {
                        switch (error.getAttribute('reason')) {
                            case 'user_not_active':
                                self.$checkForUnverifiedActivationVerification(
                                    error.getAttribute('userId')
                                );
                                break;

                            default:
                                MsgElm.set('html', '');

                                new Element('div', {
                                    'class': 'content-message-error',
                                    html   : error.getMessage()
                                }).inject(MsgElm);
                        }
                    }
                });
            });
        },

        /**
         * Checks if an unverfied Verification exists and displays appropriate information
         * and the option to resend activation link
         *
         * @param {Number} userId
         */
        $checkForUnverifiedActivationVerification: function (userId) {
            var self   = this;
            var MsgElm = this.$Elm.getElement('.quiqqer-frontendUsers-frontendlogin-message');

            MsgElm.set('html', '');

            this.Loader.show();

            this.$existsUnverifiedActivationVerification(userId).then(function (userUnverified) {
                self.Loader.hide();

                var InfoElm = new Element('div', {
                    'class': 'content-message-information',
                    html   : QUILocale.get(lg, 'controls.frontend.auth.frontendlogin.user_not_active')
                }).inject(MsgElm);

                if (!userUnverified) {
                    new Element('p', {
                        html: QUILocale.get(lg, 'controls.frontend.auth.frontendlogin.manual_activation')
                    }).inject(InfoElm);

                    return;
                }

                // show re-send activation mail option
                new Element('p', {
                    html: QUILocale.get(lg, 'controls.frontend.auth.frontendlogin.resend_activation_mail')
                }).inject(InfoElm);

                var ResendBtnElm = new Element('div', {
                    'class': 'quiqqer-frontendUsers-frontendlogin-resend-btn'
                }).inject(InfoElm);

                var ResendMsgElm = new Element('p', {
                    'class': 'quiqqer-frontendUsers-frontendlogin-resend-msg'
                }).inject(ResendBtnElm);

                new ResendActivationLinkBtn({
                    userId: userId,
                    events: {
                        onResendSuccess: function () {
                            ResendMsgElm.set(
                                'html',
                                QUILocale.get(lg, 'controls.frontend.auth.frontendlogin.resend_activation_mail_success')
                            );
                        },
                        onResendFail   : function () {
                            ResendMsgElm.set(
                                'html',
                                QUILocale.get(lg, 'controls.frontend.auth.frontendlogin.resend_activation_mail_fail')
                            );
                        }
                    }
                }).inject(ResendMsgElm, 'before');
            });
        },

        /**
         * Redirect on successful login
         */
        $onLogin: function () {
            var LoginElm    = this.$Elm.getElement('.quiqqer-frontendUsers-frontendlogin-login');
            var redirectUrl = LoginElm.get('data-redirect');

            this.fireEvent('login', [this]);

            if (redirectUrl) {
                window.location = redirectUrl;
            }
        },

        /**
         * Get link for unverified
         *
         * @param {Number} userId
         * @return {Promise}
         */
        $existsUnverifiedActivationVerification: function (userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_auth_existsUnverifiedActivation', resolve, {
                    'package': 'quiqqer/frontend-users',
                    userId   : userId,
                    onError  : reject
                });
            });
        }
    });
});