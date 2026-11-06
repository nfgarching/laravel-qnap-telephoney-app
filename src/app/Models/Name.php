<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Kyslik\ColumnSortable\Sortable;
use Nfgarching\Core\Traits\Searchable;

class Name extends Model
{
    /** NamenKom-Typen der Rufnummern */
    public const TYP_TELEFON = '14001';

    public const TYP_MOBIL = '14002';

    use HasFactory, Searchable, Sortable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $connection = 'akv';

    protected $table = 'Namen';

    public $timestamps = false;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'NR';

    /**
     * Allowing Mass Assignment
     */
    protected $guarded = [
        'NRTime',
    ];

    public $searchable = [
        'Suchbegriff',
        'Vorname',
        'Nachname',
        'Strasse',
        'Plz',
        'Ort',
        'Land',
    ];

    public $sortable = [
        'NR',
        'ID',
        'Anrede',
        'Vorname',
        'Nachname',
        'Strasse',
        'Plz',
        'Ort',
        'Land',
        'Status',
        'Typ',
    ];

    public function kommunikation(): HasMany
    {
        return $this->hasMany(NamKom::class, 'ID', 'ID');
    }

    /** Nur Rufnummern (Telefon, Mobil) aus NamenKom */
    public function rufnummern(): HasMany
    {
        return $this->kommunikation()->whereIn('Typ', [self::TYP_TELEFON, self::TYP_MOBIL]);
    }

    /**
     * Für telephony:sync: nur Namen mit Rufnummer, Nummern vorab geladen.
     */
    public function scopeTelephony(Builder $query): void
    {
        $query->whereHas('rufnummern')->with('rufnummern');
    }

    /** Telefonnummern (Typ 14001) für config('telephony.sources') */
    public function getTelefonnummernAttribute(): array
    {
        return $this->rufnummernVomTyp(self::TYP_TELEFON);
    }

    /** Mobilnummern (Typ 14002) für config('telephony.sources') */
    public function getMobilnummernAttribute(): array
    {
        return $this->rufnummernVomTyp(self::TYP_MOBIL);
    }

    /** Suchbegriff ohne Zusätze wie "(beruflich)" */
    protected function rufnummernVomTyp(string $typ): array
    {
        return $this->rufnummern
            ->where('Typ', $typ)
            ->map(fn (NamKom $kom) => trim(preg_replace('/\(.*?\)/', '', (string) $kom->Suchbegriff)))
            ->filter()
            ->values()
            ->all();
    }

    public function getAdresse()
    {
        $txt = $this->Anrede
            .chr(13)

            .($this->Vorname ? $this->Vorname.' ' : '')
            .$this->Nachname
            .chr(13)

            .$this->Strasse
            .chr(13)

            .$this->Plz.' '.$this->Ort
            .($this->Land ? chr(13).$this->Land : '');

        return $txt;
    }

    public function getNamen()
    {
        return ($this->Vorname ? $this->Vorname.' ' : '').$this->Nachname;
    }

    public function getPhonenumbers()
    {
        // dump($this->kommunikation()->get());
        $collection = collect();
        foreach ($this->kommunikation()->get() as $item) {
            // code...
            // dump($item->Suchbegriff,$item->Typ);
            switch ($item->Typ) {
                case '14001':
                case '14002':
                    $collection = $collection->concat([$item->Suchbegriff]);
                    break;

                default:
                    // code...
                    break;
            }
        }

        return $collection;
    }

    public function getSuchbegriff()
    {
        return $this->Nachname
            .($this->Vorname ? ', '.$this->Vorname : '')
            .($this->Ort ? ', '.$this->Ort : '');
    }

