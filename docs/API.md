# REST API v1

All endpoints use JSON and production clients must use HTTPS. Tokens are sent as `Authorization: Bearer ACCESS_TOKEN` and never as query parameters. Browser CSRF tokens are not used for Bearer-token API requests.

## Discovery

`GET /api/v1/server-info` returns the server version, API version, minimum supported mobile version, and feature flags without authentication.

## Authentication

`POST /api/v1/auth/login` accepts `username`, `password`, `deviceName`, `appVersion`, and the installation's stable UUID `deviceId`. It returns a short-lived access token, a device refresh token, expiry values, device ID, and user profile. Signing in again with the same UUID replaces that installation's previous tokens and updates its existing device record.

`POST /api/v1/auth/refresh` accepts `refreshToken` and returns a new short-lived access token while preserving the device refresh token. An expired, logged-out, or administrator-revoked device token fails.

Mobile clients store the refresh token in Android secure storage. The user's password is used only for the initial sign-in and is never persisted by the mobile application. Administrators can review devices and revoke all of a device's tokens from Settings.

`POST /api/v1/auth/logout` accepts `refreshToken` and revokes active tokens for that device.

## Initial synchronization

`GET /api/v1/snapshot` returns the current cursor and all active printers, slots, manufacturers, materials, spools, and locations for the authenticated workspace.

At first connection, the mobile application offers three explicit choices: upload local data to an empty server, download the server snapshot after creating a local safety backup, or merge both sides with a preview of duplicate printer names. Afterwards, local changes enter a persistent offline queue and are uploaded when the server is reachable.

## Incremental download

`GET /api/v1/sync/changes?after=CURSOR&limit=200` returns ordered changes and a new cursor. Deleted records are represented by tombstones. The maximum page size is 500.

## Uploading changes

`POST /api/v1/sync/push` accepts up to 100 mutations. Every mutation contains a UUID `clientMutationId`, entity `type`, entity UUID `id`, `operation` (`upsert` or `delete`), integer `baseVersion`, and writable `data`. The mutation ID makes retries idempotent. A stale base version produces a conflict containing the current server record rather than silently overwriting it.

Supported entity types are `printer`, `printer_slot`, `manufacturer`, `material`, `spool`, and `location`. Viewer accounts cannot upload changes. Operator accounts can change only spools and printer slots.

```json
{
  "mutations": [
    {
      "clientMutationId": "dbd5cb32-934a-4638-8dbc-055ee914ebf4",
      "type": "spool",
      "id": "ee0fbd87-48b7-4595-8839-d83a9ee2fa48",
      "operation": "upsert",
      "baseVersion": 3,
      "data": {"current_net_weight_g": 482.5}
    }
  ]
}
```

API error responses contain a stable code, message, and request ID. HTTP 401 indicates missing or expired authentication, 403 insufficient permission, 419 an invalid browser CSRF token, 422 invalid input, and 409 a state conflict where applicable.
