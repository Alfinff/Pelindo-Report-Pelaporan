<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class LaporanCatatan extends Model
{

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'judul',
        'isi',
        'form_jenis',
        'user_id'
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_laporan_catatan';
    protected $guarded    = [];

    public function kategori()
    {
        return $this->belongsTo(FormJenis::class, 'form_jenis', 'uuid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
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
