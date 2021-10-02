<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormIsianKategori extends Model
{
    use HasFactory;

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'kode'
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_form_isian_kategori';
    protected $guarded    = [];

    public function getCreatedAtAttribute($value)
    {
        return formatTanggal($value);
    }

    public function getUpdatedAtAttribute($value)
    {
        return formatTanggal($value);
    }
}
