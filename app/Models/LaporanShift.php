<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class LaporanShift extends Model
{

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'judul',
        'isi',
        'form_jenis',
        'user_id',
        'jadwal_shift_id'
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_laporan_shift';
    protected $guarded    = [];

    public function kategori()
    {
        return $this->belongsTo(FormJenis::class, 'form_jenis', 'kode');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'jadwal_shift_id', 'uuid');
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
