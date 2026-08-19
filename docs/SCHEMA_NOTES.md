# Schema notes

High-level data model for developers. Prefer migrations under `database/migrations/` as the source of truth for columns.

## Core entities

### `users`

- Parents: `parent_id` null, `role` `user` or `admin`, email/password, `account_status`, optional `how_heard_about`
- Children: `parent_id` set, typically no real email login; optional encrypted `view_pin` (presence implies PIN required — no separate `pin_enabled` column)
- Parent convenience auth: optional encrypted `admin_pin` for backend access from the registered-device Settings modal
- Profile: `username`, `slug`, `profile_picture`, `locale`, `is_viewable`, `appears_in_profile_selection`
- Gallery UI: `channel_order` (JSON), `show_all_content_section` (bool), `hidden_channels` (JSON)
- Cache: `cache_version` (timestamp) — bumping invalidates versioned user/content caches
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

- `video_watch_events` — granular player events (nullable `device_registration_id`); sessions derived server-side from events
- `watch_sessions` — session aggregates (populated from events)

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
| `2025_12_08_233308_create_video_watch_events_table` | Analytics event storage |
| `2025_12_08_233309_create_watch_sessions_table` | Analytics session aggregates |
| `2025_11_18_103606_add_cache_version_to_users_table` | Cache versioning on users |
| `2025_11_17_231253_add_channel_order_and_show_all_content_…` | Per-profile gallery channel layout |
| `2026_08_19_210000_add_admin_pin_to_users_table` | Adds separate parent admin-access PIN storage |

Do not re-run wipe migrations against production without an explicit backup and approval.
