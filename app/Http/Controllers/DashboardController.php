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
use App\Models\LaporanRangeJam;
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
            // $decodeToken = parseJwt($this->request->header('Authorization'));
            // $uuid = $decodeToken->user->uuid;
            // $user = User::with('role', 'profile')->where('uuid', $uuid)->first();
            
            // if (!$user) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Pengguna tidak ditemukan',
            //         'code'    => 404,
            //     ]);
            // }

            $tanggalsekarang = '';
            $tanggalrequest =  '';
            $minusoneday = '';
            $plusoneday = '';

            $date = date('Y-m-d');
            $tanggalsekarang = date('Y-m-d');
            $minusoneday = date('Y-m-d', strtotime("-1 day", strtotime($tanggalsekarang)));
            $plusoneday = date('Y-m-d', strtotime("+1 day", strtotime($tanggalsekarang)));

            if ($request->date) {
                $date = date('Y-m-d', strtotime($request->date));
                $tanggalrequest = date('Y-m-d', strtotime($request->date));
                $minusoneday = date('Y-m-d', strtotime("-1 day", strtotime($tanggalrequest)));
                $plusoneday = date('Y-m-d', strtotime("+1 day", strtotime($tanggalrequest)));
            }

            $jumlaheos = 0; $jumlaheos = User::where('role', env('ROLE_EOS'))->count();
            $jumlahsupervisor = 0; $jumlahsupervisor = User::where('role', env('ROLE_SPV'))->count();
            $jumlahsuperadmin = 0; $jumlahsuperadmin = User::where('role', env('ROLE_SPA'))->count();
            $jumlahlaporanshift = 0; $jumlahlaporanshift = LaporanShift::whereDate('created_at', date('Y-m-d'))->count();

            $cctv = 0; 
            $cleaning = 0; 
            $facilities = 0; 

            $rangeA = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
            $rangeB = ['I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X'];

            // $cctv = Laporan::where('form_jenis', env('FORM_CCTV')); 
            // $cleaning = Laporan::where('form_jenis', env('FORM_CLEANING')); 
            // $facilities = Laporan::where('form_jenis', env('FORM_FACILITIES'));
            $cctvhari1 = 0;
            $cctvhari2 = 0;
            $cleaninghari1 = 0;
            $cleaninghari2 = 0;
            $facilitieshari1 = 0;
            $facilitieshari2 = 0;

            if(date('H') < 8) {
                $cctvhari1 = Laporan::where('form_jenis', env('FORM_CCTV'))->whereDate('created_at', '=', $minusoneday)->whereIn('range_jam_kode', $rangeB);
                $cctvhari2 = Laporan::where('form_jenis', env('FORM_CCTV'))->whereDate('created_at', '=', $minusoneday)->whereIn('range_jam_kode', $rangeA);
                $cleaninghari1 = Laporan::where('form_jenis', env('FORM_CLEANING'))->whereDate('created_at', '=', $minusoneday)->whereIn('range_jam_kode', $rangeB);
                $cleaninghari2 = Laporan::where('form_jenis', env('FORM_CLEANING'))->whereDate('created_at', '=', $minusoneday)->whereIn('range_jam_kode', $rangeA);
                $facilitieshari1 = Laporan::where('form_jenis', env('FORM_FACILITIES'))->whereDate('created_at', '=', $minusoneday)->whereIn('range_jam_kode', $rangeB);
                $facilitieshari2 = Laporan::where('form_jenis', env('FORM_FACILITIES'))->whereDate('created_at', '=', $minusoneday)->whereIn('range_jam_kode', $rangeA);
            } else if((date('H') >= 8) && (date('H') <= 23)) {
                if ($request->date) {
                    $cctvhari1 = Laporan::where('form_jenis', env('FORM_CCTV'))->whereDate('created_at', '=', $tanggalrequest)->whereIn('range_jam_kode', $rangeB);
                    $cctvhari2 = Laporan::where('form_jenis', env('FORM_CCTV'))->WhereDate('created_at', '=', $tanggalrequest)->whereIn('range_jam_kode', $rangeA);
                    $cleaninghari1 = Laporan::where('form_jenis', env('FORM_CLEANING'))->whereDate('created_at', '=', $tanggalrequest)->whereIn('range_jam_kode', $rangeB);
                    $cleaninghari2 = Laporan::where('form_jenis', env('FORM_CLEANING'))->WhereDate('created_at', '=', $tanggalrequest)->whereIn('range_jam_kode', $rangeA);
                    $facilitieshari1 = Laporan::where('form_jenis', env('FORM_FACILITIES'))->whereDate('created_at', '=', $tanggalrequest)->whereIn('range_jam_kode', $rangeB);
                    $facilitieshari2 = Laporan::where('form_jenis', env('FORM_FACILITIES'))->WhereDate('created_at', '=', $tanggalrequest)->whereIn('range_jam_kode', $rangeA);
                } else {
                    $cctvhari1 = Laporan::where('form_jenis', env('FORM_CCTV'))->whereDate('created_at', '=', $tanggalsekarang)->whereIn('range_jam_kode', $rangeB);
                    $cctvhari2 = Laporan::where('form_jenis', env('FORM_CCTV'))->WhereDate('created_at', '=', $tanggalsekarang)->whereIn('range_jam_kode', $rangeA);
                    $cleaninghari1 = Laporan::where('form_jenis', env('FORM_CLEANING'))->whereDate('created_at', '=', $tanggalsekarang)->whereIn('range_jam_kode', $rangeB);
                    $cleaninghari2 = Laporan::where('form_jenis', env('FORM_CLEANING'))->WhereDate('created_at', '=', $tanggalsekarang)->whereIn('range_jam_kode', $rangeA);
                    $facilitieshari1 = Laporan::where('form_jenis', env('FORM_FACILITIES'))->whereDate('created_at', '=', $tanggalsekarang)->whereIn('range_jam_kode', $rangeB);
                    $facilitieshari2 = Laporan::where('form_jenis', env('FORM_FACILITIES'))->WhereDate('created_at', '=', $tanggalsekarang)->whereIn('range_jam_kode', $rangeA);
                }
            }

            if($request->shift) {
                $sh = $request->shift;

                $cctvhari1 = $cctvhari1->whereHas('jadwal', function($dd) use ($sh) {
                    $dd->where('kode_shift', $sh);
                });
                $cctvhari2 = $cctvhari2->whereHas('jadwal', function($dd) use ($sh) {
                    $dd->where('kode_shift', $sh);
                });
                $cleaninghari1 = $cleaninghari1->whereHas('jadwal', function($dd) use ($sh) {
                    $dd->where('kode_shift', $sh);
                });
                $cleaninghari2 = $cleaninghari2->whereHas('jadwal', function($dd) use ($sh) {
                    $dd->where('kode_shift', $sh);
                });
                $facilitieshari1 = $facilitieshari1->whereHas('jadwal', function($dd) use ($sh) {
                    $dd->where('kode_shift', $sh);
                });
                $facilitieshari2 = $facilitieshari2->whereHas('jadwal', function($dd) use ($sh) {
                    $dd->where('kode_shift', $sh);
                });
            }
            
            $cctv = (int)$cctvhari1->count()+(int)$cctvhari2->count();
            $cleaning = (int)$cleaninghari1->count()+(int)$cleaninghari2->count();
            $facilities = (int)$facilitieshari1->count()+(int)$facilitieshari2->count();

            // $cctv = ((int)$cctv->count()/24)*100;
            // $cleaning = ((int)$cleaning->count()/24)*100; 
            // $facilities = ((int)$facilities->count()/24)*100; 
            
            // $cctv = number_format((double)$cctv, 2, '.', '');
            // $cleaning = number_format((double)$cleaning, 2, '.', '');
            // $facilities = number_format((double)$facilities, 2, '.', '');

            $shifthariini = [];
            $shifthariini = Jadwal::with('user', 'shift')->whereNotIn('kode_shift', ['L'])->whereDate('tanggal', date('Y-m-d'))->whereHas('user')->get();

            $shift = Shift::orderBy('mulai', 'asc')->whereNotIn('kode', ['L']);

            if ($request->shift) {
                $shift = $shift->where('kode', $request->shift);
            } 

            $shift = $shift->get();

            $perangkat  = [];
            $perangkat  = FormIsian::orderBy('judul', 'asc');
            $perangkat = $perangkat->where('form_jenis', env('FORM_FACILITIES'));
            $perangkat = $perangkat->where('kategori', 'PAC');
            $perangkat = $perangkat->where('tipe', 'LIKE', '%DROPDOWN%');
            $perangkat = $perangkat->get();

            $humidity = [];
            $humidity = $shift->map(function ($dataShift) use ($request, $perangkat){
                $datanya = [];

                $dataLaporan = $perangkat->map(function($dataPerangkat) use ($request, $dataShift){
                    $range = [];
                    $laporan = [];
                    $rangejam = [];
                    $kalkulasi = [];

                    // $range = create_time_range($dataShift->mulai, $dataShift->selesai, '1 hour', '24');
                    // $rangejam = create_jam_range($dataShift->mulai, $dataShift->selesai, '1 hour', '24');
                    // ambil dari range jam
                    $range = LaporanRangeJam::where('kode_shift', '!=', '');
                    $rangejam = $range;

                    $laporan = LaporanIsi::with('laporan')->whereHas('laporan');

                    if ($request->date) {
                        $date = $request->date;
                        $laporan = $laporan->whereDate('created_at', '=', date('Y-m-d', strtotime($date)));
                    }

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });

                        $range = $range->where('kode_shift', $sh);
                        $rangejam = $rangejam->where('kode_shift', $sh);
                    }

                    $range = $range->get();
                    $rangejam = $rangejam->get();

                    $laporan = $laporan->where('form_isian_id', $dataPerangkat->uuid);
                    $laporan = $laporan->orderBy('created_at', 'asc');
                    $laporan = $laporan->get();
                    // ->groupBy(function($date) {
                    //     return Carbon::parse($date->created_at)->format('H');
                    // });

                    foreach($range as $time) {
                        $kalkulasi[$time->time] = 0;
                        if(!empty($laporan) && count($laporan)) {
                            foreach($laporan as $val) {
                                if($val->laporan->range_jam_kode == $time->kode) {
                                    // foreach($val as $item) {
                                        if(is_numeric($val->isian)) {
                                            $kalkulasi[$time->time] = (int)$val->isian;
                                        }
                                    // }
                                }
                            }
                        }
                    }

                    $datarangejam = [];
                    foreach($rangejam as $rj) {
                        array_push($datarangejam, 'Jam '.date('H', strtotime($rj->time)));
                    }

                    $return['perangkat'] = $dataPerangkat->judul;
                    $return['perjam'] = $kalkulasi;
                    $return['range'] = $range;
                    $return['rangejam'] = $rangejam;
                    $return['jam'] = $datarangejam;

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
            }

            $range = 0;
            if ($request->date) {
                $range = getrangedaymonth(date('m', strtotime($request->date)), date('Y'));
            } else {
                $range = getrangedaymonth(date('m'), date('Y'));
            }

            $humiditybulan = $shift->map(function ($dataShift) use ($request, $range, $perangkat){
                $datanya = [];

                $dataLaporan = $perangkat->map(function($dataPerangkat) use ($request, $range, $dataShift){
                    $kalkulasi = [];
                    $laporan = [];
                    $laporan = LaporanIsi::whereHas('laporan')->whereYear('created_at', date('Y'));
                    
                    if ($request->date) {
                        // $laporan = $laporan->whereMonth('created_at', date('m', strtotime($request->date)));
                        $laporan = $laporan->whereHas('laporan', function ($qq) use ($request) {
                            $qq->whereMonth('created_at', '=', date('m', strtotime($request->date)));
                        });
                    } else {
                        // $laporan = $laporan->whereMonth('created_at', date('m'));
                        $laporan = $laporan->whereHas('laporan', function ($qq) {
                            $qq->whereMonth('created_at', '=', date('m'));
                        });
                    }

                    if ($request->shift) {
                        $sh = $request->shift;
                        $laporan = $laporan->whereHas('laporan.jadwal.shift', function ($q) use ($sh) {
                            $q->where('kode', $sh);
                        });
                    }
                    
                    $laporan = $laporan->where('form_isian_id', $dataPerangkat->uuid);
                    $laporan = $laporan->orderBy('created_at', 'asc');
                    $laporan = $laporan->get();
                    $laporan = $laporan->groupBy(function($date) {
                        return Carbon::parse($date->created_at)->format('d');
                    });
                    
                    $namahari = [];
                    foreach($range as $id => $day) {
                        $kalkulasi[(int)$day] = 0;
                        if(!empty($laporan) && count($laporan)) {
                            foreach($laporan as $dd => $val) {
                                if((int)$day == (int)$dd) {
                                    foreach($val as $item) {
                                        if(is_numeric($item->isian)) {
                                            $kalkulasi[(int)$day] += (int)$item->isian;
                                        }
                                    }
                                    $kalkulasi[(int)$day] = ((int)$kalkulasi[(int)$day]/(int)count($laporan));
                                    $kalkulasi[(int)$day] = number_format((double)$kalkulasi[(int)$day], 2, '.', '');
                                }
                            }
                        }

                        $tglnya = 0;
                        if ($request->date) {
                            $bulanrequest = 0;
                            $bulanrequest = date('Y-m', strtotime($request->date));
                            $tglnya = date('Y-m-'.(int)$day, strtotime($bulanrequest));
                        } else {
                            $tglnya = date('Y-m-'.(int)$day);
                        }
                        array_push($namahari, getnameofday($tglnya));
                    }

                    $return['perangkat'] = $dataPerangkat->judul;
                    $return['perhari'] = $kalkulasi;
                    $return['hari'] = $namahari;

                    return $return;
                });

                $datanya['shift'] = $dataShift;
                $datanya['kalkulasi'] = $dataLaporan; 

                return $datanya;
            });

            $humidityperbulan = $humiditybulan;

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
