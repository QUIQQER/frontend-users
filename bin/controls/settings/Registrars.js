/**
 * Manage settings for all Registrars
 *
 * @module package/quiqqer/frontend-users/bin/controls/settings/Registrars
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/controls/settings/Registrars', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/frontend-users/bin/controls/settings/Registrars.Entry.html',
    'css!package/quiqqer/frontend-users/bin/controls/settings/Registrars.css'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, QUILocale, QUIAjax,
             Mustache, entryTemplate) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/controls/settings/Registrars',

        Binds: [
            '$onImport',
            '$setSettings'
        ],

        initialize: function (options) {
            this.parent(options);

            this.Loader = null;
            this.$Input = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.$Input      = this.getElm();
            this.$Input.type = 'hidden';

            var FormData = {};

            if (this.$Input.value.length) {
                FormData = JSON.decode(this.$Input.value);
            }

            this.$Content = new Element('div', {
                'class': 'quiqqer-frontendusers-settings-registrars'
            }).inject(this.$Input, 'after');

            this.Loader = new QUILoader();
            this.Loader.inject(this.$Content);

            this.Loader.show();

            this.$getRegistrars().then(function (registrars) {
                self.Loader.hide();

                var lgPrefix = 'controls.settings.registrars.template.';

                for (var i = 0, len = registrars.length; i < len; i++) {
                    var Registrar = registrars[i];
                    var type      = btoa(Registrar.type);

                    var EntryElm = new Element('div', {
                        'class'         : 'quiqqer-frontendusers-settings-registrars-entry',
                        'data-registrar': Registrar.type,
                        html            : Mustache.render(entryTemplate, {
                            title                     : Registrar.title,
                            description               : Registrar.description,
                            labelActivationMode       : QUILocale.get(lg, lgPrefix + 'labelActivationMode'),
                            activationModeOptionMail  : QUILocale.get(lg, lgPrefix + 'activationModeOptionMail'),
                            activationModeOptionAuto  : QUILocale.get(lg, lgPrefix + 'activationModeOptionAuto'),
                            activationModeOptionManual: QUILocale.get(lg, lgPrefix + 'activationModeOptionManual'),
                            labelActive               : QUILocale.get(lg, lgPrefix + 'labelActive')
                        })
                    }).inject(self.$Content);

                    if (type in FormData) {
                        var Form = EntryElm.getElement('form');
                        QUIFormUtils.setDataToForm(FormData[type], Form);
                    }

                    EntryElm.getElements(
                        '.quiqqer-frontendusers-settings-registrars-setting'
                    ).addEvent('change', self.$setSettings);
                }
            });
        },

        /**
         * Set settings to input
         */
        $setSettings: function () {
            var entryElms     = this.$Input.getParent().getElements(
                '.quiqqer-frontendusers-settings-registrars-entry'
            );
            var RegistrarData = {};

            for (var i = 0, len = entryElms.length; i < len; i++) {
                var EntryElm  = entryElms[i];
                var registrar = btoa(EntryElm.get('data-registrar'));

                RegistrarData[registrar] = QUIFormUtils.getFormData(
                    EntryElm.getElement('form')
                );
            }

            this.$Input.value = JSON.encode(RegistrarData);
        },

        /**
         * Get list of all registrars
         *
         * @return {Promise}
         */
        $getRegistrars: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_settings_getRegistrars', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError  : reject
                });
            });
        }
    });
});