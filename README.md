# Holy Face Academic Monitoring & Grading Suite (AMGS)

**HOLY FACE OF JESUS LYCEUM OF SAN JOSE, INC.** — shared MySQL database, PHP 8 REST API, and three client apps (web, mobile, desktop).

| App | Folder | Purpose |
|-----|--------|---------|
| Web | `web/` | Promotional site + portal links |
| Mobile | `mobile/` | Student digital ID + AI study tools |
| Desktop | `desktop/` | Teacher grade entry & class roster |
| API | `api/` | Single JSON backend for all clients |
| Database | `database/` | Schema + fictional seed data |

## Quick start

1. Install [XAMPP](https://www.apachefriends.org/) with PHP 8+ and MySQL/MariaDB.
2. Follow **[docs/SETUP_XAMPP.md](docs/SETUP_XAMPP.md)** (database import, API vhost, Composer).
3. Default API base URL (local): `http://localhost/holyface/api/public`
4. Seed logins (change in production): see **docs/USER_MANUAL.md**.

## Documentation

- [XAMPP setup guide](docs/SETUP_XAMPP.md)
- [User manual](docs/USER_MANUAL.md)
- [Assumptions & limitations](docs/ASSUMPTIONS.md)

## Branding

Replace the placeholder logo at `web/public/assets/img/hf-logo.png` (and copies in mobile/desktop) with your official asset. Default URL: `https://www.holyface.school/assets/img/branding/hf-logo.png`.

## License

Proprietary — Holy Face of Jesus Lyceum of San Jose, Inc.
