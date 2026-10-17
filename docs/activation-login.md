# Activation and automatic login

An activation link activates the account and verifies its primary email address.
Possession of that link alone does not authorize a browser login.

With `registration.autoLoginOnActivation` enabled, automatic login requires the
original browser registration session. Registration creates a random 256-bit
nonce in that session and saves only its SHA-256 hash as a user attribute. The
nonce is never placed in the email, verification data or redirect URL. It is
valid for 24 hours and consumed on automatic login. The session keeps the most
recent registration binding; registering another account replaces it.

A different browser, an expired or missing session, REST registration, and
links issued before this change still allow account activation, but require
regular login afterwards. Resending a link does not create or replace a browser
binding. The existing activation success page continues to tell visitors to sign in.
Manual activation and disabled automatic login continue to require regular login.
Immediate browser activation (`auto` and `autoWithEmailConfirm`) retains automatic
login when eligible.

If Core frontend secondary authentication is enabled (required or optional),
activation does not authenticate the session. Users complete the regular login
and its configured authenticators. Registration and automatic activation login
never mark a second factor as completed. Automatic login also does not create
the recent-authentication proof required for email changes and account deletion.
An already logged-in visitor's identity and authentication state are preserved.

Public method signatures, activation link format, settings defaults, package
dependencies and database schema are unchanged. The existing trusted PHP call
`Events::autoLogin($User, false)` still bypasses the eligibility checks, including
the session binding; it must not be exposed to untrusted requests. It also
respects the frontend MFA restriction and does not assert secondary authentication.
Custom registration integrations that do not use the browser registration control
fall back to regular login after activation. Run package setup to import the
updated German and English setting description.
