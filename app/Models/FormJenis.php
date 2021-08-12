<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormJenis extends Model
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
        'kode',
        'nama'
    ];

    protected $connection = 'pelindo_repport';
    protected $table      = 'ms_form_jenis';
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
