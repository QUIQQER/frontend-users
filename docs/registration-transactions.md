# Registration transactions

Browser registration and `PostRegister::registerUser()` use the same database
transaction and registration mutex. Run the package setup when deploying this
change:

```sh
./console quiqqer:package --setup=quiqqer/frontend-users
```

Setup creates `quiqqer_frontend_users_registration_lock` with the configured table
prefix and seeds its permanent row. Repeating setup preserves that row. Missing
schema or a missing row prevents registration; there is no unlocked fallback.

The normal validation runs first. Before user creation, an UPDATE acquires the
database mutex and username and email availability are checked again. The mutex
is held until the transaction ends, including user data, default group membership,
addresses, passwords, activation and verifications. A conflicting registration is
rejected before user creation, registrar hooks or mail delivery.

The existing database comparison/collation rules remain authoritative. Email is
trimmed for the final check, matching Core persistence. This does not introduce
case folding, new global unique indexes or a migration of existing identities.
The shared mutex also coordinates different projects and application servers
using the same database. It serializes registrations, including time spent in
their existing hooks and mail calls; it is not a rate limiter.

An exception escaping the registration work rolls back its database changes.
Registrar `onRegistered()` exceptions now abort registration instead of being
logged and followed by a partially initialized account. Rollback also restores
session attributes and evicts the newly created identity from Core's user object
cache. The browser and REST completion events run after this transaction scope
succeeds. Public method signatures and REST response shapes are unchanged.

Custom registrars and hooks must use the shared QUIQQER DBAL connection and must
not commit or roll back a transaction they do not own. A caller's outer
transaction retains control of its final commit. Such a caller must also manage
application state if it later rolls back a successful registration. Transactional
tables are required, including InnoDB for the participating MySQL tables.

Database transactions cannot undo already sent mail, external requests or file
writes. Existing hooks and mail delivery remain synchronous, so an unrelated late
failure can leave external effects even though database state is rolled back.
Core also handles some hook/database errors internally; only exceptions that
reach the registration scope trigger its rollback. A complete outbox and
transaction-aware extension lifecycle would require a separate Core contract.

The mutex protects the two package entry points. Independent Core user creation,
imports, profile changes and direct registrar calls do not automatically
participate. System-wide canonical identity uniqueness requires a separate Core
design and migration that accounts for existing data and email-login settings.

The concurrency regressions use separate PHP processes and a shared, isolated
SQLite database with a barrier after validation. They cover browser/browser,
REST/REST and mixed registrations, conflicting usernames and emails, successful
distinct identities, and absence of residual users, addresses, memberships and
verifications for the loser. Existing registration policy tests also exercise
the transaction on the configured CI database.
