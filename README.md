# Traktor

Traktor is a curated video gallery platform where parents manage YouTube content for their children, and kids browse it through a simple, kid-friendly interface.

## Features

- **Parent accounts** — Register devices, curate videos and playlists, and manage child profiles
- **Child galleries** — Personalized, easy-to-navigate video browsing
- **YouTube integration** — Import and organize content from YouTube channels and playlists
- **Device registration** — Signed device tokens with capability detection for tablets, TVs, and browsers
- **Progressive Web App** — Installable with offline caching via service worker
- **Admin panel** — User management, registration approval, and system configuration

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Vite 5, Bootstrap 5, vanilla ES modules
- **Database:** MySQL or PostgreSQL
- **APIs:** YouTube Data API v3

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- MySQL or PostgreSQL

## Getting Started

1. Clone the repository and install dependencies:

```bash
composer install
npm install
```

2. Configure the environment:

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials.

3. Set up the database:

```bash
php artisan migrate
```

4. Build frontend assets:

```bash
npm run build
```

5. Start the development server:

```bash
php artisan serve
```

For asset hot-reloading during development, run `npm run dev` in a separate terminal.

## Environment Variables

Key settings in `.env`:

| Variable | Description |
|----------|-------------|
| `DB_*` | Database connection |
| `DEVICE_TOKEN_TTL` | Device token lifetime in minutes |
| `DEVICE_COOKIE_EXPIRATION` | Device cookie lifetime in minutes |

See `.env.example` for the full list. YouTube API credentials are configured through the admin settings panel after installation.

## Author

**Jonan Steiner**

- Email: [jonan.steiner@gmail.com](mailto:jonan.steiner@gmail.com)
- Website: [jonan.space](https://jonan.space)

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).
