# Activation mail resending

The public `frontend/auth/resendActivationMail` AJAX endpoint acknowledges every
request with the boolean `true`. This acknowledges receipt of the request; it does
not confirm that an account exists or that an email was sent. The endpoint name,
`email` parameter, and boolean response format remain unchanged.

A resend requires an inactive account and its existing, pending, unexpired
`ActivationLinkVerification`. Missing, expired, verified, or mismatched verification
records do not cause mail delivery or token creation. The original verification
URL, token, expiry, project, and language are retained. SMTP failure also leaves the
original verification untouched. Initial registration continues to use
`Handler::sendActivationMail()`; resending uses `Handler::resendActivationMail()`.

Public requests have two mandatory database-backed cooldowns:

- One request per source IP every 60 seconds, applied before account lookup.
- One resend attempt per account UUID every 300 seconds, across all source IPs.

Unknown addresses consume the source quota. Failed mail attempts retain both
reservations, so SMTP failures cannot bypass the limits. A blocked source does not
reserve the account quota. The source comes from Symfony's `Request::getClientIp()`;
forwarded IP headers are only used according to the installation's trusted-proxy
configuration. Equivalent IPv6 spellings share a quota. Requests without a valid
source IP do not send mail. Shared IPs also share the source quota.

The `quiqqer_frontend_users_activation_resend` table stores hashed, namespaced
account/source keys and expiration timestamps. Reservations use a conditional DBAL
QueryBuilder update and a unique primary key for concurrent inserts. Insert
conflicts roll back their own transaction/savepoint, preserving a surrounding
transaction on PostgreSQL. The public resend path reserves cooldowns before calling SMTP.
Expired reservations are removed on subsequent requests. Database errors prevent
sending and are logged; they do not change the public acknowledgement.

After updating the package, import its database schema with the package setup:

```sh
./console package --setup=quiqqer/frontend-users
```

Targeted regression tests:

```sh
./tools/phpunit --filter 'ActivationResend|testResendCannotBind|testResendPreserves'
```

The concurrency tests use an isolated SQLite database by default. To run them
against MySQL, MariaDB, or PostgreSQL, set
`FRONTEND_USERS_RESEND_TEST_DATABASE` to a JSON object of DBAL connection parameters
and filter for `ActivationResendConcurrencyTest`. That connection must point to a
disposable test database: the tests import the package schema and clear the throttle
table. Workers share this connection configuration and race on new and expired
reservations. They also verify that denied reservations leave outer transactions
usable.
