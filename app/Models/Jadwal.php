<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $hidden = [
        'id'
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'tanggal',
        'shift_id',
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_shift_jadwal';
    protected $guarded    = [];

    public function user()
    {
        return $this->hasOne(User::class, 'uuid', 'user_id');
    }

    public function shift()
    {
        return $this->hasOne(Shift::class, 'uuid', 'kode_shift');
    }
}
