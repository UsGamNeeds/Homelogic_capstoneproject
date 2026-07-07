# mfax.to Fax Provider — Design

Date: 2026-06-21
Branch: multi-tenant
Status: Approved

## Goal

Add `mfax.to` as a sendable/receivable fax provider in Evergreen, alongside the
existing Telnyx, Documo (mFax-by-Documo), iFax, and Fake providers. `mfax.to` is
a distinct vendor (NOT Documo) with its own REST API at `developers.mfax.to`.

## Authoritative API (from https://developers.mfax.to/openapi.json)

- Base URL: `https://developers.mfax.to/v1`
- Auth: `Authorization: Bearer zk_live_…`
- Send: `POST /v1/faxes`, `multipart/form-data`, fields:
  - `to` (string, E.164, required)
  - `file` (file, PDF, required)
  - No `from` / caller-id / subject / cover fields (org number is implicit).
  - Response `202` → `Fax` object.
- Get: `GET /v1/faxes/{uuid}` → `Fax`
- List: `GET /v1/faxes?status=&direction=&since=&limit=` → `Fax[]`
- Delete: `DELETE /v1/faxes/{uuid}`
- `Fax` shape: `{ uuid, to, status, direction, page_count, media_url,
  failure_reason, created_at, updated_at }`
- Status lifecycle: `queued → sending → delivered | failed`
- Errors: `{ code, message }`; 400 invalid_number, 402 quota_exceeded,
  403 subscription_inactive, 429 rate_limited (Retry-After).
- Webhooks: ONE org-level URL configured in dashboard. Events:
  `fax.delivered`, `fax.failed`, `fax.received`. Envelope:
  `{ id, type, created (unix), data: Fax }`. Dedupe on `id` (at-least-once).
  Inbound PDF via `data.media_url` (presigned, time-limited).
- Signature (only if a signing secret is configured on the webhook):
  - `X-Zelda-Timestamp`: unix seconds
  - `X-Zelda-Signature`: `t=<ts>,v1=<hex>` where
    `hex = HMAC-SHA256(secret, "<ts>.<raw_body>")`
  - Reject if `ts` older than 5 minutes (replay guard).
- No phone-number provisioning endpoints (numbers managed in dashboard only).

## Deployment model: ONE shared mfax account across all facilities

Confirmed with Taal. This mirrors the existing **iFax** integration, not the
per-facility (Telnyx/Documo) model:

- One `zk_live_…` API key, one webhook URL, one signing secret for the whole org.
- Outbound: each `FaxSetting.credentials.api_key` holds the (same) shared key so
  `FaxManager::forFacility()` keeps working unchanged.
- Webhook routing secret + signing secret live in app config/env (shared),
  exactly like `config('fax.ifax.*')`.

## Components

### 1. `app/Services/Fax/Providers/MFaxFaxProvider.php`

Implements `FaxProvider`. Modeled on `IFaxFaxProvider`.

- `key()` → `'mfax'`
- `displayName()` → `'mfax.to'`
- `description()` → HIPAA-compliant cloud fax (developers.mfax.to).
- `credentialSchema()` → single `api_key` secret field (Bearer `zk_live_…`).
  (Webhook signing secret is shared app config, not a per-facility credential.)
- `testConnection()` → `GET /v1/faxes?limit=1`; ok on 2xx.
- `send(SendFaxRequest)` → `POST /v1/faxes` multipart `{to, file}`; drop
  `fromNumber`/`subject` (unsupported). `providerFaxId = response.uuid`,
  status from `response.status` (default `queued`).
- `searchAvailableNumbers()` → `[]` (no API).
- `purchaseNumber()` / `releaseNumber()` → throw `RuntimeException`
  "Provision numbers in the mfax dashboard." (no silent fake)
- `verifyWebhookSignature(Request)` → parse `X-Zelda-Signature`
  (`t=,v1=`), recompute `hash_hmac('sha256', "{$t}.{$raw}", $secret)`,
  `hash_equals` compare, reject if `|now - t| > 300`. Secret comes from shared
  config (passed in via provider construction or read in the webhook controller).
