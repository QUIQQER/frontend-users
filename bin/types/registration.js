function jsInit() {
    "use strict";

    /**
     * Registers a JavaScript callback which is called when a site is deleted
     */
    require(['qui/QUI', 'Ajax'], function (QUI, QUIAjax) {
        QUIAjax.registerGlobalJavaScriptCallback(
            'quiqqerFrontendUsersUserRegisterCallback',
            function (Response, Data) {
                QUI.fireEvent('quiqqerFrontendUsersUserRegister', [
                    Data.userId,
                    Data.registrarHash,
                    Data.registrarType
                ]);
            }
        );
    });
}

if (typeof window.whenQuiLoaded !== 'undefined') {
    window.whenQuiLoaded.then(jsInit);
} else {
    document.addEvent('domready', jsInit);
}
