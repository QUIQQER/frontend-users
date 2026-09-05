# Account lookup protection

The existing `userExists`, `emailExists` and `existsUnverifiedActivation` AJAX
endpoints remain available with their existing parameters. Username/email
availability answers and the registration-to-login flow are preserved.

## Shared lookup limit

`registration.throttleLookupIpLimit` sets the number of lookups allowed from one
IP address in 15 minutes, default **60**. It is configurable in the registration
settings. Missing or invalid values (including zero) use the default; accepted
values are integers from 1 to 1000000. Shared office/mobile IP addresses share the
quota. The three endpoints share one counter, reserved before validation or user
lookup. Unknown users, invalid emails and unauthorized activation lookups count
too. Changing endpoint, session or cookies does not reset the quota.

The existing registration throttle table and atomic reservation logic are reused
with separate `lookup:ip:` keys. Registration and activation-mail quotas remain
independent. Only hashed keys, counts and expiry timestamps are stored. The fixed
window starts at the first request and does not slide on further requests.
Equivalent IPv6 spellings and IPv4-mapped IPv6 addresses share counters. Client
addresses use Symfony's trusted-proxy configuration. Missing addresses or storage
failures do not allow a lookup through.

Requests beyond the quota fail through the existing QUIQQER AJAX exception
mechanism with code **429** and a translated retry message. This is not a `false`
availability answer. The JavaScript promise wrappers propagate errors; the
controls display a message and restore their loading/disabled states without
discarding input. A later retry follows the existing registration or login flow.

These limits reduce automated enumeration. Individual availability checks and
distributed requests remain possible by design.

## Activation email lookup

`existsUnverifiedActivation(userId)` returns an email only if the same browser
session has proved the password for that account within the last **5 minutes**.
Otherwise it returns `false`, retaining the existing return format.

The package uses `onAjaxCallBefore` to decorate the registered Core
`ajax_users_login` callable without changing its signature or Core source. Core
still checks origins, allowed authenticators, passwords and MFA. Only a fresh
QUIQQER password authentication can establish proof; cached authentication and
registration flags cannot. `onUserLoginError` records the specific inactive-user
error after password authentication. Core clears and invalidates the login
session; the decorator then stores only the narrow lookup proof in the renewed
session before rethrowing that same error. The session ID is regenerated when
recording proof. Authentication flags cleared by Core are never restored.

A successful primary password step awaiting MFA can also establish this narrow
proof. It does not complete MFA, authenticate a session, activate a user or grant
profile access. A subsequent primary login attempt removes previous proof, and
failed authentication does not create a replacement. Lookups do not extend the
proof. Ending the session removes it.

The requested user must match the proof. The account must be inactive and have a
pending, valid link verification bound to that UUID and the activation handler.
The current email is then returned for the existing resend button. Resending
continues to use the existing endpoint and its independent source/account limits.

## Scope of issues #29, #30 and #87/009

- The previously unlimited username/email helper queries are now throttled.
  Their intentional availability answers are retained.
- The activation helper no longer reveals an email merely because the caller
  supplies a user ID, UUID or an arbitrary failed login.
- The additional Core-login concern in #30 was inspected separately. The current
  Core gives the same public 401 message for an unknown username and an incorrect
  password; this is exercised through the real login AJAX callable. Core's
  per-account backoff and session failure counter still do not constitute a
  persistent source-IP login quota for unknown users. Core changes are not part
  of this package change.

The original scan's expectation of identical availability responses is therefore
not a completion claim: availability feedback is intentionally preserved.

## Deployment and verification

Run package setup to import the setting, locales and the two event listeners:

```sh
./console quiqqer:package --setup=quiqqer/frontend-users
```

The existing registration throttle table must be available. No new table,
interface migration or package major version is required.

```sh
./tools/phpunit tests/integration/AccountLookupWorkflowTest.php
./tools/phpunit tests/integration/RegistrationThrottleConcurrencyTest.php
node tests/javascript/account-lookup.test.cjs
./tools/phpcs
./tools/phpstan
./tools/phpunit
```

The JavaScript tests use Node's built-in test runner and production AMD methods
with controlled collaborators. The PHP workflow tests use Core's real login,
session lifecycle and AJAX dispatcher against isolated database fixtures; only
mail transport and installation-specific hooks are isolated. The concurrency
tests run separate processes against a shared database.
