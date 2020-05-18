/**
 * Button to re-send and activation link
 *
 * @module package/quiqqer/frontend-users/bin/frontend/controls/auth/ResendActivationLinkBtn
 * @author www.pcsg.de (Patrick Müller)
 *
 * @event onResendSuccess [this]
 * @event onResendFail [this]
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/auth/ResendActivationLinkBtn', [

    'qui/controls/buttons/Button',

    'Ajax',
    'Locale'

], function (QUIButton, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIButton,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/auth/ResendActivationLinkBtn',

        Binds: [
            '$onClick'
        ],

        options: {
            email    : false,   // User e-mail address
            text     : QUILocale.get(lg, 'controls.frontend.auth.resendactivationlinkbtn.resend_activation_mail'),
            textimage: 'fa fa-envelope'
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onClick: this.$onClick
            });
        },

        /**
         * event: onClick
         */
        $onClick: function () {
            this.disable();
            this.setAttribute('textimage', 'fa fa-spinner fa-spin');

            this.$resendActivationMail().then(function (success) {
                if (!success) {
                    this.enable();
                    this.setAttribute('textimage', 'fa fa-envelope');

                    this.fireEvent('resendFail', [this]);
                    return;
                }

                this.setAttributes({
                    textimage: 'fa fa-check',
                    text     : QUILocale.get(lg, 'controls.frontend.auth.resendactivationlinkbtn.resend_completed')
                });

                this.fireEvent('resendSuccess', [this]);
            }.bind(this));
        },

        /**
         * Resend activation mail
         *
         * @return {Promise}
         */
        $resendActivationMail: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_frontend_auth_resendActivationMail', resolve, {
                    'package': 'quiqqer/frontend-users',
                    email    : self.getAttribute('email'),
                    onError  : reject
                })
            });
        }
    });
});