- `parseWebhook(Request)` → envelope `{id,type,data}`:
  - `fax.delivered` / `fax.failed` → `WebhookResult::status(providerFaxId=data.uuid,
    newStatus=map(data.status), eventId=id, statusReason=data.failure_reason)`
  - `fax.received` → `WebhookResult::inbound(InboundFax{providerFaxId=data.uuid,
    toNumber=data.to, mediaUrl=data.media_url, pageCount=data.page_count,
    receivedAt=data.created_at})`, `eventId=id`
  - else → `WebhookResult::ignore()`
- `downloadInboundMedia(InboundFax)` → GET `mediaUrl` (presigned, no auth),
  return bytes; throw on non-2xx.
- Status map: `queued→queued`, `sending→sending`, `delivered→delivered`,
  `failed→failed`; inbound → `received`.

### 2. `app/Http/Controllers/Webhook/MFaxFaxWebhookController.php`

Modeled on `IFaxFaxWebhookController` (shared account, single callback URL):

- Route param `{secret}` compared against `config('fax.mfax.webhook_secret')`.
- Signature verified with `config('fax.mfax.signing_secret')` (X-Zelda).
- Idempotency on envelope `id` via `FaxEvent.provider_event_id`.
- Outbound status (`fax.delivered/failed`): locate `Fax` by
  `provider_fax_id = data.uuid` (`withoutGlobalScopes`), `FaxManager::applyStatus`.
- Inbound (`fax.received`): route to facility by destination number `data.to`
  via `FaxNumber` lookup → `FaxManager::recordInbound`.
- Broadcast `FaxStatusUpdated` / `FaxReceived` as the iFax controller does.

### 3. `routes/api.php`

`Route::post('/webhooks/fax/mfax/{secret}', MFaxFaxWebhookController::class);`
next to the other fax webhook routes.

### 4. `config/fax.php`

- Append `MFaxFaxProvider::class` to `providers`.
- Add:
  ```php
  'mfax' => [
      'webhook_secret'  => env('FAX_MFAX_WEBHOOK_SECRET'),
      'signing_secret'  => env('FAX_MFAX_SIGNING_SECRET'),
  ],
  ```

### 5. `.env` / `.env.example`

`FAX_MFAX_WEBHOOK_SECRET=` and `FAX_MFAX_SIGNING_SECRET=` (signing secret from
the mfax dashboard webhook config).

### 6. `tests/Feature/MFaxFaxProviderTest.php`

Modeled on `IFaxFaxProviderTest`, `Http::fake()`:
- `send()` returns `providerFaxId` from `uuid`, status `queued`.
- `send()` failure path (402 quota / 400 invalid_number) → `SendResult::fail`.
- `testConnection()` ok / fail.
- Status webhook (`fax.delivered`) updates matching outbound `Fax` by uuid.
- Inbound webhook (`fax.received`) routes by `to` number → creates inbound
  `Fax`, stores PDF from `media_url`.
- Signature verify: valid passes; tampered body fails; stale timestamp (>5min)
  fails; missing/garbage header fails.

## Known limitations (flagged + accepted)

1. Inbound routing requires each facility's mfax DID registered in `fax_numbers`.
   Setup step, not code. If unmapped, inbound cannot be attributed to a facility.
2. Outbound caller-id is the shared account's number (mfax send has no `from`).
   All facilities' outbound faxes share one sender identity. mfax API limitation.
3. Inbound sender number is not in mfax's documented webhook `Fax` schema, so
   inbound "from" may be blank.

## Out of scope

- Number provisioning UI/automation (no mfax API support).
- Migrating existing faxes between providers.
- Per-facility caller-id (not supported by mfax).

## Testing / verification

- `php artisan test --filter=MFaxFaxProviderTest` green with quoted output.
- `php artisan config:clear` then confirm provider appears in
  `GET /api/.../fax/providers`.
- Manual: configure a facility with the shared `api_key`, Test Connection green,
  send a real fax to a test number, confirm status webhook flips it to delivered.
