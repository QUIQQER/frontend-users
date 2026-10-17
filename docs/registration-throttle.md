# Registration attempt limits

Browser forms, the registration AJAX endpoint and REST registration share two
persistent limits. In the package's **Registration** settings:

| Setting | Default | Configuration key in `registration` |
| --- | --- | --- |
| Maximum registration attempts per IP | 20 | `throttleIpLimit` |
| Maximum registration attempts per identity | 5 | `throttleIdentityLimit` |

Both use a fixed **15-minute** window starting with the first attempt for that
IP address or identity. Further attempts do not extend it. After expiry, the next
attempt starts a fresh window. Only the attempt counts are configurable. Values
must be whole numbers between 1 and 1000000; missing or invalid values, including
zero, use the defaults.

Failed attempts count too. IP capacity is reserved first, before validation or
account creation. A request rejected by the IP limit does not create identity
counters. Email addresses and usernames supplied for registration are counted
separately across IP addresses, ignoring letter case and surrounding whitespace.
Changing email-provider aliases or internal punctuation is not part of this
normalization. A request rejected by an identity limit still consumes IP capacity.

The IP comes from Symfony's `Request::getClientIp()` and its trusted-proxy
configuration. Forwarded headers from untrusted clients cannot select another
source. Equivalent IPv6 spellings and IPv4-mapped IPv6 addresses share their
respective counters. Missing or invalid source addresses and storage failures
prevent registration. Server-side callers must provide a request with the real
client address too. Shared company or mobile-network IPs share the IP allowance;
adjust the IP count to match expected legitimate traffic.

Counters are reserved atomically in the shared database before the registration
transaction. Registration validation errors or its later rollback do not refund
these reservations. As with other database operations, an outer transaction
opened by a custom caller also owns these writes; such callers must reserve
outside any transaction they intend to roll back.

No global registration quota, global mail budget or pending-account cap is added.
The existing activation-resend limits remain independent. Requests over a limit
use the existing registration error response, with a translated retry message;
endpoint parameters, response structures and activation modes remain unchanged.
No user, group membership, verification or registration mail is created for a
request rejected by these limits.

Run package setup when deploying this change:

```sh
./console quiqqer:package --setup=quiqqer/frontend-users
```

Setup imports the settings, translations and the portable
`quiqqer_frontend_users_registration_throttle` table. It stores hashed keys,
attempt counts and expiry times. Expired rows are cleaned on subsequent attempts.
