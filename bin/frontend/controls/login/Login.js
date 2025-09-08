/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/login/Login
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
    'qui/controls/windows/Popup',
    'qui/utils/Form',

    'package/quiqqer/frontend-users/bin/frontend/controls/auth/ResendActivationLinkBtn',

    'URI',
    'Ajax',
    'Locale',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/login/Login.css'

], function (QUI, QUIControl, QUILoader, QUIPopup, QUIFormUtils, ResendActivationLinkBtn, URI, QUIAjax, QUILocale) {
    'use strict';

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
            const container = this.getElm();
            this.Loader.inject(container);

            require(['controls/users/Login'], (Login) => {
                new Login({
                    events: {
                        onAuthBegin: () => {
                            container.style.overflow = 'hidden';
                            this.Loader.show();
                        },
                        onAuthNext: () => {
                            container.style.overflow = '';
                            this.Loader.hide();
                        },
                        onBuildAuthenticator: () => {
                            container.style.overflow = '';
                            this.Loader.hide();
                        }
                    }
                }).inject(container);
            });
        },

        /**
         * event: on import
         */
        $onInject: function () {
            this.$onImport();
        }
    });
});
