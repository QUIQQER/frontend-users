window.whenQuiLoaded().then(function () {
    'use strict';

    require(['qui/QUI'], function (QUI) {
        /**
         * tracks the start of a deletion process from an user
         */
        function trackUserDeleteStart() {
            window.qTrack('event', 'user_deleted_start');
        }

        /**
         * tracks the success of a deletion from an user
         */
        function trackUserDelete() {
            window.qTrack('event', 'user_deleted');
        }


        // registration tracking
        QUI.addEvent('onQuiqqerFrontendUsersRegisterStart', function () {
            window.qTrack('event', 'user_register_start');
        });

        QUI.addEvent('onQuiqqerFrontendUsersRegisterSuccess', function () {
            window.qTrack('event', 'user_register_success');
            window.qTrack('event', 'sign_up');
        });


        // deletion tracking
        if (QUI.getAttribute('QUIQQER_FRONTEND_USERS_ACCOUNT_DELETE_START')) {
            trackUserDeleteStart();
        }

        QUI.addEvent('quiqqerFrontendUsersAccountDeleteStart', function () {
            trackUserDeleteStart();
        });

        if (QUI.getAttribute('QUIQQER_VERIFIER_SUCCESS')) {
            const verifier = QUI.getAttribute('QUIQQER_VERIFIER_SUCCESS');

            if (verifier === 'QUIFrontendUsersUserDeleteConfirmVerification') {
                trackUserDelete();
            }
        }

        QUI.addEvent('quiqqerVerifierSuccess', function (verifier) {
            if (verifier === 'QUIFrontendUsersUserDeleteConfirmVerification') {
                trackUserDelete();
            }
        });

        QUI.addEvent('quiqqerUserAuthLoginSuccess', function (Instance, authenticator) {
            if (typeof authenticator === 'undefined' || authenticator === '') {
                window.qTrack('event', 'user_register_success');
                window.qTrack('event', 'sign_up');
            } else {
                window.qTrack('event', 'user_register_success', {
                    method: authenticator
                });

                window.qTrack('event', 'sign_up', {
                    method: authenticator
                });
            }
        });
    });
});