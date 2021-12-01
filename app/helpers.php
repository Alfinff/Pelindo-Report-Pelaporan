<?php

\Firebase\JWT\JWT::$leeway = 10;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

use App\Models\User;
use App\Models\Jadwal;
use App\Models\Shift;

function writeLog($message)
{
	try {
		\Log::error($message);
		return response()->json([
			'success' => false,
			'message' => env('APP_DEBUG') ? $message : 'Terjadi kesalahan',
			'code'    => 500,
		]);
	} catch (Exception $e) {
		return false;
	}
}

function writeLogValidation($message)
{
	try {
		\Log::error($message);
		return response()->json([
            'success' => false,
            'message' => 'Validasi gagal',
            'code'    => 422,
        ]);
	} catch (Exception $e) {
		return false;
	}
}

function generateUuid()
{
	try {
		return Uuid::uuid4();
	} catch (Exception $e) {
		return false;
	}
}

function generateJwt(User $user)
{
	try {
		$key = '';
	    $key  = str_shuffle('QWERTYUIOPASDFGHJKLZXCVBNM1234567890');

		$dataUser = [];
		$dataUser = [
			'id'           	=> $user->id ?? '',
			'uuid'          => $user->uuid ?? '',
			'nama'          => $user->nama ?? '',
			'role'          => $user->role ?? '',
			'email'         => $user->email ?? '',
			'no_hp'         => $user->no_hp ?? '',
			'fcm_token'     => $user->fcm_token ?? '',
			'profile'       => ''
		];

		if($user->profile) {
			$dataUser['profile'] = [
				'uuid'             => $user->profile->uuid ?? '',
				'foto'             => $user->profile->foto ?? '',
				'tgllahir'         => $user->profile->tgllahir ?? '',
				'jenis_kelamin'    => $user->profile->jenis_kelamin ?? '',
				'alamat'           => $user->profile->alamat ?? '',
				'user_id'          => $user->profile->user_id ?? '',
			];
		}

		$payload = [];
	    $payload = [
			'iss'  => 'lumen-jwt',
			'iat'  => time(),
			'exp'  => time() + 60 * 60 * 24,
			'key'  => $key,
			'user' => $dataUser,
	    ];

	    // find user
	    $user = User::find($user->id);

	    // update key
		if($user) {
			$user->update([
				'key' => $key,
			]);
		}

	    return JWT::encode($payload, env('JWT_SECRET'));
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function parseJwt($token)
{
	try {
		return JWT::decode($token, env('JWT_SECRET'), array('HS256'));
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function validasiJadwal($jadwalshiftid, $userid)
{
	try {
		$minus_oneday = strtotime('-1 day', strtotime(date('Y-m-d')));
				
		// cek id jadwal terdaftar / tidak
		$cekJadwal = '';
		$cekJadwal = Jadwal::where('uuid', $jadwalshiftid)->where('user_id', $userid)->first();
		if (!$cekJadwal) {
			return array('errcode' => 1, 'data'  => '');
		}

		// cek jadwal hari ini
		$cekJadwal = '';
		$cekJadwal = Jadwal::where('uuid', $jadwalshiftid)->where('user_id', $userid)->whereDate('tanggal', date('Y-m-d'))->first();
		if(!$cekJadwal) {
			// cek jadwal hari sebelumnya
			$cekJadwal = Jadwal::where('user_id', $userid)->whereDate('tanggal', date('Y-m-d', $minus_oneday))->where('kode_shift', 'M')->first();
			if (!$cekJadwal) {
				return array('errcode' => 1, 'data'  => '');
			} else {
				// cek kalau jadwal sebelumnya shift malam
				if(isset($cekJadwal->kode_shift)) {
					if($cekJadwal->kode_shift != 'M') 
					{
						return array('errcode' => 1, 'data'  => '');
					}
				}
			}
		} 
		
		// cek jadwal libur atau tidak
		if ($cekJadwal->kode_shift == 'L') {
			return array('errcode' => 2, 'data'  => '');
		}

		// cek ketika jadwal sebelumnya malam
		if($cekJadwal->kode_shift == 'M') {
			if(date('H') >= 8) {
				// cek hari ini libur atau tidak
				$cekJadwalHariIni = '';
				$cekJadwalHariIni = Jadwal::with('user', 'shift')->where('user_id', $userid)->whereDate('tanggal', date('Y-m-d'))->first();
				if(isset($cekJadwalHariIni->kode_shift)) {
					if($cekJadwal->kode_shift == 'L') {
						return array('errcode' => 2, 'data'  => '');
					}
				}
			}
		}
		
		return array('errcode' => 0, 'data'  => $cekJadwal);
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function validasiJamShift($kode_shift)
{
	try {
		$jammulai = '';
		$jamselesai = '';
		$jamsekarang = '';
		$jamsekarang = strtotime(date('Y-m-d H:i:s'));
		$minus_oneday = strtotime('-1 day', strtotime(date('Y-m-d')));

		$getShift = Shift::where('kode', $kode_shift)->first();
		
		if(!$getShift) {
			return array(
				'errcode' => 1,
				'jammulai' => $jammulai,
				'jamselesai' => $jamselesai,
				'jamsekarang' => $jamsekarang,
				'data' => $getShift
			);
		} else {
			$jammulai = strtotime(date('Y-m-d H:i:s', strtotime($getShift->mulai)));
			if($kode_shift == 'M') {
				$jammulai = '';
				// $jammulai = date('Y-m-d', $minus_oneday);
				$tes = strtotime(date('Y-m-d', $minus_oneday).date('H:i:s', strtotime($getShift->mulai)));
				$jammulai = strtotime(date('Y-m-d H:i:s', $tes));
				$jamselesai = strtotime(date('Y-m-d H:i:s', strtotime($getShift->selesai)));
				if(date('H') >= 23) {
					$jamselesai = strtotime(date('Y-m-d H:i:s', strtotime("+1 day", $jamselesai)));
				}
			} else {
				$jamselesai = strtotime(date('Y-m-d H:i:s', strtotime($getShift->selesai)));   
			}
		}

		return array(
			'errcode' => 0,
			'jammulai' => $jammulai,
			'jamselesai' => $jamselesai,
			'jamsekarang' => $jamsekarang,
			'data' => $getShift
		);
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function uploadFileS3($base64, $path)
{
	try {
		$file = base64_decode($base64);
		Flysystem::connection('awss3')->put($path, $file);
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function generateOtp()
{
	try {
		return substr(str_shuffle('1234567890'), 0, 6);
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function formatTanggal($tanggal)
{
	try {
		return date('Y-m-d H:i:s', strtotime($tanggal));
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function sendFcm($to, $notification, $data)
{
	try {
		$response = Http::withHeaders([
			'Authorization' => 'key=' . env('KEY_FCM'),
			'Content-Type'  => 'application/json',
		])->post(env('URL_FCM'), [
			'to'           => $to,
			'notification' => $notification,
			'data'         => $data,
		]);

		return $response;
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function generateRandomString($length = 6) {
	try {
		return substr(str_shuffle(str_repeat($x = '1234567890', ceil($length / strlen($x)))), 1, $length);
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function create_time_range($start, $end, $interval = '30 mins', $format = '12') {
	try {
		$startTime = strtotime($start); 
		$endTime   = strtotime($end);
		$returnTimeFormat = ($format == '12')?'g A':'G';

		$current   = time(); 
		$addTime   = strtotime('+'.$interval, $current); 
		$diff      = $addTime - $current;

		$times = array(); 
		while ($startTime < $endTime) { 
			$times[] = date($returnTimeFormat, $startTime); 
			$startTime += $diff; 
		} 
		$times[] = date($returnTimeFormat, $startTime); 
		return $times; 
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function create_jam_range($start, $end, $interval = '30 mins', $format = '12') {
	try {
		$startTime = strtotime($start); 
		$endTime   = strtotime($end);
		$returnTimeFormat = ($format == '12')?'g A':'G';

		$current   = time(); 
		$addTime   = strtotime('+'.$interval, $current); 
		$diff      = $addTime - $current;

		$times = array(); 
		while ($startTime < $endTime) { 
			$times[] = 'Jam '.date($returnTimeFormat, $startTime); 
			$startTime += $diff; 
		} 
		if(date($returnTimeFormat, $startTime) == '0') {
			$times[] = 'Jam 00';
		} else {
			$times[] = 'Jam '.date($returnTimeFormat, $startTime);
		}
		return $times; 
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function getrangedaymonth($month, $year)
{
	try {
		$range = [];

		// jumlah hari bulan ini
		$dayrange=cal_days_in_month(CAL_GREGORIAN,$month,$year);
		
		for($i = 1; $i <= ((int)$dayrange); $i++) {
			array_push($range, $i);
		}

		return $range;
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function getnameofday($date)
{
	try {
		return date('l', strtotime($date));
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}

function color_value($str) {
	try {
		$str = str_replace(' ', '', trim(strtolower($str), ' '));
		if(is_numeric($str)) {
			return '#495b64';
		}
		if(($str == 'ok') || ($str == 'standby')) {
			return '#1289ff';
		} else if(($str == 'on') || ($str == 'hijau(penuh)')) {
			return 'rgb(24 255 63)';
		} else if(($str == 'normal')) {
			return '#10c747';
		} else if(($str == 'notok') || ($str == 'off') || ($str == 'merah(kosong)')) {
			return 'rgb(255 3 3)';
		} else if(($str == 'critical') || ($str == 'alarm') || ($str == 'running')) {
			return '#ff4a23';
		} else if(($str == 'smokephotoelectric') || ($str == 'smokeionization')) {
			return '#a31d00';
		} else if($str == 'notapplicable') {
			return '#fbb912';
		} else {
			return 'rgb(0 0 0)';
		}
	} catch (Exception $e) {
		return writeLog($e->getMessage());
	}
}
