<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class LaporanDikerjakan extends Model
{

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'jadwal_shift_id',
        'form_jenis',
        'user_id',
        'created_at'
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_laporan_dikerjakan';
    protected $guarded    = [];

    public function user()
    {
        return $this->hasOne(User::class, 'uuid', 'user_id');
    }

    public function form_jenis()
    {
        return $this->hasOne(FormJenis::class, 'uuid', 'form_jenis');
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
