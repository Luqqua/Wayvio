# HTTPS profiles (local vs ngrok/prod)

Use these presets to flip between HTTP for local dev and enforced HTTPS for tunnels/production.

## One-time code setup
- `app/Http/Middleware/TrustProxies.php`: uses `TRUSTED_PROXIES` and falls back to `*` only in local env, so ngrok `X-Forwarded-*` headers are trusted during local tunnel testing.

## Env files
- `.env.local.example`: HTTP-friendly dev profile (`APP_ENV=local`, `APP_URL=http://localhost:8000`, `FORCE_HTTPS=false`, `FORCE_ROUTE_HTTPS=false`).
- `.env.ngrok.example`: HTTPS-forced profile for ngrok (`APP_ENV=production`, `APP_URL=https://example-subdomain.ngrok-free.app`, `TRUSTED_PROXIES=*`, `FORCE_HTTPS=true`, `FORCE_ROUTE_HTTPS=true`). Contains a commented `APP_URL=http://localhost:8000` so you can flip by comment/uncomment when testing. Marked as TEMP—replace with your real domain and remove the fallback line in production.
- Production: same as ngrok example but `APP_URL` set to your real domain.

## Switching
1) Copy the profile into place:
   - Local HTTP: `cp .env.local.example .env`
   - Ngrok/prod-like HTTPS: `cp .env.ngrok.example .env` (replace the URL first)
2) Clear cached config: `php artisan config:clear` (and `route:clear`/`view:clear` if you cache them).
3) Start the app and, for ngrok, tunnel with host header rewrite: `ngrok http --host-header=rewrite 8000`.

## Notes
- `FORCE_HTTPS` forces generated URLs/assets to https (safe behind proxies).
- `FORCE_ROUTE_HTTPS` adds redirects; only enable after proxy trust is set to avoid loops.
- Keep `APP_URL` accurate; it controls asset and link generation.
