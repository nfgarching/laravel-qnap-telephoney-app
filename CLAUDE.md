# Anrufanzeige für Sipgate-Telephony

Domain und Traefik-Werte: `.env` (Vorlage `.env.example`), Laravel: `src/.env`.
Eigenständige Laravel-13-App auf einem QNAP-NAS (aarch64, Kernel 5.10, Container Station).
Die Fachlogik (sipgate-Webhook, Anrufliste `/telefon`, Web-Push, Rufnummernabgleich, `telephony:*`)
steckt im Package `nfgarching/telephony` (`src/vendor/`, Quelle: github.com/nfgarching/laravel-bootstrap-telephony).
Die App ergänzt nur Anmeldung, Docker-Umgebung und Betrieb. Betrieb und Befehle: `README.md`.

## Regeln

- **Kein PHP, Composer oder Node auf dem Host installieren.** Alles läuft in Containern:
  - Artisan: immer `./artisan <befehl>` (läuft als www-data). Nie `docker compose exec app php artisan`,
    denn als root entstehen root-eigene Dateien (SQLite-WAL, Caches) und die App bricht.
  - Composer: `docker compose exec -u www-data -e HOME=/tmp app composer …`
  - Node/Vite: `docker run --rm -u 1000:100 -e HOME=/tmp -v "$PWD/src":/app -w /app node:22-bookworm-slim …`
- Docker-Befehle brauchen `export DOCKER_CONFIG=/share/homes/$(id -un)/.docker` (Container Station setzt HOME falsch).
- PHP-Images nur auf Basis `php:8.4-fpm-bookworm`. Trixie läuft nicht auf dem QNAP-Kernel.
- Die Host-Shell ist bash 3.2 ohne `od`/`pgrep`. Für Zufallswerte `openssl rand`, längere Abläufe als Skript.
- Nach Änderungen an `src/.env` oder `src/config/*`: `./artisan optimize` und `docker compose restart queue reverb scheduler`.
- Das Package nicht in `vendor/` ändern, sondern im Package-Repo. Danach `composer update nfgarching/telephony`.

## Sicherheit (öffentlich im Internet erreichbar)

- `src/.env` enthält alle Geheimnisse (chmod 600) und gehört nie ins Repository. Werte nie ausgeben oder loggen.
- Anmeldung: Fortify mit eigenen Bootstrap-Ansichten (`resources/views/auth/`), Registrierung und Passwort-Reset aus,
  2FA-Pflicht über `App\Http\Middleware\RequireTwoFactor` (an die `web`-Gruppe gehängt). Benutzer: `./artisan user:*`.
- Proxy-Kette: Traefik (TLS, Header) → nginx → PHP-FPM. nginx übergibt die echte Client-IP als `REMOTE_ADDR` und
  `HTTPS=on` und verwirft `X-Forwarded-*`. Deshalb **keine TrustProxies** einschalten.
- Neue Routen brauchen `auth` (bzw. das Gate `viewTelephony`), außer sie sind bewusst öffentlich wie der Webhook.
- Keine Host-Ports veröffentlichen, erreichbar ist nur, was Traefik weiterleitet.

## Aufbau

| Pfad | Inhalt |
|---|---|
| `docker-compose.yaml` | web (nginx), app, reverb, queue, scheduler, redis; Traefik-Labels |
| `docker/php/`, `docker/nginx/` | PHP-Image, nginx-Konfiguration |
| `src/routes/console.php` | Benutzerbefehle, Zeitplan (`telephony:sync` stündlich, `telephony:prune` täglich) |
| `src/routes/web.php` | `/` → `/telefon`, `/konto` |
| `src/config/telephony.php` | Package-Konfiguration (Rufnummern, Adressquellen, Push) |

Datenbank: SQLite (`src/database/database.sqlite`, WAL). Cache, Sessions und Queue: Redis (flüchtig).
