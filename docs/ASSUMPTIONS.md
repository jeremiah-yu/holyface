# Assumptions & Known Limitations

## Assumptions

1. **XAMPP** is the local dev stack; production will use HTTPS, hardened PHP settings, and non-default DB credentials.
2. **Single school** — one SHS campus; no multi-tenant isolation in v1.
3. **School year 2025–2026** is current in seed data; switching SY is manual via `school_years.is_current`.
4. **DepEd transmutation** uses the Appendix 2 bands stored in `transmutation_table` (editable by the Registrar). Default component weights are school-proposed profiles pending confirmation; Work Immersion/performance-heavy weights are not treated as official until Settings → Confirmed is checked.
5. **AI study tools** use on-server text processing (no OpenAI/external API) to guarantee zero PII leakage; schools may plug in an external LLM later behind a redaction layer.
6. **Student photos** use a placeholder path; file upload pipeline is not included in v1.
7. **Enrollment forms** on the legacy PHP site remain separate; this suite focuses on monitoring/grading after enrollment.
8. **Admin CRUD** for users/enrollment is partially via database/API foundation; full registrar UI is a future increment.
9. **Mobile** targets Expo SDK 52; **Desktop** uses Electron 31 + Vite — versions pinned in package files.
10. **API path** defaults to `http://localhost/holyface/api/public` — adjust for your htdocs layout.
11. **Legal pack v1.1** is a school-project draft, not legal advice. Contact email uses `registrar@holyface.school`; phone `(02) 8546-8364` is a public directory listing to confirm with the Registrar. The DPO is the Registrar until the School names one. AI study tools currently run on School servers (no third-party AI provider). Retention after leaving is five years unless DepEd/law requires longer.

## Known limitations

- Web login CSRF is session-based; API JSON clients use JWT only.
- Refresh token rotation endpoint is stored but not exposed in clients v1 (access token TTL 1 hour).
- Mobile has no offline grade encoding (students are read-mostly). Teacher desktop queues edits encrypted and syncs when the API is reachable; conflicts surface if the server row is newer.
- No push notifications for grade posts.
- Teacher desktop prints class record / report of grades via the system print dialog (Save as PDF) and Excel-compatible CSV. Not a paginated SF9/SF10 template.
- Rate limiting is IP + DB based; distributed attacks need WAF in production.
- `grade_components` duplicate labels are allowed; UX deduplication not enforced.
- Android/iOS store deployment (signing, privacy labels) not covered in setup guide.
- Backup/restore procedures for MySQL left to IT policy.

## Security checklist before go-live

- [ ] Rotate `JWT_SECRET` and all seed passwords
- [ ] Disable `APP_DEBUG`
- [ ] Enable HTTPS
- [ ] Restrict MariaDB user to `holyface_amgs` only
- [ ] Review `CORS_ALLOWED_ORIGINS`
- [ ] Schedule DB backups
- [ ] Register DPO processes per RA 10173
