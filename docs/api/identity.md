# Identity API (`/api/v1`)

Bearer tokens: `Authorization: Bearer <token>`. Tokens expire after `IDENTITY_TOKEN_EXPIRATION_MINUTES` (default 30 days).

## Auth

| Method | Path | Auth | Success |
|--------|------|------|---------|
| POST | `/auth/register` | guest | 201 `{ data: PrivateUser, token }` |
| POST | `/auth/login` | guest | 200 `{ data: PrivateUser, token }` |
| POST | `/auth/logout` | bearer | 204 |
| POST | `/auth/logout-all` | bearer | 204 |
| GET | `/auth/me` | bearer | 200 `{ data: PrivateUser }` |
| GET | `/auth/tokens` | bearer | 200 `{ data: Token[] }` |
| DELETE | `/auth/tokens/{id}` | bearer | 204 / 404 |
| POST | `/auth/email/verification-notification` | bearer | 200 |
| GET | `/auth/email/verify/{id}/{hash}` | signed | 200 |
| POST | `/auth/password/forgot` | guest | 200 (anti-enumeration) |
| POST | `/auth/password/reset` | guest | 200 |
| PUT | `/auth/password` | bearer | 200 |
| PUT | `/auth/email` | bearer | 200 `{ data: PrivateUser }` |
| GET | `/users/{username}` | public | 200 `{ data: PublicUser }` / 404 |
| PATCH | `/profile` | bearer | 200 `{ data: PrivateUser }` |

### PrivateUser

`id`, `username`, `name`, `email`, `email_verified_at`, `bio`, `avatar_url`, `created_at`

### PublicUser

`id`, `username`, `name`, `bio`, `avatar_url`, `created_at`

### Token metadata

`id`, `name`, `abilities`, `last_used_at`, `expires_at`, `created_at` (never the secret)

## Password / token policy

- Password length: 12–128 characters
- Password change: revokes other API tokens; keeps current bearer token; clears `sessions` rows for the user
- Password reset: revokes all API tokens; clears `sessions` rows
- Cookie-based SPA sessions (future Sanctum stateful) are separate from PAT; session rows are cleared on password change/reset when the `sessions` table is used

## Errors

Laravel default JSON: 401 / 403 / 404 / 422 / 429
