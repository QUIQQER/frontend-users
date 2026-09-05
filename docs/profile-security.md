# Profile request protection

The browser profile uses `QUI\Security\CsrfToken` from QUIQQER Core 2.32 or later.
Profile saves and address creation, editing and deletion require a POST request
with the session token in the top-level `_csrf` form field. This protection is
enforced even when the optional `quiqqer/csrf` package is absent or inactive.

The supplied profile and address templates render the token as a hidden field.
The JavaScript controls also send it as a top-level AJAX parameter. Custom
templates must retain the hidden `_csrf` field and its `data-name="csrf-token"`
attribute. Custom browser clients must obtain the token from the authenticated
profile or address form and include it in their POST requests. Tokens must never
be put in URLs or shared caches. The profile site already disables page caching.

An email address change additionally requires a complete sign-in within the last
ten minutes. The Core `onUserLogin` event records this in the session, tied to the
user's UUID. Configured frontend MFA must be complete. Ordinary profile edits do
not require another sign-in, and viewing the profile never refreshes this time
window. Passwordless authenticators continue to use the normal Core login flow.
Activation auto-login does not establish recent authentication.

Existing sessions without a recent-login record must sign out and sign in again
before requesting an email change. The profile reports this requirement when a
request is rejected. Run the package setup after deployment to register the
login event and import the translations.

The confirmation link is sent to the proposed new email address. A separate
notification is sent to the current address, without the confirmation token.
The account's primary email and username retain their existing confirmation
semantics. Server-side Handler methods and profile control signatures are
unchanged; browser request validation is performed at the HTTP entry points.
