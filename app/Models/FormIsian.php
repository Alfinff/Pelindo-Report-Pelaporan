<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class FormIsian extends Model
{

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'judul',
        'form_jenis',
        'kategori',
        'status',
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_form_isian';
    protected $guarded    = [];

    public function jenis_form()
    {
        return $this->hasOne(FormJenis::class, 'kode', 'form_jenis');
    }

    public function kategori_isian()
    {
        return $this->hasOne(FormIsianKategori::class, 'kode', 'kategori');
    }

    public function pilihan()
    {
        return $this->hasMany(FormPilihan::class, 'isian_id', 'uuid');
    }

    public function getCreatedAtAttribute($value)
    {
        return formatTanggal($value);
    }

    public function getUpdatedAtAttribute($value)
    {
        return formatTanggal($value);
    }
    
}
