<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Oberfläche
    |--------------------------------------------------------------------------
    | Pfad der mobilen Anrufliste, Middleware und Layout. Das mitgelieferte
    | Layout ist eigenständig (Bootstrap 5.3, PWA-Manifest, Service Worker).
    | Wer das Layout aus nfgarching/core nutzen will, trägt es hier ein.
    */
    'route_prefix' => env('TELEPHONY_ROUTE_PREFIX', 'telefon'),
    'middleware' => ['web', 'auth'],
    'layout' => 'telephony::layouts.mobile',

    // Vite-Einträge der App, die Laravel Echo initialisieren
    // (php artisan install:broadcasting legt resources/js/echo.js an).
    'vite' => ['resources/js/app.js'],

    // Benutzer, die Anrufe sehen dürfen (IDs). Leer = alle angemeldeten.
    'users' => array_filter(explode(',', (string) env('TELEPHONY_USERS', ''))),

    // Wie viele Tage die Liste zurückreicht und nach wie vielen Tagen
    // Anrufe gelöscht werden (telephony:prune, null = nie).
    'list_days' => 7,
    'retention_days' => env('TELEPHONY_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Telefonanbieter
    |--------------------------------------------------------------------------
    | Jeder Anbieter erhält eine eigene Webhook-URL:
    |   https://example.de/telephony/webhook/{provider}/{token}
    | Weitere Anbieter werden per Telephony::extend('name', fn) registriert
    | und hier mit 'driver' => 'name' eingetragen.
    */
    'default_provider' => env('TELEPHONY_PROVIDER', 'sipgate'),

    'providers' => [
        'sipgate' => [
            'driver' => 'sipgate',
            'webhook_token' => env('SIPGATE_WEBHOOK_TOKEN'),
            // optional: nur diese Absender-IPs zulassen (leer = keine Prüfung)
            'allowed_ips' => array_filter(explode(',', (string) env('SIPGATE_WEBHOOK_IPS', ''))),
            // Anrufe in diese Richtung verarbeiten: in, out oder beide
            'directions' => ['in'],
            // REST-API (optional, z. B. für Rufnummern-Bezeichnungen)
            'api' => [
                'base_url' => 'https://api.sipgate.com/v2',
                'token_id' => env('SIPGATE_TOKEN_ID'),
                'token' => env('SIPGATE_TOKEN'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rufnummern
    |--------------------------------------------------------------------------
    */
    'phone' => [
        // Land für Nummern ohne Vorwahl, z. B. "089 1234567"
        'default_region' => env('TELEPHONY_REGION', 'DE'),
        // Durchwahl-Abgleich: 089 123456-23 findet die Zentrale 089 123456,
        // wenn die gespeicherte Nummer mindestens so viele Ziffern hat.
        'prefix_match_min_digits' => 8,
    ],

    // Eigene Rufnummern mit Bezeichnung (Anzeige "an Kanzlei", "an Mobil").
    // Schlüssel in E.164 ohne "+", z. B. '4989123456' => 'Kanzlei'.
    'own_numbers' => [],

    /*
    |--------------------------------------------------------------------------
    | Adressquellen
    |--------------------------------------------------------------------------
    | Alle Quellen werden per "php artisan telephony:sync" in einen lokalen
    | Rufnummern-Index geschrieben. Beim Anruf wird nur der Index befragt –
    | das ist schnell und funktioniert auch, wenn CardDAV/Google gerade
    | nicht erreichbar ist. Die Rückwärtssuche läuft live im Hintergrund.
    | Reihenfolge = Priorität bei mehreren Treffern.
    */
    'sources' => [

        'database' => [
            'driver' => 'eloquent',
            'enabled' => true,
            'label' => 'Datenbank',
            'models' => [
                // Adressen aus der AkV-Datenbank (Namen + NamenKom)
                [
                    'model' => App\Models\Name::class,
                    'label' => 'AkV',
                    'phone_columns' => [
                        'Telefon' => 'telefonnummern',
                        'Mobil' => 'mobilnummern',
                    ],
                    'name' => ['Vorname', 'Nachname'],
                    'street' => 'Strasse',
                    'postal_code' => 'Plz',
                    'city' => 'Ort',
                    'country' => 'Land',
                    'scope' => 'telephony',
                ],
                // Beispiel – an eigene Tabellen anpassen:
                // [
                //     'model' => App\Models\Contact::class,
                //     'label' => 'Mandant',
                //     'phone_columns' => ['phone', 'mobile', 'phone_business'],
                //     'name' => ['first_name', 'last_name'],   // Spalten oder Accessor
                //     'company' => 'company',
                //     'street' => 'street',
                //     'postal_code' => 'zip',
                //     'city' => 'city',
                //     'email' => 'email',
                //     'route' => 'contacts.show',              // Detailseite (optional)
                //     'scope' => null,                         // lokaler Scope, z. B. 'active'
                // ],
            ],
        ],

        'carddav' => [
            'driver' => 'carddav',
            'enabled' => env('CARDDAV_URL') !== null,
            'label' => 'Adressbuch',
            // vollständige URL des Adressbuchs, z. B.
            // https://cloud.example.de/remote.php/dav/addressbooks/users/norbert/contacts/
            'addressbooks' => array_filter(explode(',', (string) env('CARDDAV_URL', ''))),
            'username' => env('CARDDAV_USERNAME'),
            'password' => env('CARDDAV_PASSWORD'),
            'verify_ssl' => env('CARDDAV_VERIFY_SSL', true),
        ],

        'google' => [
            'driver' => 'google',
            'enabled' => env('GOOGLE_CONTACTS_REFRESH_TOKEN') !== null,
            'label' => 'Google Kontakte',
            'client_id' => env('GOOGLE_CONTACTS_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CONTACTS_CLIENT_SECRET'),
            'refresh_token' => env('GOOGLE_CONTACTS_REFRESH_TOKEN'),
            // auch "Weitere Kontakte" (automatisch angelegte) einbeziehen
            'other_contacts' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rückwärtssuche (letzter Fallback, live und gecacht)
    |--------------------------------------------------------------------------
    | Treiber "html": lädt eine Ergebnisseite und liest schema.org-Daten
    | (JSON-LD oder Microdata). Treiber "json": eigener Dienst, Felder per
    | Punkt-Pfad. Es gibt keine offizielle deutsche Rückwärtssuche-API –
    | Nutzungsbedingungen des Anbieters beachten und mit
    | "php artisan telephony:lookup <nummer> --live" testen.
    */
    'reverse_lookup' => [
        'enabled' => env('TELEPHONY_REVERSE_LOOKUP', false),
        'driver' => 'html',
        // Platzhalter: {national} = 08912345, {e164} = +498912345, {digits} = 498912345
        'url' => env('TELEPHONY_REVERSE_LOOKUP_URL', 'https://www.dasoertliche.de/rueckwaertssuche/?ph={national}'),
        'timeout' => 4,
        'cache_days' => 30,
        'negative_cache_days' => 7,
        'user_agent' => 'Mozilla/5.0 (compatible; nfgarching-telephony)',
        // nur für driver "json":
        'json_paths' => [
            'name' => 'name',
            'street' => 'address.street',
            'postal_code' => 'address.zip',
            'city' => 'address.city',
        ],
        // Nur deutsche Festnetz-/Mobilnummern nachschlagen
        'regions' => ['DE'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Live-Benachrichtigung
    |--------------------------------------------------------------------------
    */
    'broadcast' => [
        'enabled' => true,
        'channel' => 'telephony.calls',
    ],

    'push' => [
        'enabled' => env('TELEPHONY_PUSH', true),
        // Benutzer-Model mit NotificationChannels\WebPush\HasPushSubscriptions
        'user_model' => env('TELEPHONY_USER_MODEL', 'App\\Models\\User'),
        // Ziel-Rufnummer (E.164 ohne "+") => Benutzer-IDs. Nicht gelistete
        // Nummern gehen an alle berechtigten Benutzer mit Push-Abo.
        'routes' => [],
        // Auch bei verpassten Anrufen eine zweite Benachrichtigung senden
        'notify_missed' => true,
        'ttl' => 60,
        // eigene Queue, damit Push nicht hinter langen Jobs wartet (null = Standard)
        'queue' => env('TELEPHONY_PUSH_QUEUE'),
    ],
];
