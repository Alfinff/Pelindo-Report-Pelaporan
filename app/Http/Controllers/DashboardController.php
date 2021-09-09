<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Shift;
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
            $cctv = 0; $cctv = Laporan::whereDate('created_at', date('Y-m-d'))->where('form_jenis', env('FORM_CCTV'))->count(); $cctv = ((int)$cctv/24)*100; $cctv = number_format((double)$cctv, 2, '.', '');
            $cleaning = 0; $cleaning = Laporan::whereDate('created_at', date('Y-m-d'))->where('form_jenis', env('FORM_CLEANING'))->count(); $cleaning = ((int)$cleaning/24)*100; $cleaning = number_format((double)$cleaning, 2, '.', '');
            $facilities = 0; $facilities = Laporan::whereDate('created_at', date('Y-m-d'))->where('form_jenis', env('FORM_FACILITIES'))->count(); $facilities = ((int)$facilities/24)*100; $facilities = number_format((double)$facilities, 2, '.', '');

            $chartLaporan = [];
            foreach(User::where('role', env('ROLE_EOS'))->get() as $item => $val) {
                $countLaporanForm = Laporan::with('user')->where('user_id', $val->uuid)->whereMonth('created_at', date('m'))->count();
                $countLaporanShift = LaporanShift::with('user')->where('user_id', $val->uuid)->whereMonth('created_at', date('m'))->count();

                $data['user'] = $val->nama;
                $data['laporanform'] = $countLaporanForm;
                $data['laporanshift'] = $countLaporanShift;

                array_push($chartLaporan, $data);
            }

            $shifthariini = [];
            $shifthariini = Jadwal::with('user', 'shift')->whereNotIn('kode_shift', ['L'])->whereDate('tanggal', date('Y-m-d'))->get();

            $shift = Shift::orderBy('mulai', 'asc')->whereNotIn('kode', ['L']);

            if ($request->shift) {
                $shift = $shift->where('kode', $request->shift);
            } 

            $shift = $shift->get();

            $shift = $shift->map(function ($dataShift) use ($request){
                $datanya = [];

                $perangkat  = [];
                $perangkat  = FormIsian::orderBy('judul', 'asc');

                $perangkat = $perangkat->where('kategori', 'PAC');

                $perangkat = $perangkat->where('tipe', 'LIKE', '%ISIAN%');

                $perangkat = $perangkat->get();

                $dataLaporan = $perangkat->map(function($dataPerangkat) use ($request, $dataShift){
                    
                    $range = create_time_range($dataShift->mulai, $dataShift->selesai, '1 hour', '24');

                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc');

                    if ($request->date) {
                        $date = $request->date;
                        $laporan = $laporan->whereDate('created_at', '=', date('Y-m-d', strtotime($date)));
                    }
                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $dataPerangkat->uuid);

                    $laporan = $laporan->get()->groupBy(function($date) {
                        return Carbon::parse($date->created_at)->format('H');
                    });


                    foreach($range as $time) {
                        foreach($laporan as $jam => $val) {
                            if((int)$jam == $time) {
                                foreach($val as $item) {
                                    if(is_int((int)$item->isian)) {
                                        $kalkulasi[(int)$jam] = (int)$item->isian;
                                    } else {
                                        $kalkulasi[(int)$jam] = 0;
                                    }
                                }
                            } else {
                                $kalkulasi[(int)$time] = 0;
                            }
                        }
                    }

                    $return['perangkat'] = $dataPerangkat->judul;
                    $return['perjam'] = $kalkulasi;

                    return $return;
                });

                $datanya['shift'] = $dataShift;
                $datanya['kalkulasi'] = $dataLaporan; 

                return $datanya;
            });

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
                'chartlaporan' => $chartLaporan,
                'shifthariini' => $shifthariini,
                'humidity' => $shift
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
