<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Informasi;
use App\Models\InformasiUser;
use App\Models\Jadwal;
use App\Models\Laporan;
use App\Models\LaporanIsi;
use App\Models\LaporanShift;
use App\Models\LaporanDikerjakan;
use App\Models\FormIsian;
use Illuminate\Support\Facades\DB;
use GrahamCampbell\Flysystem\Facades\Flysystem;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class LaporanController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getLaporan(Request $request)
    {
        // return $request->date;
        $decodeToken = parseJwt($this->request->header('Authorization'));
        $uuid        = $decodeToken->user->uuid;
        $user        = User::where('uuid', $uuid)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan',
                'code'    => 404,
            ]);
        }
        else {
            try {
                $laporan =  Laporan::with('user', 'jadwal.shift')->whereHas('jadwal.shift')->orderBy('created_at', 'desc');
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal.shift')->orderBy('created_at', 'desc');
                
                if ($request->date) {
                    $date = $request->date;
                    $laporan = $laporan->whereDate('created_at', '=', $date);
                    $catatan = $catatan->whereDate('created_at', '=', $date);
                }
                if ($request->nama) {
                    $nama = $request->nama;
                    $laporan = $laporan->whereHas('user', function ($q) use ($nama) {
                        $q->where('nama', $nama);
                    });
                    $catatan = $catatan->whereHas('user', function ($q) use ($nama) {
                        $q->where('nama', $nama);
                    });
                }
                if ($request->shift) {
                    $shift = $request->shift;
                    $laporan = $laporan->whereHas('jadwal.shift', function ($q) use ($shift) {
                        $q->where('uuid', $shift);
                    });
                    $catatan = $catatan->whereHas('jadwal.shift', function ($q) use ($shift) {
                        $q->where('uuid', $shift);
                    });
                }

                $laporan = $laporan->paginate(25);
                $catatan = $catatan->get();

                $laporan->map(function ($laporan) {
                    if ($laporan->user != null) {
                       return $laporan->nama_eos = $laporan->user->nama;
                    }
                });
                $laporan->map(function ($laporan) {
                    if ($laporan->jadwal->shift != null) {
                       return $laporan->shift = $laporan->jadwal->shift->nama;
                    }
                });
                
                $laporan = $laporan->setPath('https://pelindo.primakom.co.id/api/pelaporan/laporan/');
                if (empty($laporan)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not Found',
                        'code'    => 404,
                    ]);
                } 
                else {
                    return response()->json([
                        'success' => true,
                        'message' => 'OK',
                        'code'    => 200,
                        'data'  => $laporan,
                        'catatan_shift' => $catatan
                    ]);
                }

            } catch (\Throwable $th) {
                return writeLog($th->getMessage());
            }
        }
        
    }

    public function detailLaporan($id)
    {
        $decodeToken = parseJwt($this->request->header('Authorization'));
        $uuid        = $decodeToken->user->uuid;
        $user        = User::where('uuid', $uuid)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak ditemukan',
                'code'    => 404,
            ]);
        }
        else {
            try {
                $eos =  Laporan::where('uuid', $id)->with('user', 'jadwal.shift')->first();

                $laporan = LaporanIsi::where('laporan_id', $id)->with('pilihan.isianForm')->get();
                $laporan->map(function ($laporan) use ($eos) {
                    return $laporan->nama_eos = $eos->user->nama;
                });
                // $laporan->map(function ($laporan) use ($eos) {
                //     return $laporan->shift = $eos->jadwal->shift->nama;
                // });
                if (empty($laporan)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not Found',
                        'code'    => 404,
                    ]);
                } 
                else {
                    return response()->json([
                        'success' => true,
                        'message' => 'OK',
                        'code'    => 200,
                        'eos'   => $eos,
                        'data'  => $laporan
                    ]);
                }

            } catch (\Throwable $th) {
                return writeLog($th->getMessage());
            }
        }
    }

    public function getCatatanShift(Request $request)
    {
        
    }

}
