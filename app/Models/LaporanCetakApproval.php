<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class LaporanCetakApproval extends Model
{

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'jenis',
        'tanggal',
        'soft_delete',
        'approved_by',
        'approved_at',
        'is_approved',
        'created_at',
        'updated_at',
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_laporan_cetak_approval';
    protected $guarded    = [];

    public function user()
    {
        return $this->hasOne(User::class, 'uuid', 'user_id');
    }

    public function approver()
    {
        return $this->hasOne(User::class, 'uuid', 'approved_by');
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
