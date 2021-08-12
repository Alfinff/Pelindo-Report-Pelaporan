<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class LaporanIsi extends Model
{

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'laporan_id',
        'form_isian_id',
        'pilihan_id',
        'isian',
        'keterangan'
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_laporan_isi';
    protected $guarded    = [];

    public function laporan()
    {
        return $this->belongsTo(Laporan::class, 'laporan_id', 'uuid');
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
