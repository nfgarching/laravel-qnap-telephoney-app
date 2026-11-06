<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kyslik\ColumnSortable\Sortable;
use Nfgarching\Core\Traits\Searchable;

class NamKom extends Model
{
    use HasFactory, Searchable, Sortable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $connection = 'akv';

    protected $table = 'NamenKom';

    public $timestamps = false;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'NR';

    // protected $fillable = [
    //     'Suchbegriff',
    //     'Status',
    // ];
    /**
     * Allowing Mass Assignment
     */
    protected $guarded = [
        'created_at',
        'updated_at',
    ];

    public $searchable = [
        'value',
    ];

    public $sortable = [
        'status',
        'typ',
        'value',
    ];

    /**
     * Eigenschaften von Namen
     */
    public function namen(): BelongsTo
    {
        return $this->belongsTo(Name::class, 'ID', 'ID');
    }

    public function get_btn_icon()
    {
        return 'bi bi-'.config('namkom.typ_icon.'.$this->Typ, 'question');
        // if (config('namkom.typ.' . $this->typ . '.ico')) {
        //     return "bi bi-" . config('namkom.typ.' . $this->typ . '.ico');
        // } else {
        //     return "bi bi-person";
        // }
    }

    public function get_btn_type()
    {
        return config('namkom.typ_type.'.$this->Typ, 'outline-dark');
        // if (config('namkom.status.' . $this->typ . '.btn')) {
        //     return 'table-' . config('namkom.status.' . $this->status . '.btn');
        // } else {
        //     return "primary";
        // }
    }

    public function get_tr_color()
    {
        if (config('namkom.status.'.$this->typ.'.col')) {
            return 'table-'.config('namkom.status.'.$this->status.'.col');
        } else {
            return 'table-default';
        }
    }
}
