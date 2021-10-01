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
        $this->bulan = array(
            '1' => 'Januari',
            '2' => 'Februari',
            '3' => 'Maret',
            '4' => 'April',
            '5' => 'Mei',
            '6' => 'Juni',
            '7' => 'Juli',
            '8' => 'Agustus',
            '9' => 'September',
            '10' => 'Oktober',
            '11' => 'November',
            '12' => 'Desember',
        );
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

            $cctv = 0; 
            $cleaning = 0; 
            $facilities = 0; 

            $cctv = Laporan::where('form_jenis', env('FORM_CCTV')); 
            $cleaning = Laporan::where('form_jenis', env('FORM_CLEANING')); 
            $facilities = Laporan::where('form_jenis', env('FORM_FACILITIES'));

            $date = date('Y-m-d');
            if ($request->date) {
                $date = date('Y-m-d', strtotime($request->date));
            }

            if ($request->date) {
                $cctv = $cctv->whereDate('created_at', '=', date('Y-m-d', strtotime($request->date)));
                $cleaning = $cleaning->whereDate('created_at', '=', date('Y-m-d', strtotime($request->date)));
                $facilities = $facilities->whereDate('created_at', '=', date('Y-m-d', strtotime($request->date)));
            } else {
                $cctv = $cctv->whereDate('created_at', date('Y-m-d'));
                $cleaning = $cleaning->whereDate('created_at', date('Y-m-d'));
                $facilities = $facilities->whereDate('created_at', date('Y-m-d'));
            }

            
            $cctv = ((int)$cctv->count()/24)*100; 
            $cleaning = ((int)$cleaning->count()/24)*100; 
            $facilities = ((int)$facilities->count()/24)*100; 
            
            $cctv = number_format((double)$cctv, 2, '.', '');
            $cleaning = number_format((double)$cleaning, 2, '.', '');
            $facilities = number_format((double)$facilities, 2, '.', '');

            // $chartLaporan = [];
            // foreach(User::where('role', env('ROLE_EOS'))->get() as $item => $val) {
            //     $countLaporanForm = Laporan::with('user')->where('user_id', $val->uuid)->whereMonth('created_at', date('m'))->count();
            //     $countLaporanShift = LaporanShift::with('user')->where('user_id', $val->uuid)->whereMonth('created_at', date('m'))->count();

            //     $data['user'] = $val->nama;
            //     $data['laporanform'] = $countLaporanForm;
            //     $data['laporanshift'] = $countLaporanShift;

            //     array_push($chartLaporan, $data);
            // }

            $shifthariini = [];
            $shifthariini = Jadwal::with('user', 'shift')->whereNotIn('kode_shift', ['L'])->whereDate('tanggal', date('Y-m-d'))->get();

            $shift = Shift::orderBy('mulai', 'asc')->whereNotIn('kode', ['L']);

            if ($request->shift) {
                $shift = $shift->where('kode', $request->shift);
            } 

            $shift = $shift->get();

            $humidity = [];
            $humidity = $shift->map(function ($dataShift) use ($request){
                $datanya = [];

                $perangkat  = [];
                $perangkat  = FormIsian::orderBy('judul', 'asc');
                $perangkat = $perangkat->where('form_jenis', env('FORM_FACILITIES'));
                $perangkat = $perangkat->where('kategori', 'PAC');
                $perangkat = $perangkat->where('tipe', 'LIKE', '%ISIAN%');
                $perangkat = $perangkat->get();

                $dataLaporan = $perangkat->map(function($dataPerangkat) use ($request, $dataShift){
                    $kalkulasi = [];
                    $range = [];
                    $rangejam = [];
                    $range = create_time_range($dataShift->mulai, $dataShift->selesai, '1 hour', '24');
                    $rangejam = create_jam_range($dataShift->mulai, $dataShift->selesai, '1 hour', '24');

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
                        if(!empty($laporan) && count($laporan)) {
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
                        } else {
                            $kalkulasi[(int)$time] = 0;
                        }
                    }

                    $return['perangkat'] = $dataPerangkat->judul;
                    $return['perjam'] = $kalkulasi;
                    $return['jam'] = $rangejam;

                    return $return;
                });

                $datanya['shift'] = $dataShift;
                $datanya['kalkulasi'] = $dataLaporan; 

                return $datanya;
            });

            
            $whereUPS = array(
                'form_jenis' => env('FORM_FACILITIES'),
                'kategori' => 'UPS'
            );
            $whereUPSAPC = array(
                'form_jenis' => env('FORM_FACILITIES'),
                'kategori' => 'UPS-APC'
            );

            $whereUPSLIKE = "tipe LIKE '%ISIAN%'";
            $whereUPSAPCLIKE = "tipe LIKE '%DROPDOWN%'";

            $where_ups1_ampere = "judul LIKE 'UPS 1 Ampere (A) (R)%' OR judul LIKE 'UPS 1 Ampere (A) (S)%' OR judul LIKE 'UPS 1 Ampere (A) (T)%'";
            $where_ups1_voltage = "judul LIKE 'UPS 1 Voltage (Vac) (R)%' OR judul LIKE 'UPS 1 Voltage (Vac) (S)%' OR judul LIKE 'UPS 1 Voltage (Vac) (T)%'";
            $where_ups1_loadlevel = "judul LIKE 'UPS 1 Load Level (%) (R)%' OR judul LIKE 'UPS 1 Load Level (%) (S)%' OR judul LIKE 'UPS 1 Load Level (%) (T)%'";

            $where_ups2_ampere = "judul LIKE 'UPS 2 Ampere (A) (R)%' OR judul LIKE 'UPS 2 Ampere (A) (S)%' OR judul LIKE 'UPS 2 Ampere (A) (T)%'";
            $where_ups2_voltage = "judul LIKE 'UPS 2 Voltage (Vac) (R)%' OR judul LIKE 'UPS 2 Voltage (Vac) (S)%' OR judul LIKE 'UPS 2 Voltage (Vac) (T)%'";
            $where_ups2_loadlevel = "judul LIKE 'UPS 2 Load Level (%) (R)%' OR judul LIKE 'UPS 2 Load Level (%) (S)%' OR judul LIKE 'UPS 2 Load Level (%) (T)%'";

            $where_upsapc_ampere = "judul LIKE 'Iout (A) (R)%' OR judul LIKE 'Iout (A) (S)%' OR judul LIKE 'Iout (A) (T)%'";
            $where_upsapc_voltage = "judul LIKE 'Vout (Vac) (R)%' OR judul LIKE 'Vout (Vac) (S)%' OR judul LIKE 'Vout (Vac) (T)%'";
            $where_upsapc_runtime = "judul LIKE 'Runtime (min) (R)%' OR judul LIKE 'Runtime (min) (S)%' OR judul LIKE 'Runtime (min) (T)%'";

            $ups1_ampere  = FormIsian::where($whereUPS)->whereRaw($whereUPSLIKE)->whereRaw($where_ups1_ampere)->get();
            $ups1_voltage  = FormIsian::where($whereUPS)->whereRaw($whereUPSLIKE)->whereRaw($where_ups1_voltage)->get();
            $ups1_loadlevel  = FormIsian::where($whereUPS)->whereRaw($whereUPSLIKE)->whereRaw($where_ups1_loadlevel)->get();

            $ups2_ampere  = FormIsian::where($whereUPS)->whereRaw($whereUPSLIKE)->whereRaw($where_ups2_ampere)->get();
            $ups2_voltage  = FormIsian::where($whereUPS)->whereRaw($whereUPSLIKE)->whereRaw($where_ups2_voltage)->get();
            $ups2_loadlevel  = FormIsian::where($whereUPS)->whereRaw($whereUPSLIKE)->whereRaw($where_ups2_loadlevel)->get();

            $upsapc_ampere  = FormIsian::where($whereUPSAPC)->whereRaw($whereUPSAPCLIKE)->whereRaw($where_upsapc_ampere)->get();
            $upsapc_voltage  = FormIsian::where($whereUPSAPC)->whereRaw($whereUPSAPCLIKE)->whereRaw($where_upsapc_voltage)->get();
            $upsapc_runtime  = FormIsian::where($whereUPSAPC)->whereRaw($whereUPSAPCLIKE)->whereRaw($where_upsapc_runtime)->get();

            $humidityperbulan = [];
            for($i=1;$i<=12;$i++) {
                $range = 0;
                $range = getrangedaymonth($i, date('Y'));

                $kalkulasi_ups1_ampere[$this->bulan[$i]] = 0;
                foreach($ups1_ampere as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_ups1_ampere[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_ups1_ampere[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_ups1_ampere[$this->bulan[$i]] = ((int)$kalkulasi_ups1_ampere[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_ups1_ampere[$this->bulan[$i]] = number_format((double)$kalkulasi_ups1_ampere[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_ups1_ampere[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_ups2_ampere[$this->bulan[$i]] = 0;
                foreach($ups2_ampere as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_ups2_ampere[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_ups2_ampere[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_ups2_ampere[$this->bulan[$i]] = ((int)$kalkulasi_ups2_ampere[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_ups2_ampere[$this->bulan[$i]] = number_format((double)$kalkulasi_ups2_ampere[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_ups2_ampere[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_upsapc_ampere[$this->bulan[$i]] = 0;
                foreach($upsapc_ampere as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_upsapc_ampere[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_upsapc_ampere[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_upsapc_ampere[$this->bulan[$i]] = ((int)$kalkulasi_upsapc_ampere[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_upsapc_ampere[$this->bulan[$i]] = number_format((double)$kalkulasi_upsapc_ampere[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_upsapc_ampere[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_ups1_voltage[$this->bulan[$i]] = 0;
                foreach($ups1_voltage as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_ups1_voltage[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_ups1_voltage[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_ups1_voltage[$this->bulan[$i]] = ((int)$kalkulasi_ups1_voltage[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_ups1_voltage[$this->bulan[$i]] = number_format((double)$kalkulasi_ups1_voltage[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_ups1_voltage[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_ups2_voltage[$this->bulan[$i]] = 0;
                foreach($ups2_voltage as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_ups2_voltage[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_ups2_voltage[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_ups2_voltage[$this->bulan[$i]] = ((int)$kalkulasi_ups2_voltage[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_ups2_voltage[$this->bulan[$i]] = number_format((double)$kalkulasi_ups2_voltage[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_ups2_voltage[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_upsapc_voltage[$this->bulan[$i]] = 0;
                foreach($upsapc_voltage as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_upsapc_voltage[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_upsapc_voltage[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_upsapc_voltage[$this->bulan[$i]] = ((int)$kalkulasi_upsapc_voltage[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_upsapc_voltage[$this->bulan[$i]] = number_format((double)$kalkulasi_upsapc_voltage[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_upsapc_voltage[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_ups1_loadlevel[$this->bulan[$i]] = 0;
                foreach($ups1_loadlevel as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_ups1_loadlevel[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_ups1_loadlevel[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_ups1_loadlevel[$this->bulan[$i]] = ((int)$kalkulasi_ups1_loadlevel[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_ups1_loadlevel[$this->bulan[$i]] = number_format((double)$kalkulasi_ups1_loadlevel[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_ups1_loadlevel[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_ups2_loadlevel[$this->bulan[$i]] = 0;
                foreach($ups2_loadlevel as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_ups2_loadlevel[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_ups2_loadlevel[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_ups2_loadlevel[$this->bulan[$i]] = ((int)$kalkulasi_ups2_loadlevel[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_ups2_loadlevel[$this->bulan[$i]] = number_format((double)$kalkulasi_ups2_loadlevel[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_ups2_loadlevel[$this->bulan[$i]] = 0;
                    }
                }

                $kalkulasi_upsapc_runtime[$this->bulan[$i]] = 0;
                foreach($upsapc_runtime as $item) {
                    $laporan = [];
                    $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }

                    $laporan = $laporan->where('form_isian_id', $item->uuid);
                    $laporan = $laporan->get();

                    if(!empty($laporan) && count($laporan)) {
                        foreach($laporan as $ll) {
                            if(is_int((int)$ll->isian)) {
                                $kalkulasi_upsapc_runtime[$this->bulan[$i]] += (int)$ll->isian;
                            } else {
                                $kalkulasi_upsapc_runtime[$this->bulan[$i]] += 0;
                            }
                            
                        }

                        $kalkulasi_upsapc_runtime[$this->bulan[$i]] = ((int)$kalkulasi_upsapc_runtime[$this->bulan[$i]] / (int)count($laporan));
                        $kalkulasi_upsapc_runtime[$this->bulan[$i]] = number_format((double)$kalkulasi_upsapc_runtime[$this->bulan[$i]], 2, '.', '');
                    } else {
                        $kalkulasi_upsapc_runtime[$this->bulan[$i]] = 0;
                    }
                }

                $humiditybulan = $shift->map(function ($dataShift) use ($request, $i, $range){
                    $datanya = [];

                    $perangkat  = [];
                    $perangkat  = FormIsian::orderBy('judul', 'asc');
                    $perangkat = $perangkat->where('form_jenis', env('FORM_FACILITIES'));
                    $perangkat = $perangkat->where('kategori', 'PAC');
                    $perangkat = $perangkat->where('tipe', 'LIKE', '%ISIAN%');
                    $perangkat = $perangkat->get();

                    $dataLaporan = $perangkat->map(function($dataPerangkat) use ($request, $i, $range, $dataShift){
                        $kalkulasi = [];
                        $laporan = [];
                        $laporan = LaporanIsi::orderBy('created_at', 'asc')->whereMonth('created_at', $i)->whereYear('created_at', date('Y'));

                        if ($request->shift) {
                            $sh = $request->shift;
                            $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                                $q->where('kode', $sh);
                            });
                        }

                        $laporan = $laporan->where('form_isian_id', $dataPerangkat->uuid);

                        $laporan = $laporan->get()->groupBy(function($date) {
                            return Carbon::parse($date->created_at)->format('d');
                        });

                        foreach($range as $day) {
                            if(!empty($laporan) && count($laporan)) {
                                foreach($laporan as $dd => $val) {
                                    if((int)$dd == $day) {
                                        $kalkulasi[(int)$dd] = 0;
                                        foreach($val as $item) {
                                            if(is_int((int)$item->isian)) {
                                                $kalkulasi[(int)$dd] += (int)$item->isian;
                                            } else {
                                                $kalkulasi[(int)$dd] += 0;
                                            }
                                        }
                                        $kalkulasi[(int)$day] = ((int)$kalkulasi[(int)$day]/(int)count($laporan));
                                        $kalkulasi[(int)$day] = number_format((double)$kalkulasi[(int)$day], 2, '.', '');
                                    } else {
                                        $kalkulasi[(int)$day] = 0;
                                    }
                                }
                            } else {
                                $kalkulasi[(int)$day] = 0;
                            }
                        }

                        $return['perangkat'] = $dataPerangkat->judul;
                        $return['perhari'] = $kalkulasi;
                        $return['hari'] = $range;

                        return $return;
                    });

                    $datanya['shift'] = $dataShift;
                    $datanya['kalkulasi'] = $dataLaporan; 

                    return $datanya;
                });

                $humidityperbulan[$this->bulan[$i]] = $humiditybulan;
            }

            $data = [
                'tanggal' => $date,
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
                'grafikbulanan' =>  [
                    'loadampere' => array(
                        'ups1' => $kalkulasi_ups1_ampere,
                        'ups2' => $kalkulasi_ups2_ampere,
                        'ups-apc' => $kalkulasi_upsapc_ampere
                    ),
                    'system' => array(
                        'ups1' => $kalkulasi_ups1_voltage,
                        'ups2' => $kalkulasi_ups2_voltage,
                        'ups-apc' => $kalkulasi_upsapc_voltage
                    ),
                    'persenload' => array(
                        'ups1' => $kalkulasi_ups1_loadlevel,
                        'ups2' => $kalkulasi_ups2_loadlevel,
                        'ups-apc' => $kalkulasi_upsapc_runtime
                    ),
                ],
                // 'chartlaporan' => $chartLaporan,
                'shifthariini' => $shifthariini,
                'humidity' => $humidity,
                'humidityperbulan' => $humidityperbulan
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
