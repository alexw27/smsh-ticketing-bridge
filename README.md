# SmartShanghai Ticketing Bridge

Private Symfony bundle for plugging SmartShanghai into the SolidSource ticketing external identity system.

Package name:

```json
"smsh/ticketing-bridge": "*"
```

## What It Provides

- Integration provider key: `smartshanghai`
- Admin integration provider: `SmartShanghai`
- External identity provider button for `/login` and `/register`
- Authentication handler for `/connect/smartshanghai/check`
- WeChat Mini Program sales channel publisher (`wechat_miniprogram`) — generates event QR codes via `getwxacodeunlimit` when an event is published

## WeChat Mini Program event QR codes

On first event publish, the bridge generates a Mini Program QR for the `wechat_miniprogram` sales channel when WeChat credentials are configured.

- **Credentials:** enabled WeChat integration (`System → API / Integrations → WeChat`) or WeChat Pay connection — set the **SmartShanghai Mini Program** App ID and App Secret (`miniprogram_app_secret`).
- **Default page:** `pages/event/event` (override via SmartShanghai integration → **MiniProgram event page path**).
- **Scene:** numeric ticketing event id (read in the Mini Program from `options.scene` on page load).

Ensure the SmartShanghai Mini Program page handles `options.scene` as the event id.

## Expected SmartShanghai Flow

1. User clicks `Continue with SmartShanghai`.
2. The ticketing app redirects to the configured SmartShanghai `login_url`.
3. The bridge appends `callback_url=<absolute /connect/smartshanghai/check URL>`.
4. SmartShanghai authenticates the user and redirects back with a short-lived JWT:

```text
/connect/smartshanghai/check?jwt=<smartshanghai-jwt>
```

The callback also accepts `token=<jwt>` or an `Authorization: Bearer <jwt>` header.

The JWT payload should contain an external user id as either:

- `user_id`
- `sub`

The bridge decodes the JWT only to read that user id for the verify API path. **JWT validity is checked by SmartShanghai's API**, not locally.

## Integration Settings

Configure these under `System -> API / Integrations -> SmartShanghai`:

- `login_url`: SmartShanghai login URL.
- `api_base_url`: SmartShanghai API base URL.
- `api_token`: partner API key sent as the `key` query parameter on every SmartShanghai API call.
- `verify_user_path`: optional path, defaults to `/api2/ticketing/users/{user_id}`.

## Verify User API Contract

The bridge calls:

```text
GET {api_base_url}{verify_user_path}?jwt=<jwt>&key=<api_token>
```

Example default URL:

```text
GET https://smsh.solidsource.software/api2/ticketing/users/123?jwt=...&key=...
```

Expected successful response:

```json
{
  "valid": true,
  "email": "user@example.com"
}
```

`email` is optional. The ticketing app can create a local user with only the linked external identity.

Any non-2xx response or `"valid": false` rejects login.
