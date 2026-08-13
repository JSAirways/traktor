# Schema notes

High-level data model for developers. Prefer migrations under `database/migrations/` as the source of truth for columns.

## Core entities

### `users`

- Parents: `parent_id` null, `role` `user` or `admin`, email/password, `account_status`
- Children: `parent_id` set, typically no real email login; optional encrypted `view_pin`
- Profile: `username`, `slug`, `profile_picture` / legacy `cat_gif`, `locale`, `is_viewable`, `appears_in_profile_selection`
- Optional JSON `parental_controls` (not enforced in viewing paths yet)

### `device_registrations`

- `parent_user_id` → owning parent
- `device_uid` — durable client UUID; unique with parent (`parent_user_id`, `device_uid`)
- `device_token` — current token id (UUID); signed cookie carries `rid`/`tid`/`exp`
- `token_expires_at`, `capabilities`, `capability_hash`
- `device_name`, `user_agent`, `screen_resolution`, `is_active`, `registered_at`, `last_used_at`
- Viewing: `current_viewing_slug`, `viewing_validated_at`, `viewing_expires_at`

### `device_child_visibility`

- Per-device which children appear on profile selection (`device_registration_id`, `child_user_id`, `is_visible`)

### Content

- `videos`, `playlists` — YouTube ids/metadata, `user_id` (profile owner), `display_order`, `is_visible`, channel fields as applicable

### Analytics

- `video_watch_events` — granular player events (nullable `device_registration_id`)
- `watch_sessions` — session aggregates

### `settings`

- Key/value app config: `youtube_api_key`, admin notification emails, Google Cloud project/service account JSON, asset version, etc.

## Local fixtures

`DatabaseSeeder` is intentionally empty (production-oriented rewrite). For a local smoke environment:

1. `php artisan migrate`
2. Register a parent via `/register-account` (or `/register`)
3. Approve in DB or tinker (`account_status = approved`)
4. Optionally set `role = admin`
5. Log in → Admin → Settings → paste YouTube API key
6. Register a device from `/welcome` or `/register-device`
7. Create a child profile and (optional) PIN; add content under Content

## Migrations of note

| Migration | Effect |
|-----------|--------|
| `2026_08_13_160000_add_device_uid_…` | Adds `device_uid`, unique with parent; **deletes existing device rows** (tester cutover) |
| `2026_08_13_170000_drop_device_fingerprint_…` | Removes retired `device_fingerprint` column |

Do not re-run wipe migrations against production without an explicit backup and approval.
