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
use App\Models\FormJenis;
use Illuminate\Support\Facades\DB;
use GrahamCampbell\Flysystem\Facades\Flysystem;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DashboardController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getDashboard(Request $request)
    {
        try 
        {
            $decodeToken = parseJwt($this->request->header('Authorization'));
            $uuid = $decodeToken->user->uuid;
            $user = User::with('role', 'profile')->where('uuid', $uuid)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            $jumlaheos = 0; $jumlaheos = User::where('role', env('ROLE_EOS'))->count();
            $jumlahsupervisor = 0; $jumlahsupervisor = User::where('role', env('ROLE_SPV'))->count();
            $jumlahsuperadmin = 0; $jumlahsuperadmin = User::where('role', env('ROLE_SPA'))->count();
            $jumlahlaporanshift = 0; $jumlahlaporanshift = LaporanShift::whereDate('created_at', date('Y-m-d'))->count();
            $cctv = 0; $cctv = Laporan::whereDate('created_at', date('Y-m-d'))->where('form_jenis', env('FORM_CCTV'))->count();
            $cleaning = 0; $cleaning = Laporan::whereDate('created_at', date('Y-m-d'))->where('form_jenis', env('FORM_CLEANING'))->count();
            $facilities = 0; $facilities = Laporan::whereDate('created_at', date('Y-m-d'))->where('form_jenis', env('FORM_FACILITIES'))->count();

            $chartLaporan = 0; 

            $data = [
                'jumlah' => [
                    'eos' => $jumlaheos,
                    'supervisor' => $jumlahsupervisor,
                    'superadmin' => $jumlahsuperadmin,
                    'laporan' => [
                        'shift' => $jumlahlaporanshift,
                        'form' => [
                            'cctv' => $cctv,
                            'cleaning' => $cleaning,
                            'facilities' => $facilities
                        ]
                    ]
                ],
                'chartlaporan' => $chartLaporan
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data Dashboard',
                'code'    => 200,
                'data'  => $data
            ]);
        } catch (\Throwable $th) {
            return writeLog($th->getMessage());
        }
    }

}
