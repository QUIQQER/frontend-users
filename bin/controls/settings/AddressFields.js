/**
 * Manage fields that are shown for address input on registration
 *
 * @module package/quiqqer/frontend-users/bin/controls/settings/AddressFields
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/frontend-users/bin/controls/settings/AddressFields', [

    'qui/QUI',
    'qui/controls/Control',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/frontend-users/bin/controls/settings/AddressFields.html',
    'css!package/quiqqer/frontend-users/bin/controls/settings/AddressFields.css'

], function (QUI, QUIControl, QUILocale, QUIAjax,
             Mustache, template) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/frontend-users/bin/controls/settings/AddressFields',

        Binds: [
            '$onImport',
            '$setSettings'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input    = null;
            this.$Settings = {};

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

            if (this.$Input.value.length) {
                this.$Settings = JSON.decode(this.$Input.value);
            }

            var lgPrefix = 'controls.settings.addressfields.template.';

            this.$Content = new Element('div', {
                'class': 'quiqqer-frontendusers-settings-addressfields',
                html   : Mustache.render(template, {
                    field          : QUILocale.get(lg, lgPrefix + 'field'),
                    fieldShow      : QUILocale.get(lg, lgPrefix + 'fieldShow'),
                    fieldRequired  : QUILocale.get(lg, lgPrefix + 'fieldRequired'),
                    labelSalutation: QUILocale.get(lg, lgPrefix + 'labelSalutation')
                })
            }).inject(this.$Input, 'after');
        },

        /**
         * Set settings to input
         */
        $setSettings: function () {
            var entryElms     = this.$Input.getParent().getElements(
                '.quiqqer-frontendusers-settings-addressfields-entry'
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
        $getAddressFields: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_frontend-users_ajax_settings_getAddressFields', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError  : reject
                });
            });
        }
    });
});