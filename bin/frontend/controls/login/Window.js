/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/login/Window
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/login/Window', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'package/quiqqer/frontend-users/bin/frontend/controls/login/Login',
    'Locale',
    'Ajax',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/login/Window.css'

], function (QUI, QUIPopup, Login, QUILocale, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIPopup,
        Type: 'package/quiqqer/frontend-users/bin/frontend/controls/login/Window',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight: 640,
            maxWidth: 500,
            buttons: false,
            logo: false,
            reload: true,

            ownRedirectOnLogin: false, // redirect function

            'show-registration-link': false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Login = null;

            this.addEvents({
                onOpen: this.$onOpen
            });

            if (this.getAttribute('logo') === false) {
                this.setAttribute('logo', window.QUIQQER_PROJECT.logo);
            }
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this,
                Content = this.getContent();

            this.Loader.show();

            this.getElm().addClass('quiqqer-frontendUsers-loginWindow');

            new Element('button', {
                'class': 'quiqqer-frontendUsers-loginWindow-close',
                html: '<span class="fa fa-close"></span>',
                events: {
                    click: this.cancel.bind(this)
                }
            }).inject(Content);

            if (this.getAttribute('message')) {
                new Element('div', {
                    'class': 'quiqqer-frontendUsers-loginWindow-message message-attention',
                    html: this.getAttribute('message')
                }).inject(Content);
            }

            if (this.getAttribute('logo')) {
                new Element('img', {
                    'class': 'quiqqer-frontendUsers-loginWindow-logo',
                    src: this.getAttribute('logo')
                }).inject(Content);
            }

            var Prom = window.Promise.resolve();

            if (this.getAttribute('show-registration-link')) {
                Prom = new window.Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_registrars_getRegistrationLink', resolve, {
                        'package': 'quiqqer/frontend-users'
                    });
                });
            }

            Prom.then(function (registrationLink) {
                self.$Login = new Login({
                    showLoader: false,
                    reload: self.getAttribute('reload'),
                    ownRedirectOnLogin: self.getAttribute('ownRedirectOnLogin'),
                    onSuccess: function () {
                        self.close();
                        self.fireEvent('success', [self]);
                    },
                    events: {
                        onAuthBegin: function () {
                            self.Loader.show();
                        },
                        onAuthNext: function () {
                            self.Loader.hide();
                        },
                        onLoad: function () {
                            self.Loader.hide();
                            self.fireEvent('load', [self]);
                        },
                        userLoginError: function () {
                            self.Loader.hide();
                        }
                    }
                }).inject(Content);

                if (self.getAttribute('show-registration-link')) {
                    new Element('a', {
                        'class': 'quiqqer-frontendUsers-loginWindow-registration-link',
                        href: registrationLink,
                        html: QUILocale.get('quiqqer/frontend-users', 'registration.control.registration.link')
                    }).inject(Content);
                }
            });
        }
    });
});
