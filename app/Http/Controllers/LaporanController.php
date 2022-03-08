<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Informasi;
use App\Models\InformasiUser;
use App\Models\Jadwal;
use App\Models\Laporan;
use App\Models\Shift;
use App\Models\LaporanIsi;
use App\Models\LaporanShift;
use App\Models\LaporanDikerjakan;
use App\Models\LaporanCetakApproval;
use App\Models\LaporanRangeJam;
use App\Models\FormIsian;
use App\Models\FormJenis;
use Illuminate\Support\Facades\DB;
use GrahamCampbell\Flysystem\Facades\Flysystem;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LaporanController extends Controller
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

    public function getLaporanFct(Request $request)
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
                $shift = null;
                $catatan = null;
                $approval = null;

                $shift = Shift::orderBy('mulai', 'asc')->where('created_at', '!=', null);
                      
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal.shift');
                
                $approval = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'FCT');

                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    // $catatan = $catatan->whereDate('created_at', '=', $date);
                    $catatan = $catatan->whereHas('jadwal', function ($qq) use ($date) {
                        $qq->whereDate('tanggal', '=', $date);
                    });
                    $approval = $approval->whereDate('tanggal', '=', $date);
                } else {
                    $approval = $approval->whereDate('tanggal', '=', date('Y-m-d'));
                }
                if ($request->nama) {
                    $nama = $request->nama;
                    $catatan = $catatan->whereHas('user', function ($q) use ($nama) {
                        $q->where('nama', 'ilike', '%'. $nama .'%');
                    });
                }
                if ($request->shift) {
                    $sh = $request->shift;
                    $shift = $shift->where('kode', $sh);
                    $catatan = $catatan->whereHas('jadwal.shift', function ($q) use ($sh) {
                        $q->where('kode', $sh);
                    });
                }

                $shift = $shift->get();
                $catatan = $catatan->orderBy('created_at', 'asc')->get();
                $approval = $approval->first();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'FCT')->orderBy('kategori', 'asc')->orderBy('judul', 'asc')->get();
                    $a = $cek->map(function ($cek) use ($request, $shift) {
                        $isi= LaporanIsi::where('form_isian_id', $cek->uuid)->with('laporan.jadwal.shift','laporan.user','pilihan')->whereHas('laporan.jadwal.shift', function ($q) use ($shift){
                            $q->where('nama', $shift->nama);
                        });
                            
                        if ($request->date) {
                            $date = $request->date;
                            $isi = $isi->whereHas('laporan.jadwal', function ($q) use ($date) {
                                $q->whereDate('tanggal', $date);
                            });
                        }   

                        if ($request->nama) {
                            $nama = $request->nama;
                            $isi = $isi->whereHas('laporan.user', function ($q) use ($nama) {
                                $q->where('nama', 'ilike', '%'. $nama .'%');
                            });
                        }                

                        $isi = $isi->orderBy('created_at', 'asc')->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama ?? '';
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $rangejam = $isi->map(function ($isi) {
                            $jamawal = 0;
                            $jamakhir = 0;
                            $kode = $isi->laporan->range_jam_kode;
                            $time = LaporanRangeJam::where('kode', $kode)->first();
                            if($time) {
                                $jamawal = Carbon::parse($time->time)->format('H:00');
                                $plus1jam = Carbon::parse($jamawal)->addHour(1);
                                $jamakhir = $plus1jam->format('H:00');
                            }
                            
                            // return $jamawal.'-'.$jamakhir;
                            return $jamawal;
                        });

                        $warna = $isi->map(function ($isi) use ($cek) {
                            // return $isi->warna = $isi->laporan->user->color ?? '';
                            if(strpos($cek->judul, 'PAC') !== false) {
                                if(($isi->isian != '') && ($isi->isian != null)) {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                }
                            } else {
                                if ($isi->pilihan){
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                }
                            }
                        });
                        $isi->map(function ($isi) use ($cek) {
                            if(strpos($cek->judul, 'PAC') !== false) {
                                if(($isi->isian != '') && ($isi->isian != null)) {
                                    return $isi->keadaan = $isi->isian ?? '';
                                } else {
                                    return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                                }
                            } else {
                                if ($isi->pilihan){
                                    return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                                } else {
                                    return $isi->keadaan = $isi->isian ?? '';
                                }
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna, $cek->rangejam = $rangejam];
                    });

                    $jadwal = Jadwal::with('user')->whereHas('user')->where('kode_shift', $shift->kode);
                    if ($request->date) {
                        $date = $request->date;
                        $jadwal = $jadwal->whereDate('tanggal', '=', $date);
                    }
                    $jadwal = $jadwal->get();

                    $eosNM = $jadwal->map(function ($jj) use ($request, $shift) {
                        return $jj->eos = $jj->user->nama ?? '';
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2], $shift->range = $a[3][3], $shift->daftareosyangshift = $eosNM];
                });

                $approvalAll = null;
                $approvalAll = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'ALL');

                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', $date);
                } else {
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', date('Y-m-d'));
                }

                $approvalAll = $approvalAll->first();
               
                if (empty($shift)) {
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
                        'data'  => $shift,
                        'approval' => $approval,
                        'approval_all' => $approvalAll,
                        'catatan_shift' => $catatan
                    ]);
                }

            } catch (\Throwable $th) {
                return writeLog($th->getMessage());
            }
        }
        
    }

    public function getLaporanCln(Request $request)
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
                $shift = null;
                $catatan = null;
                $approval = null;
                
                $shift = Shift::orderBy('mulai', 'asc')->where('created_at', '!=', null);
                      
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal.shift');

                $approval = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'CLN');
                
                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    // $catatan = $catatan->whereDate('created_at', '=', $date);
                    $catatan = $catatan->whereHas('jadwal', function ($qq) use ($date) {
                        $qq->whereDate('tanggal', '=', $date);
                    });
                    $approval = $approval->whereDate('tanggal', '=', $date);
                } else {
                    $approval = $approval->whereDate('tanggal', '=', date('Y-m-d'));
                }
                if ($request->nama) {
                    $nama = $request->nama;
                    $catatan = $catatan->whereHas('user', function ($q) use ($nama) {
                        $q->where('nama', 'ilike', '%'. $nama .'%');
                    });
                }
                if ($request->shift) {
                    $sh = $request->shift;
                    $shift = $shift->where('kode', $sh);
                    $catatan = $catatan->whereHas('jadwal.shift', function ($q) use ($sh) {
                        $q->where('kode', $sh);
                    });
                }

                $shift = $shift->get();
                $catatan = $catatan->orderBy('created_at', 'asc')->get();
                $approval = $approval->first();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'CLN')->orderBy('kategori', 'asc')->orderBy('judul', 'asc')->get();
                    $a = $cek->map(function ($cek) use ($request, $shift) {
                        $isi= LaporanIsi::where('form_isian_id', $cek->uuid)->with('laporan.jadwal.shift','laporan.user','pilihan')->whereHas('laporan.jadwal.shift', function ($q) use ($shift){
                            $q->where('nama', $shift->nama);
                        });
                            
                        if ($request->date) {
                            $date = $request->date;
                            $isi = $isi->whereHas('laporan.jadwal', function ($q) use ($date) {
                                $q->whereDate('tanggal', $date);
                            });
                        }   

                        if ($request->nama) {
                            $nama = $request->nama;
                            $isi = $isi->whereHas('laporan.user', function ($q) use ($nama) {
                                $q->where('nama', 'ilike', '%'. $nama .'%');
                            });
                        }                

                        $isi = $isi->orderBy('created_at', 'asc')->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama ?? '';
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $rangejam = $isi->map(function ($isi) {
                            $jamawal = 0;
                            $jamakhir = 0;
                            $kode = $isi->laporan->range_jam_kode;
                            $time = LaporanRangeJam::where('kode', $kode)->first();
                            if($time) {
                                $jamawal = Carbon::parse($time->time)->format('H:00');
                                $plus1jam = Carbon::parse($jamawal)->addHour(1);
                                $jamakhir = $plus1jam->format('H:00');
                            }
                            
                            // return $jamawal.'-'.$jamakhir;
                            return $jamawal;
                        });

                        $warna = $isi->map(function ($isi) use ($cek) {
                            // return $isi->warna = $isi->laporan->user->color ?? '';
                            if($cek->kategori == 'PAC') {
                                return $isi->warna = color_value($isi->isian ?? '');
                            } else {
                                if ($isi->pilihan){
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                }
                            }
                        });
                        $isi->map(function ($isi) {
                            if ($isi->pilihan){
                                return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                            } else {
                                return $isi->keadaan = $isi->isian ?? '';
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna, $cek->rangejam = $rangejam];
                    });

                    $jadwal = Jadwal::with('user')->whereHas('user')->where('kode_shift', $shift->kode);
                    if ($request->date) {
                        $date = $request->date;
                        $jadwal = $jadwal->whereDate('tanggal', '=', $date);
                    }
                    $jadwal = $jadwal->get();

                    $eosNM = $jadwal->map(function ($jj) use ($request, $shift) {
                        return $jj->eos = $jj->user->nama ?? '';
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2], $shift->range = $a[3][3], $shift->daftareosyangshift = $eosNM];
                });

                $approvalAll = null;
                $approvalAll = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'ALL');

                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', $date);
                } else {
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', date('Y-m-d'));
                }

                $approvalAll = $approvalAll->first();
               
                if (empty($shift)) {
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
                        'data'  => $shift,
                        'approval' => $approval,
                        'approval_all' => $approvalAll,
                        'catatan_shift' => $catatan
                    ]);
                }

            } catch (\Throwable $th) {
                return writeLog($th->getMessage());
            }
        }
        
    }

    public function getLaporanCctv(Request $request)
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
                $shift = null;
                $catatan = null;
                $approval = null;

                $shift = Shift::orderBy('mulai', 'asc')->where('created_at', '!=', null);
                      
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal')->whereHas('jadwal.shift');

                $approval = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'CCTV');
                
                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    // $catatan = $catatan->whereDate('created_at', '=', $date);
                    $catatan = $catatan->whereHas('jadwal', function ($qq) use ($date) {
                        $qq->whereDate('tanggal', '=', $date);
                    });
                    $approval = $approval->whereDate('tanggal', '=', $date);
                } else {
                    $approval = $approval->whereDate('tanggal', '=', date('Y-m-d'));
                }
                if ($request->nama) {
                    $nama = $request->nama;
                    $catatan = $catatan->whereHas('user', function ($q) use ($nama) {
                        $q->where('nama', 'ilike', '%'. $nama .'%');
                    });
                }
                if ($request->shift) {
                    $sh = $request->shift;
                    $shift = $shift->where('kode', $sh);
                    $catatan = $catatan->whereHas('jadwal.shift', function ($q) use ($sh) {
                        $q->where('kode', $sh);
                    });
                }

                $shift = $shift->get();
                $catatan = $catatan->orderBy('created_at', 'asc')->get();
                $approval = $approval->first();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'CCTV')->orderBy('kategori', 'asc')->orderBy('judul', 'asc')->get();
                    $a = $cek->map(function ($cek) use ($request, $shift) {
                        $isi= LaporanIsi::where('form_isian_id', $cek->uuid)->with('laporan.jadwal.shift','laporan.user','pilihan')->whereHas('laporan.jadwal.shift', function ($q) use ($shift){
                            $q->where('nama', $shift->nama);
                        });
                            
                        if ($request->date) {
                            $date = $request->date;
                            $isi = $isi->whereHas('laporan.jadwal', function ($q) use ($date) {
                                $q->whereDate('tanggal', $date);
                            });
                        }   

                        if ($request->nama) {
                            $nama = $request->nama;
                            $isi = $isi->whereHas('laporan.user', function ($q) use ($nama) {
                                $q->where('nama', 'ilike', '%'. $nama .'%');
                            });
                        }                

                        $isi = $isi->orderBy('created_at', 'asc')->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama ?? '';
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $rangejam = $isi->map(function ($isi) {
                            $jamawal = 0;
                            $jamakhir = 0;
                            $kode = $isi->laporan->range_jam_kode;
                            $time = LaporanRangeJam::where('kode', $kode)->first();
                            if($time) {
                                $jamawal = Carbon::parse($time->time)->format('H:00');
                                $plus1jam = Carbon::parse($jamawal)->addHour(1);
                                $jamakhir = $plus1jam->format('H:00');
                            }
                            
                            // return $jamawal.'-'.$jamakhir;
                            return $jamawal;
                        });

                        $warna = $isi->map(function ($isi) use ($cek) {
                            // return $isi->warna = $isi->laporan->user->color ?? '';
                            if($cek->kategori == 'PAC') {
                                return $isi->warna = color_value($isi->isian ?? '');
                            } else {
                                if ($isi->pilihan){
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                }
                            }
                        });
                        $isi->map(function ($isi) {
                            if ($isi->pilihan){
                                return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                            } else {
                                return $isi->keadaan = $isi->isian ?? '';
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna, $cek->rangejam = 
                        $rangejam];
                    });

                    $jadwal = Jadwal::with('user')->whereHas('user')->where('kode_shift', $shift->kode);
                    if ($request->date) {
                        $date = $request->date;
                        $jadwal = $jadwal->whereDate('tanggal', '=', $date);
                    }
                    $jadwal = $jadwal->get();

                    $eosNM = $jadwal->map(function ($jj) use ($request, $shift) {
                        return $jj->eos = $jj->user->nama ?? '';
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2], $shift->range = $a[3][3], $shift->daftareosyangshift = $eosNM];
                });

                $approvalAll = null;
                $approvalAll = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'ALL');

                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', $date);
                } else {
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', date('Y-m-d'));
                }

                $approvalAll = $approvalAll->first();
               
                if (empty($shift)) {
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
                        'data'  => $shift,
                        'approval' => $approval,
                        'approval_all' => $approvalAll,
                        'catatan_shift' => $catatan
                    ]);
                }

            } catch (\Throwable $th) {
                return writeLog($th->getMessage());
            }
        }
        
    }

    public function getLaporanAll(Request $request)
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
                $fasilitas=collect();
                $cleaning=collect();
                $cctv=collect();
                
                //Fasilitas
                $shift = null;
                $catatan = null;
                $approval = null;

                $shift = Shift::orderBy('mulai', 'asc')->where('created_at', '!=', null);
                      
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal.shift');
                
                $approval = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'FCT');

                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    // $catatan = $catatan->whereDate('created_at', '=', $date);
                    $catatan = $catatan->whereHas('jadwal', function ($qq) use ($date) {
                        $qq->whereDate('tanggal', '=', $date);
                    });
                    $approval = $approval->whereDate('tanggal', '=', $date);
                } else {
                    $approval = $approval->whereDate('tanggal', '=', date('Y-m-d'));
                }
                if ($request->nama) {
                    $nama = $request->nama;
                    $catatan = $catatan->whereHas('user', function ($q) use ($nama) {
                        $q->where('nama', 'ilike', '%'. $nama .'%');
                    });
                }
                if ($request->shift) {
                    $sh = $request->shift;
                    $shift = $shift->where('kode', $sh);
                    $catatan = $catatan->whereHas('jadwal.shift', function ($q) use ($sh) {
                        $q->where('kode', $sh);
                    });
                }

                $shift = $shift->get();
                $catatan = $catatan->orderBy('created_at', 'asc')->get();
                $approval = $approval->first();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'FCT')->orderBy('kategori', 'asc')->orderBy('judul', 'asc')->get();
                    $a = $cek->map(function ($cek) use ($request, $shift) {
                        $isi= LaporanIsi::where('form_isian_id', $cek->uuid)->with('laporan.jadwal.shift','laporan.user','pilihan')->whereHas('laporan.jadwal.shift', function ($q) use ($shift){
                            $q->where('nama', $shift->nama);
                        });
                            
                        if ($request->date) {
                            $date = $request->date;
                            $isi = $isi->whereHas('laporan.jadwal', function ($q) use ($date) {
                                $q->whereDate('tanggal', $date);
                            });
                        }   

                        if ($request->nama) {
                            $nama = $request->nama;
                            $isi = $isi->whereHas('laporan.user', function ($q) use ($nama) {
                                $q->where('nama', 'ilike', '%'. $nama .'%');
                            });
                        }                

                        $isi = $isi->orderBy('created_at', 'asc')->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama ?? '';
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $rangejam = $isi->map(function ($isi) {
                            $jamawal = 0;
                            $jamakhir = 0;
                            $kode = $isi->laporan->range_jam_kode;
                            $time = LaporanRangeJam::where('kode', $kode)->first();
                            if($time) {
                                $jamawal = Carbon::parse($time->time)->format('H:00');
                                $plus1jam = Carbon::parse($jamawal)->addHour(1);
                                $jamakhir = $plus1jam->format('H:00');
                            }
                            
                            // return $jamawal.'-'.$jamakhir;
                            return $jamawal;
                        });

                        $warna = $isi->map(function ($isi) use ($cek) {
                            // return $isi->warna = $isi->laporan->user->color ?? '';
                            if(strpos($cek->judul, 'PAC') !== false) {
                                if(($isi->isian != '') && ($isi->isian != null)) {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                }
                            } else {
                                if ($isi->pilihan){
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                }
                            }
                        });
                        $isi->map(function ($isi) use ($cek) {
                            if(strpos($cek->judul, 'PAC') !== false) {
                                if(($isi->isian != '') && ($isi->isian != null)) {
                                    return $isi->keadaan = $isi->isian ?? '';
                                } else {
                                    return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                                }
                            } else {
                                if ($isi->pilihan){
                                    return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                                } else {
                                    return $isi->keadaan = $isi->isian ?? '';
                                }
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna, $cek->rangejam = $rangejam];
                    });

                    $jadwal = Jadwal::with('user')->whereHas('user')->where('kode_shift', $shift->kode);
                    if ($request->date) {
                        $date = $request->date;
                        $jadwal = $jadwal->whereDate('tanggal', '=', $date);
                    }
                    $jadwal = $jadwal->get();

                    $eosNM = $jadwal->map(function ($jj) use ($request, $shift) {
                        return $jj->eos = $jj->user->nama ?? '';
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2], $shift->range = $a[3][3], $shift->daftareosyangshift = $eosNM];
                });
                $fasilitas->put('data',$shift);
                $fasilitas->put('approval',$approval);
               
                //Cleaning
                $shift = null;
                $approval = null;
                
                $shift = Shift::orderBy('mulai', 'asc')->where('created_at', '!=', null);

                $approval = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'CLN');
                
                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    // $catatan = $catatan->whereDate('created_at', '=', $date);
                    $approval = $approval->whereDate('tanggal', '=', $date);
                } else {
                    $approval = $approval->whereDate('tanggal', '=', date('Y-m-d'));
                }
                if ($request->nama) {
                    $nama = $request->nama;
                }
                if ($request->shift) {
                    $sh = $request->shift;
                    $shift = $shift->where('kode', $sh);
                }

                $shift = $shift->get();
                $approval = $approval->first();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'CLN')->orderBy('kategori', 'asc')->orderBy('judul', 'asc')->get();
                    $a = $cek->map(function ($cek) use ($request, $shift) {
                        $isi= LaporanIsi::where('form_isian_id', $cek->uuid)->with('laporan.jadwal.shift','laporan.user','pilihan')->whereHas('laporan.jadwal.shift', function ($q) use ($shift){
                            $q->where('nama', $shift->nama);
                        });
                            
                        if ($request->date) {
                            $date = $request->date;
                            $isi = $isi->whereHas('laporan.jadwal', function ($q) use ($date) {
                                $q->whereDate('tanggal', $date);
                            });
                        }   

                        if ($request->nama) {
                            $nama = $request->nama;
                            $isi = $isi->whereHas('laporan.user', function ($q) use ($nama) {
                                $q->where('nama', 'ilike', '%'. $nama .'%');
                            });
                        }                

                        $isi = $isi->orderBy('created_at', 'asc')->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama ?? '';
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $rangejam = $isi->map(function ($isi) {
                            $jamawal = 0;
                            $jamakhir = 0;
                            $kode = $isi->laporan->range_jam_kode;
                            $time = LaporanRangeJam::where('kode', $kode)->first();
                            if($time) {
                                $jamawal = Carbon::parse($time->time)->format('H:00');
                                $plus1jam = Carbon::parse($jamawal)->addHour(1);
                                $jamakhir = $plus1jam->format('H:00');
                            }
                            
                            // return $jamawal.'-'.$jamakhir;
                            return $jamawal;
                        });

                        $warna = $isi->map(function ($isi) use ($cek) {
                            // return $isi->warna = $isi->laporan->user->color ?? '';
                            if($cek->kategori == 'PAC') {
                                return $isi->warna = color_value($isi->isian ?? '');
                            } else {
                                if ($isi->pilihan){
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                }
                            }
                        });
                        $isi->map(function ($isi) {
                            if ($isi->pilihan){
                                return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                            } else {
                                return $isi->keadaan = $isi->isian ?? '';
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna, $cek->rangejam = $rangejam];
                    });

                    $jadwal = Jadwal::with('user')->whereHas('user')->where('kode_shift', $shift->kode);
                    if ($request->date) {
                        $date = $request->date;
                        $jadwal = $jadwal->whereDate('tanggal', '=', $date);
                    }
                    $jadwal = $jadwal->get();

                    $eosNM = $jadwal->map(function ($jj) use ($request, $shift) {
                        return $jj->eos = $jj->user->nama ?? '';
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2], $shift->range = $a[3][3], $shift->daftareosyangshift = $eosNM];
                });

                $cleaning->put('data',$shift);
                $cleaning->put('approval',$approval);

                //CCTV
                $shift = null;
                $approval = null;

                $shift = Shift::orderBy('mulai', 'asc')->where('created_at', '!=', null);
                
                $approval = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'CCTV');
                
                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    // $catatan = $catatan->whereDate('created_at', '=', $date);
                    $approval = $approval->whereDate('tanggal', '=', $date);
                } else {
                    $approval = $approval->whereDate('tanggal', '=', date('Y-m-d'));
                }
                if ($request->nama) {
                    $nama = $request->nama;
                }
                if ($request->shift) {
                    $sh = $request->shift;
                    $shift = $shift->where('kode', $sh);
                }
				
				$shift = $shift->get();
                $approval = $approval->first();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'CCTV')->orderBy('kategori', 'asc')->orderBy('judul', 'asc')->get();
                    $a = $cek->map(function ($cek) use ($request, $shift) {
                        $isi= LaporanIsi::where('form_isian_id', $cek->uuid)->with('laporan.jadwal.shift','laporan.user','pilihan')->whereHas('laporan.jadwal.shift', function ($q) use ($shift){
                            $q->where('nama', $shift->nama);
                        });
                            
                        if ($request->date) {
                            $date = $request->date;
                            $isi = $isi->whereHas('laporan.jadwal', function ($q) use ($date) {
                                $q->whereDate('tanggal', $date);
                            });
                        }   

                        if ($request->nama) {
                            $nama = $request->nama;
                            $isi = $isi->whereHas('laporan.user', function ($q) use ($nama) {
                                $q->where('nama', 'ilike', '%'. $nama .'%');
                            });
                        }                

                        $isi = $isi->orderBy('created_at', 'asc')->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama ?? '';
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $rangejam = $isi->map(function ($isi) {
                            $jamawal = 0;
                            $jamakhir = 0;
                            $kode = $isi->laporan->range_jam_kode;
                            $time = LaporanRangeJam::where('kode', $kode)->first();
                            if($time) {
                                $jamawal = Carbon::parse($time->time)->format('H:00');
                                $plus1jam = Carbon::parse($jamawal)->addHour(1);
                                $jamakhir = $plus1jam->format('H:00');
                            }
                            
                            // return $jamawal.'-'.$jamakhir;
                            return $jamawal;
                        });

                        $warna = $isi->map(function ($isi) use ($cek) {
                            // return $isi->warna = $isi->laporan->user->color ?? '';
                            if($cek->kategori == 'PAC') {
                                return $isi->warna = color_value($isi->isian ?? '');
                            } else {
                                if ($isi->pilihan){
                                    return $isi->warna = color_value($isi->pilihan->pilihan ?? '');
                                } else {
                                    return $isi->warna = color_value($isi->isian ?? '');
                                }
                            }
                        });
                        $isi->map(function ($isi) {
                            if ($isi->pilihan){
                                return $isi->keadaan = $isi->pilihan->pilihan ?? '';
                            } else {
                                return $isi->keadaan = $isi->isian ?? '';
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna, $cek->rangejam = 
                        $rangejam];
                    });

                    $jadwal = Jadwal::with('user')->whereHas('user')->where('kode_shift', $shift->kode);
                    if ($request->date) {
                        $date = $request->date;
                        $jadwal = $jadwal->whereDate('tanggal', '=', $date);
                    }
                    $jadwal = $jadwal->get();

                    $eosNM = $jadwal->map(function ($jj) use ($request, $shift) {
                        return $jj->eos = $jj->user->nama ?? '';
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2], $shift->range = $a[3][3], $shift->daftareosyangshift = $eosNM];
                });

                $cctv->put('data',$shift);
                $cctv->put('approval',$approval);
				
				$approvalAll = LaporanCetakApproval::with('user', 'approver')->where('jenis', 'ALL');

                if ($request->date) {
                    $date = date('Y-m-d', strtotime($request->date));
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', $date);
                } else {
                    $approvalAll = $approvalAll->whereDate('tanggal', '=', date('Y-m-d'));
                }

                $approvalAll = $approvalAll->first();
				
                if (empty($shift)) {
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
                        'fasilitas' => $fasilitas,
                        'cleaning' => $cleaning,
                        'cctv' => $cctv,
						'approval' => $approvalAll,
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

                $laporan = LaporanIsi::where('laporan_id', $id)->with('pilihan.isianForm', 'isian')->get();
                $laporan->map(function ($laporan) use ($eos) {
                    return $laporan->nama_eos = $eos->user->nama;
                });
                // $laporan = $laporan->isian()->groupBy('kategori')->get();
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

    public function getFormJenis()
    {
        $data = FormJenis::all();
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'code'    => 200,
            'data'  => $data,
        ]);
    }

    public function getList()
    {   
        try
        {
            $decodeToken = parseJwt($this->request->header('Authorization'));
            $uuid = $decodeToken->user->uuid;
            $user = User::where('uuid', $uuid)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak ditemukan',
                    'code'    => 404,
                ]);
            }
            
            $tahun = [];
            $tahun = Laporan::selectRaw("extract(year from created_at) as tahun")->distinct()->get();

            if (!count($tahun)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Tidak Ditemukan',
                    'code'    => 404,
                ]);
            }

            foreach($tahun as $item){
                $getbulan = Laporan::selectRaw("extract(month from created_at) as bulan")->whereYear('created_at',$item->tahun)->distinct()->get();
                $listbulan=[];
                foreach($getbulan as $itemm){
                    $object = new \stdClass();
                    $object->bulan = $this->bulan[$itemm->bulan];
                    $object->value = $itemm->bulan;
                    array_push($listbulan,$object);
                }
                $item->listbulan = $listbulan;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Data Tahun',
                'code'    => 200,
                'data'    => $tahun
            ]);
        } catch (\Throwable $th) {
            return writeLog($th->getMessage().' at Line '.$th->getLine());
        }
    }

    public function getPAC()
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
                $month = $this->request->bulan;
                $year = $this->request->tahun;
                $days_count = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $tanggal = [];
                for($i = 1; $i <=  $days_count; $i++)
                {
                    $tanggal[] = $year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                }
                $kode_shift = ['P','S','M'];
                $time = [
                    ['08:00:00','09:00:00','10:00:00','11:00:00','12:00:00','13:00:00','14:00:00','15:00:00'],
                    ['16:00:00','17:00:00','18:00:00','19:00:00','20:00:00','21:00:00','22:00:00','23:00:00'],
                    ['00:00:00','01:00:00','02:00:00','03:00:00','04:00:00','05:00:00','06:00:00','07:00:00']
                ];
                $judul = ['PAC 1 TEMP (°C)','PAC 1 HUM (RH%)','PAC 2 TEMP (°C)','PAC 2 HUM (RH%)','PAC 3 TEMP (°C)','PAC 3 HUM (RH%)','PAC 4 TEMP (°C)','PAC 4 HUM (RH%)','PAC 5 TEMP (°C)','PAC 5 HUM (RH%)'];
                //siapkan data
                $data_all=[];
                foreach($tanggal as $a){
                    foreach($kode_shift as $ib=>$b){
                        $isic=[];
                        foreach($time[$ib] as $c){
                            foreach($judul as $d){
                                $isid[$d]='-';
                            }
                            $isic[$c]=$isid;
                        }
                        $isib[$b]=$isic;
                    }
                    $isia[$a]=$isib;
                }
                $data_all=$isia;
                // dd($data_all);

                $query = DB::table('ms_laporan_isi','isi')->
                selectRaw("shift.tanggal,shift.kode_shift,jam.time,form.judul,pilih.pilihan,isi.isian")
                ->join('ms_laporan as lap','isi.laporan_id','=','lap.uuid')
                ->join('ms_shift_jadwal as shift','lap.jadwal_shift_id','=','shift.uuid')
                ->join('ms_laporan_range_jam as jam','lap.range_jam_kode','=','jam.kode')
                ->join('ms_form_isian as form','isi.form_isian_id','=','form.uuid')
                ->join('ms_form_pilihan as pilih','isi.pilihan_id','=','pilih.uuid')
                ->whereRaw('EXTRACT(MONTH FROM shift.tanggal)=?',$month)
                ->whereRaw('EXTRACT(YEAR FROM shift.tanggal)=?',$year)
                ->whereRaw("isi.form_isian_id in (SELECT uuid from ms_form_isian where judul LIKE 'PAC%' and judul NOT LIKE 'PAC 0%')")
                ->get();
                // dd($query->toSql(),$this->request->all());

                foreach($query as $item){
                    // dd($item,$data_all[$item->tanggal][$item->kode_shift][$item->time][$item->judul]);
                    if($item->pilihan=='RUNNING'){
                        $data_all[$item->tanggal][$item->kode_shift][$item->time][$item->judul]=$item->isian;
                    }else{
                        $data_all[$item->tanggal][$item->kode_shift][$item->time][$item->judul]='-';
                    }
                }
                // dd($data_all);
                return response()->json([
                    'success' => true,
                    'message' => 'OK',
                    'code'    => 200,
                    'data'  => $data_all,
                    'device' => $judul
                ]);
            } catch (\Throwable $th) {
                return writeLog($th->getMessage());
            }
        }
    }

    public function getUPS()
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
                $month = $this->request->bulan;
                $year = $this->request->tahun;
                $days_count = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $tanggal = [];
                for($i = 1; $i <=  $days_count; $i++)
                {
                    $tanggal[] = $year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($i, 2, '0', STR_PAD_LEFT);
                }
                $kode_shift = ['P','S','M'];
                $time = [
                    ['08:00:00','09:00:00','10:00:00','11:00:00','12:00:00','13:00:00','14:00:00','15:00:00'],
                    ['16:00:00','17:00:00','18:00:00','19:00:00','20:00:00','21:00:00','22:00:00','23:00:00'],
                    ['00:00:00','01:00:00','02:00:00','03:00:00','04:00:00','05:00:00','06:00:00','07:00:00']
                ];
                $judul = ['UPS 1 VOLTAGE (VAC) (R)','UPS 1 VOLTAGE (VAC) (S)','UPS 1 VOLTAGE (VAC) (T)','UPS 1 AMPERE (A) (R)','UPS 1 AMPERE (A) (S)','UPS 1 AMPERE (A) (T)','UPS 1 LOAD LEVEL (%) (R)','UPS 1 LOAD LEVEL (%) (S)','UPS 1 LOAD LEVEL (%) (T)','UPS 2 VOLTAGE (VAC) (R)','UPS 2 VOLTAGE (VAC) (S)','UPS 2 VOLTAGE (VAC) (T)','UPS 2 AMPERE (A) (R)','UPS 2 AMPERE (A) (S)','UPS 2 AMPERE (A) (T)','UPS 2 LOAD LEVEL (%) (R)','UPS 2 LOAD LEVEL (%) (S)','UPS 2 LOAD LEVEL (%) (T)','UPS APC VOUT (VAC) (R)','UPS APC VOUT (VAC) (S)','UPS APC VOUT (VAC) (T)','UPS APC IOUT (A) (R)','UPS APC IOUT (A) (S)','UPS APC IOUT (A) (T)','UPS APC RUNTIME (MIN)'];
                //siapkan data
                $data_all=[];
                foreach($tanggal as $a){
                    foreach($kode_shift as $ib=>$b){
                        $isic=[];
                        foreach($time[$ib] as $c){
                            foreach($judul as $d){
                                $isid[$d]='-';
                            }
                            $isic[$c]=$isid;
                        }
                        $isib[$b]=$isic;
                    }
                    $isia[$a]=$isib;
                }
                $data_all=$isia;
                // dd($data_all);

                $query = DB::table('ms_laporan_isi','isi')->
                selectRaw("shift.tanggal,shift.kode_shift,jam.time,form.judul,isi.isian")
                ->join('ms_laporan as lap','isi.laporan_id','=','lap.uuid')
                ->join('ms_shift_jadwal as shift','lap.jadwal_shift_id','=','shift.uuid')
                ->join('ms_laporan_range_jam as jam','lap.range_jam_kode','=','jam.kode')
                ->join('ms_form_isian as form','isi.form_isian_id','=','form.uuid')
                ->whereRaw('EXTRACT(MONTH FROM shift.tanggal)=?',$month)
                ->whereRaw('EXTRACT(YEAR FROM shift.tanggal)=?',$year)
                ->whereRaw("isi.form_isian_id in (SELECT uuid from ms_form_isian where judul LIKE 'UPS%')")
                ->get();
                // dd($query->toSql(),$query->get());

                foreach($query as $item){
                    $data_all[$item->tanggal][$item->kode_shift][$item->time][$item->judul]=$item->isian;
                }
                // dd($data_all);
                return response()->json([
                    'success' => true,
                    'message' => 'OK',
                    'code'    => 200,
                    'data'  => $data_all,
                    'device' => $judul
                ]);
            } catch (\Throwable $th) {
                return writeLog($th->getMessage());
            }
        }
    }
}
