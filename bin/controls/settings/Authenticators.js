/**
 * Manage settings for all Authenticators
 *
 * @module package/quiqqer/frontend-users/bin/controls/settings/Authenticators
 */
define('package/quiqqer/frontend-users/bin/controls/settings/Authenticators', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/utils/Form',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/frontend-users/bin/controls/settings/Authenticators.html',
    'css!package/quiqqer/frontend-users/bin/controls/settings/Authenticators.css'

], function (QUI, QUIControl, QUILoader, QUIFormUtils, QUILocale, QUIAjax,
             Mustache, template) {
    "use strict";

    var lg = 'quiqqer/frontend-users';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/frontend-users/bin/controls/settings/Authenticators',

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

            this.$Input = this.getElm();
            this.$Input.type = 'hidden';

            var SettingData = {};

            if (this.$Input.value.length) {
                SettingData = JSON.decode(this.$Input.value);
            }

            this.$Content = new Element('div', {
                'class': 'quiqqer-frontendusers-settings-authenticators field-container-field'
            }).inject(this.$Input, 'after');

            this.Loader = new QUILoader();
            this.Loader.inject(this.$Content);

            this.Loader.show();

            this.$getAuthenticators().then(function (authenticators) {
                self.Loader.hide();

                for (var i = 0, len = authenticators.length; i < len; i++) {
                    var classHash = btoa(authenticators[i].class);

                    if (classHash in SettingData && SettingData[classHash]) {
                        authenticators[i].checked = 'checked';
                    } else {
                        authenticators[i].checked = '';
                    }
                }

                self.$Content.set('html', Mustache.render(template, {
                    authenticators: authenticators
                }));

                // register events
                self.$Content.getElements('.quiqqer-frontendusers-settings-authenticators-entry input').addEvent(
                    'change', self.$setSettings
                );
            });
        },

        /**
         * Set settings to input
         */
        $setSettings: function () {
            var entryElms = this.$Input.getParent().getElements(
                '.quiqqer-frontendusers-settings-authenticators-entry input'
            );

            var AuthenticatorDate = {};

            for (var i = 0, len = entryElms.length; i < len; i++) {
                var Checkbox = entryElms[i];
                var registrar = btoa(Checkbox.value);

                AuthenticatorDate[registrar] = Checkbox.checked;
            }

            this.$Input.value = JSON.encode(AuthenticatorDate);
        },

        /**
         * Get list of all authenticators
         *
         * @return {Promise}
         */
        $getAuthenticators: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_frontend-users_ajax_settings_getAuthenticators', resolve, {
                    'package': 'quiqqer/frontend-users',
                    onError: reject
                });
            });
        }
    });
});