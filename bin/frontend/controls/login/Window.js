/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/login/Window
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/login/Window', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'package/quiqqer/frontend-users/bin/frontend/controls/login/Login',

    'css!package/quiqqer/frontend-users/bin/frontend/controls/login/Window.css'

], function (QUI, QUIPopup, Login) {
    "use strict";

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/login/Window',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight: 640,
            maxWidth : 500,
            buttons  : false,
            logo     : false
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
            var self    = this,
                Content = this.getContent();

            this.Loader.show();

            this.getElm().addClass('quiqqer-frontendUsers-loginWindow');

            new Element('button', {
                'class': 'quiqqer-frontendUsers-loginWindow-close',
                html   : '<span class="fa fa-close"></span>',
                events : {
                    click: this.close.bind(this)
                }
            }).inject(Content);

            if (this.getAttribute('message')) {
                new Element('div', {
                    'class': 'quiqqer-frontendUsers-loginWindow-message message-attention',
                    html   : this.getAttribute('message')
                }).inject(Content);
            }

            if (this.getAttribute('logo')) {
                new Element('img', {
                    'class': 'quiqqer-frontendUsers-loginWindow-logo',
                    src    : this.getAttribute('logo')
                }).inject(Content);
            }

            this.$Login = new Login({
                showLoader: false,
                onSuccess : function () {
                    self.close();
                    self.fireEvent('success', [self]);
                },
                events    : {
                    onAuthBegin: function () {
                        self.Loader.show();
                    },
                    onAuthNext : function () {
                        self.Loader.hide();
                    },

                    onLoad: function () {
                        self.Loader.hide();
                    },

                    userLoginError: function () {
                        self.Loader.hide();
                    }
                },
                styles    : {
                    height: 'calc(100% - 80px)'
                }
            }).inject(Content);
        }
    });
});