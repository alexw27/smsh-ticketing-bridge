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
4. SmartShanghai authenticates the user and redirects back with a JWT:

```text
/connect/smartshanghai/check?jwt=<smartshanghai-jwt>
```

The callback also accepts `token=<jwt>` or an `Authorization: Bearer <jwt>` header.

The JWT payload should contain either:

- `user_id`
- `sub`

The bridge then calls the configured SmartShanghai API to verify that user.

## Integration Settings

Configure these under `System -> API / Integrations -> SmartShanghai`:

- `login_url`: SmartShanghai login URL.
- `api_base_url`: SmartShanghai API base URL.
- `api_token`: bearer token used by the ticketing app when calling SmartShanghai.
- `verify_user_path`: optional path, defaults to `/api/ticketing/users/{user_id}`.

## Verify User API Contract

The bridge currently calls:

```text
GET {api_base_url}{verify_user_path}?jwt=<jwt>
Authorization: Bearer <api_token>
```

Example default URL:

```text
GET https://www.smartshanghai.com/api/ticketing/users/123?jwt=...
```

Expected successful response:

```json
{
  "valid": true,
  "email": "user@example.com"
}
```

`email` is optional, but the current ticketing app requires an email to create or link a user when no prior `user_external_identity` link exists.

Any non-2xx response or `"valid": false` rejects login.
