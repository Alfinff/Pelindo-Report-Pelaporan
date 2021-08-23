<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class Profile extends Model
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
	protected $table      = 'ms_profile';
	protected $guarded    = [];

    protected $fillable = [
        'user_id',
        'jenis_kelamin',
        'alamat',
        'tgllahir',
        'uuid',
        'foto'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }
    public function jenis_kelamin()
    {
        return $this->belongsTo(JenisKelamin::class, 'jenis_kelamin', 'uuid');
    }

    public function getFotoAttribute($value)
    {
        if ($value == '') {
            return Storage::disk('s3')->temporaryUrl("images/avatar-mahasiswa.svg", Carbon::now()->addMinutes(5));
        } else {
            return Storage::disk('s3')->temporaryUrl($value, Carbon::now()->addMinutes(5));
        }

        return $value;
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
