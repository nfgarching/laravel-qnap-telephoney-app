# Anrufanzeige für Sipgate-Telephony

Eigenständige Laravel-13-App mit [`nfgarching/telephony`](https://github.com/nfgarching/laravel-bootstrap-telephony):
eingehende sipgate-Anrufe live auf dem Handy (Reverb + Web-Push), Datenbank SQLite.
Läuft auf einem QNAP-NAS (Container Station) hinter einem vorhandenen Traefik, der TLS übernimmt.

## Aufbau

```
Internet ─▶ Traefik (TLS, Let's Encrypt, Security-Header)  eigener Stack, Netz $TRAEFIK_NETWORK
              └─▶ telephony_web (nginx, nur src/public/ gemountet)
                    ├─▶ telephony_app      PHP-FPM 8.4  (src/)
                    └─▶ telephony_reverb   WebSockets unter /app
                  telephony_queue      queue:work --queue=telephony,default (Web-Push)
                  telephony_scheduler  schedule:work (telephony:sync stündlich, prune täglich)
                  telephony_redis      Cache, Sessions, Queue (nur intern, nicht persistent)
```

Kein Container veröffentlicht einen Host-Port; erreichbar ist nur, was Traefik weiterreicht.

| Datei | Inhalt |
|---|---|
| `docker-compose.yaml` | Dienste, Traefik-Labels |
| `.env` | Domain und Traefik-Einstellungen für Compose (Vorlage `.env.example`, nicht im Repository) |
| `docker/php/` | PHP-8.4-FPM-Image (Bookworm, wegen QNAP-Kernel 5.10) + `php.ini` |
| `docker/nginx/default.conf` | nginx hinter Traefik |
| `start.sh` | `docker compose up -d` mit QNAP-Workarounds (`./start.sh --build` baut neu) |
| `artisan` | `php artisan` im app-Container **als www-data** – immer diesen Wrapper benutzen |
| `src/` | Laravel-App, `src/.env` enthält alle Geheimnisse (chmod 600) |

## Absicherung

- **Anmeldung** (Laravel Fortify, eigene Bootstrap-Ansichten): keine Registrierung, kein Passwort-Reset per Mail.
  Benutzer nur per `./artisan user:create`.
- **Zwei-Faktor-Pflicht** (TOTP): Ohne bestätigte 2FA ist nur `/konto` erreichbar (`App\Http\Middleware\RequireTwoFactor`,
  abschaltbar mit `AUTH_REQUIRE_2FA=false`). Wiederherstellungscodes auf `/konto`.
- **Brute-Force-Schutz**: Login 5/min je E-Mail+IP und 20/h je IP, 2FA-Code 5/min; Passwörter mind. 12 Zeichen mit Buchstaben und Ziffern.
- **Sitzung**: Redis, verschlüsselt, Cookies `Secure`, `HttpOnly`, `SameSite=Lax`.
- **Zugriff auf Anrufe**: zusätzlich auf Benutzer-IDs beschränkbar (`TELEPHONY_USERS=1,2`).
- **sipgate-Webhook**: geheimes 64-stelliges Token in der URL **und** IP-Allowlist
  (`SIPGATE_WEBHOOK_IPS`, push-api.sipgate.net: 217.116.118.254, 212.9.46.32). Sonst 404.
- **WebSockets**: Reverb akzeptiert nur die eigene Domain als Origin (`REVERB_ALLOWED_ORIGINS`); private Kanäle über Gate `viewTelephony`.
- **HTTP**: HSTS, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `noindex`; `APP_DEBUG=false`, keine Versions-Header.
- **Proxy-Header**: nginx übergibt PHP die echte Client-IP als `REMOTE_ADDR` und `HTTPS=on`; `X-Forwarded-*` werden verworfen
  (keine TrustProxies, kein IP-Spoofing).

## Erste Schritte

```sh
./artisan user:create                 # fragt E-Mail, Name, Passwort
```

1. `https://<APP_DOMAIN>` auf dem Handy öffnen, anmelden, 2FA einrichten.
2. Auf der Anrufliste die Glocke antippen und Benachrichtigungen erlauben. iPhone: *Teilen → Zum Home-Bildschirm*, von dort öffnen.
3. In sipgate unter *Webhooks* als URL für eingehende Anrufe eintragen:
   `https://<APP_DOMAIN>/telephony/webhook/sipgate/<SIPGATE_WEBHOOK_TOKEN aus src/.env>`

Test ohne echten Anruf: `./artisan telephony:simulate "089 123456"` (bzw. `--answer`, `--keep`).

## Benutzer

```sh
./artisan user:list
./artisan user:create [email] [--name=]
./artisan user:password <email>      # setzt auch "Angemeldet bleiben" zurück
./artisan user:reset-2fa <email>     # Handy verloren → 2FA neu einrichten
./artisan user:delete <email>
```

## Konfiguration ändern

Nach jeder Änderung an `src/.env` oder `src/config/*`:

```sh
./artisan optimize
docker compose restart queue reverb scheduler
```

Telephony-Einstellungen (eigene Rufnummern, CardDAV, Google, Rückwärtssuche): `src/config/telephony.php`
und die README des Packages. `VITE_*`-Werte werden beim Frontend-Build eingebaut (siehe unten).

## Updates

```sh
# PHP-Pakete (inkl. nfgarching/telephony, dev-main von GitHub)
docker compose exec -u www-data -e HOME=/tmp app composer update
./artisan migrate --force && ./artisan optimize
docker compose restart queue reverb scheduler

# Frontend (Echo/Reverb)
docker run --rm -u 1000:100 -e HOME=/tmp -v "$PWD/src":/app -w /app node:22-bookworm-slim sh -c "npm ci && npm run build"

# PHP-Image
./start.sh --build
```

## Neu aufsetzen (aus dem Git-Repository)

Nicht im Repository: `.env`, `src/.env`, `src/vendor/`, `src/node_modules/`, `src/public/build/`, die SQLite-Datenbank.

```sh
export DOCKER_CONFIG=/share/homes/$(id -un)/.docker

# Compose/Traefik: APP_DOMAIN, Traefik-Netz, Resolver, Router-Name eintragen
cp .env.example .env && chmod 600 .env

docker compose build app
R="docker run --rm -u 1000:100 -e HOME=/tmp -v $PWD/src:/var/www/html telephony-app:latest"

# Laravel: APP_URL, REVERB_ALLOWED_ORIGINS und VITE_REVERB_HOST auf APP_DOMAIN setzen
cp src/.env.example src/.env && chmod 600 src/.env
# Geheimnisse eintragen (oder aus der Sicherung übernehmen!):
#   APP_KEY             → $R php artisan key:generate
#   REVERB_APP_ID/KEY/SECRET, SIPGATE_WEBHOOK_TOKEN → openssl rand -hex 20
#   VAPID_*             → $R php artisan webpush:vapid
# Neue VAPID-Schlüssel machen bestehende Push-Abos ungültig, neuer Webhook-Token muss in sipgate.

$R composer install --no-dev --optimize-autoloader
touch src/database/database.sqlite && $R php artisan migrate --force
docker run --rm -u 1000:100 -e HOME=/tmp -v "$PWD/src":/app -w /app node:22-bookworm-slim sh -c "npm ci && npm run build"
./start.sh && ./artisan optimize
```

## Sicherung

Wichtig sind nur `.env`, `src/.env` (APP_KEY, VAPID-Schlüssel, Tokens) und `src/database/database.sqlite`.
Konsistente Kopie im laufenden Betrieb:

```sh
docker compose exec -u www-data app sqlite3 database/database.sqlite ".backup 'storage/app/backup.sqlite'"
```

## Logs

```sh
docker compose logs -f web queue reverb
tail -f src/storage/logs/laravel-*.log
```
