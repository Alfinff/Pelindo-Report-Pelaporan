<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class Laporan extends Model
{

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'shift',
        'form_jenis',
        'user_id'
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_laporan';
    protected $guarded    = [];

    public function user()
    {
        return $this->hasOne(User::class, 'uuid', 'user_id');
    }

    public function form_jenis()
    {
        return $this->hasOne(FormJenis::class, 'uuid', 'form_jenis');
    }

    public function isi()
    {
        return $this->hasMany(LaporanIsi::class, 'laporan_id', 'uuid');
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
