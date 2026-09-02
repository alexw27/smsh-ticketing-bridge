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
- WeChat Scanner sales channel publisher (`wechat_scanner`) — generates scanner Mini Program QR codes when an event is published
- WeChat Mini Program QR codes on discount campaigns (admin campaign form)
- WeChat Mini Program QR codes on event affiliates (admin event → Affiliates)
- SmartShanghai event listing sync on publish — links SMSH listings via report access token and imports thumbnail

## WeChat Scanner event QR codes

On first event publish, the bridge generates a Mini Program QR for the `wechat_scanner` sales channel.

- **Credentials:** enabled WeChat integration with slug `wechat-scanner` (`System → API / Integrations`).
- **Page:** `pages/scanner/scanner` with scene `id={eventId}`.

## WeChat campaign Mini Program QR codes

When a campaign is saved, the bridge generates a Mini Program QR for each targeted event and shows it on the campaign admin form (below the web share QR). Global and venue-only campaigns are skipped until an event (or price-category) target is added.

- **Credentials:** WeChat Pay MiniProgram App ID + App Secret by default (same as MiniProgram checkout). Override with SmartShanghai setting **MiniProgram WeChat connection slug**. Do not use `wechat-scanner` unless the QR should open the door MiniProgram.
- **Page:** `pages/smtkEvent/smtkEvent`.
- **Scene:** `id={eventId}&aci={campaignId}` (SmartTicket event id and campaign id, e.g. `id=5614&aci=99`). MiniProgram checkout sends `aci` as `affiliate_campaign_id`.

Generation failure is logged and does not block saving the campaign.

## WeChat event-affiliate Mini Program QR codes

When an event affiliate is saved, the bridge generates a Mini Program QR and shows it on the affiliate admin form (below the web share QR).

- **Credentials:** same consumer WeChat MiniProgram as campaign QRs (WeChat Pay by default).
- **Page:** `pages/smtkEvent/smtkEvent`.
- **Scene:** `id={eventId},ea={affiliateId}` (e.g. `id=5753,ea=14`). WeChat `getwxacodeunlimit` rejects `&` in scene, so pairs are comma-separated. MiniProgram checkout sends `ea` as `event_affiliate_id`.

Generation failure is logged and does not block saving the affiliate.

## SmartShanghai event listing sync

When an event is published, the bridge:

1. Creates a promoter report access token (label: `SmartShanghai listing sync`).
2. Calls `PATCH {api_base_url}/api2/admin/smtk-event-bridge/{event_id}?key={api_token}` with `{ "access_token": "<raw token>" }` — `{event_id}` is the ticketing event id (same as `smtk_id` on SmartShanghai).
3. Downloads `data.thumbnail_path` from the response and sets it as the event thumbnail (admin → Media).

Requires an enabled SmartShanghai integration with `api_base_url` and `api_token`. Optional setting **Event bridge API path** (default `/api2/admin/smtk-event-bridge/{event_id}`).

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
- `miniprogram_event_page`: unused for campaign and event-affiliate QRs (those always use `pages/smtkEvent/smtkEvent`).
- `miniprogram_wechat_connection_slug`: optional WeChat integration slug for campaign/affiliate QRs. Empty uses WeChat Pay MiniProgram credentials.
- `event_bridge_path`: optional path, defaults to `/api2/admin/smtk-event-bridge/{event_id}`.

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