    /**
     * Bildet die Briefanrede aus Anrede und Nachname.
     *
     * Vorlagen stehen in config('namen.anredebrief'); ist die Anrede dort nicht
     * hinterlegt (Firma, Behörde, keine Anrede), greift die neutrale Vorgabe.
     * Gleiche Logik wie im Namen-Formular (resources/js/namen-create.js).
     *
     * Die Vorlagen sind auf die Adressform ("Herrn") geschlüsselt; die
     * Nominativform "Herr" (ältere Chatbot-Extraktionen) wird darauf abgebildet.
     */
    public static function buildAnredeBrief(?string $anrede, ?string $nachname): string
    {
        $anrede = trim((string) $anrede);
        $nachname = trim((string) $nachname);

        if ($anrede === 'Herr') {
            $anrede = 'Herrn';
        }

        $vorlage = $anrede !== '' ? config('namen.anredebrief.'.$anrede) : null;

        if ($vorlage === null || $nachname === '') {
            return config('namen.anredebrief.default');
        }

        return $vorlage.' '.$nachname.',';
    }

    /**
     * Findet mögliche Dubletten zu einem extrahierten Namens-Datensatz.
     *
     * Ein gleicher Nachname allein reicht nicht – zusätzlich muss ein
     * weiteres Identitätsmerkmal übereinstimmen (Vorname ODER Ort ODER Plz).
     * Nur wenn der neue Datensatz keine dieser Zusatzangaben hat, wird der
     * reine Nachnamenstreffer als Warnung zurückgegeben.
     *
     * @param  array<string, mixed>  $data  namePreview-Struktur (surname, prename, address.city, address.zip)
     */
    public static function findPotentialDuplicates(array $data): Collection
    {
        $surname = trim((string) ($data['surname'] ?? ''));

        if ($surname === '') {
            return collect();
        }

        $prename = trim((string) ($data['prename'] ?? ''));
        $city = trim((string) ($data['address']['city'] ?? ''));
        $zip = trim((string) ($data['address']['zip'] ?? ''));

        // Kandidaten mit ähnlichem Nachnamen (tolerant gegenüber Tippfehlern und
        // mehrteiligen Namen). Die Feinauswahl passiert ebenfalls in SQL, damit das
        // Limit erst nach dem Filtern greift – bei häufigen Nachnamen (> 50 Treffer)
        // fielen sonst gerade die neuesten Datensätze aus der Kandidatenmenge.
        $query = static::query()->where('Nachname', 'LIKE', '%'.$surname.'%');

        if (str_contains($surname, ' ') && ! (clone $query)->exists()) {
            $query = static::query()->where(function ($q) use ($surname) {
                foreach (explode(' ', $surname) as $word) {
                    if (mb_strlen($word) >= 3) {
                        $q->orWhere('Nachname', 'LIKE', '%'.$word.'%');
                    }
                }
            });
        }

        // Ohne weitere Merkmale bleibt nur der Nachname als (schwaches) Signal.
        if ($prename !== '' || $city !== '' || $zip !== '') {
            $query->where(function ($q) use ($prename, $city, $zip) {
                foreach (['Vorname' => $prename, 'Ort' => $city, 'Plz' => $zip] as $column => $value) {
                    if ($value !== '') {
                        $q->orWhereRaw("LOWER(TRIM({$column})) = ?", [mb_strtolower($value)]);
                    }
                }
            });
        }

        return $query
            ->orderByDesc('NR')
            ->limit(20)
            ->get(['NR', 'Nachname', 'Vorname', 'Anrede', 'Plz', 'Ort', 'Typ']);
    }

    public function get_btn_icon()
    {
        if (config('namen.typ.'.$this->Typ.'.ico')) {
            return 'bi bi-'.config('namen.typ_icon.'.$this->Typ);
        } else {
            return 'bi bi-person';
        }
    }

    public function get_btn_type()
    {
        if (config('namen.status_color.'.$this->Status)) {
            return ''.config('namen.status_color.'.$this->Status);
        } else {
            return 'primary';
        }
    }

    public function get_tr_color()
    {
        if (config('namen.status_color.'.$this->Status)) {
            return ''.config('namen.status_color.'.$this->Status);
        } else {
            return 'primary';
        }
    }
}
