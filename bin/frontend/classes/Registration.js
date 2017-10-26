/**
 * @module package/quiqqer/frontend-users/bin/frontend/classes/Registration
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/frontend-users/bin/frontend/classes/Registration', [

    'Ajax'

], function (QUIAjax) {
    "use strict";

    return new Class({

        Type: 'package/quiqqer/frontend-users/bin/frontend/classes/Registration',

        /**
         * Validate QUIQQER username
         *
         * @param {String} username
         * @return {Promise} - returns true if username is valid; false otherwise
         */
        validateUsername: function (username) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('ajax_users_exists', resolve, {
                    username: username,
                    onError : reject
                });
            });
        }
    });
});