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

## Expected SmartShanghai Flow

1. User clicks `Continue with SmartShanghai`.
2. The ticketing app redirects to the configured SmartShanghai `login_url`.
3. The bridge appends `callback_url=<absolute /connect/smartshanghai/check URL>`.
4. SmartShanghai authenticates the user and redirects back with a short-lived RS256 JWT:

```text
/connect/smartshanghai/check?jwt=<smartshanghai-jwt>
```

The callback also accepts `token=<jwt>` or an `Authorization: Bearer <jwt>` header.

The JWT must be signed by SmartShanghai with its private key. Configure the matching public key in the ticketing app integration settings.

The JWT header must use:

```json
{
  "alg": "RS256",
  "typ": "JWT"
}
```

The JWT payload should contain:

```json
{
  "iss": "smartshanghai",
  "aud": "solidsource-ticketing",
  "sub": "123456",
  "iat": 1770000000,
  "exp": 1770000300,
  "jti": "one-time-token-id"
}
```

The external user id can be provided as either:

- `user_id`
- `sub`

The bridge verifies the signature, `iss`, `aud`, `exp`, and optional `nbf`, then calls the configured SmartShanghai API to verify that user.

## Integration Settings

Configure these under `System -> API / Integrations -> SmartShanghai`:

- `login_url`: SmartShanghai login URL.
- `api_base_url`: SmartShanghai API base URL.
- `api_token`: bearer token used by the ticketing app when calling SmartShanghai.
- `public_key`: SmartShanghai JWT public key in PEM format.
- `issuer`: expected JWT `iss`, defaults to `smartshanghai`.
- `audience`: expected JWT `aud`, defaults to `solidsource-ticketing`.
- `clock_tolerance_seconds`: allowed clock skew for `exp` / `nbf`, defaults to `60`.
- `verify_user_path`: optional path, defaults to `/ticketing/users/{user_id}`.

## Verify User API Contract

The bridge currently calls:

```text
GET {api_base_url}{verify_user_path}?jwt=<jwt>
Authorization: Bearer <api_token>
X-Api-Key: <api_token>
```

Example default URL:

```text
GET https://www.smartshanghai.com/api2/ticketing/users/123?jwt=...
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
