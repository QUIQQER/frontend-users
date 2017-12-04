/**
 * @module package/quiqqer/frontend-users/bin/frontend/controls/Registration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/controls/Registration', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',
    'Ajax'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/frontend/controls/Registration',

        Binds: [
            '$onImport',
            '$sendForm'
        ],

        initialize: function (options) {
            this.parent(options);

            this.Loader = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self  = this,
                Elm   = this.getElm(),
                forms = Elm.getElements('form.quiqqer-frontendUsers-controls-registration-registrar');

            this.Loader = new QUILoader();
            this.Loader.inject(Elm);

            forms.addEvent('submit', function (event) {
                event.stop();
                self.$sendForm(event.target).then(self.$onImport);
            });

            var RedirectElm = Elm.getElement(
                '.quiqqer-frontendUsers-autoRedirect'
            );

            if (RedirectElm) {
                var url = RedirectElm.get('data-url');

                (function () {
                    window.location = url;
                }.delay(10000));
            }
        },

        /**
         * Send the registrar form
         *
         * @param {HTMLFormElement} Form
         * @return {Promise}
         */
        $sendForm: function (Form) {
            this.Loader.show();

            var self     = this,
                formData = QUIFormUtils.getFormData(Form);

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_frontend_register', function (html) {
                    var Container = new Element('div', {
                        html: html
                    });

                    var Registration = Container.getElement(
                        '[data-qui="package/quiqqer/frontend-users/bin/frontend/controls/Registration"]'
                    );

                    self.getElm().set('html', Registration.get('html'));

                    QUI.parse(self.getElm()).then(resolve);
                }, {
                    'package'  : 'quiqqer/frontend-users',
                    'registrar': Form.get('data-registrar'),
                    'data'     : JSON.encode(formData),
                    onError    : reject
                });
            });
        },

        /**
         * Validate e-mail address
         *
         * @param {String} email
         * @return {Promise} - returns true if email address is valid; false otherwise
         */
        $validateUsername: function (username) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('ajax_email_validate', resolve, {
                    mail   : email,
                    onError: reject
                });
            });
        }
    });
});