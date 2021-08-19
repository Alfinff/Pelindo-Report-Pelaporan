<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FormPilihan extends Model
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

    protected $connection = 'pelindo_repport';
	protected $table      = 'ms_form_pilihan';
	protected $guarded    = [];

    public function isianForm()
    {
        return $this->belongsTo(FormIsian::class, 'isian_id', 'uuid');
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
