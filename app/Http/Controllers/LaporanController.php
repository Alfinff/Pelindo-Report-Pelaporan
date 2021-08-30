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
                $shift = Shift::orderBy('created_at', 'asc')->where('created_at', '!=', null);
                      
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal.shift')->orderBy('created_at', 'desc');
                
                if ($request->date) {
                    $date = $request->date;
                    $catatan = $catatan->whereDate('created_at', '=', $date);
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
                $catatan = $catatan->get();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'FCT')->get();
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

                        $isi = $isi->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama;
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $warna = $isi->map(function ($isi) {
                            return $isi->warna = $isi->laporan->user->color;
                        });
                        $isi->map(function ($isi) {
                            if ($isi->pilihan){
                                return $isi->keadaan = $isi->pilihan->pilihan;
                            } else {
                                return $isi->keadaan = $isi->isian;
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna];
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2]];
                });

               
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
                $shift = Shift::orderBy('created_at', 'asc')->where('created_at', '!=', null);
                      
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal.shift')->orderBy('created_at', 'desc');
                
                if ($request->date) {
                    $date = $request->date;
                    $catatan = $catatan->whereDate('created_at', '=', $date);
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
                $catatan = $catatan->get();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'CLN')->get();
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

                        $isi = $isi->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama;
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $warna = $isi->map(function ($isi) {
                            return $isi->warna = $isi->laporan->user->color;
                        });
                        $isi->map(function ($isi) {
                            if ($isi->pilihan){
                                return $isi->keadaan = $isi->pilihan->pilihan;
                            } else {
                                return $isi->keadaan = $isi->isian;
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna];
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2]];
                });

               
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
                $shift = Shift::orderBy('created_at', 'asc')->where('created_at', '!=', null);
                      
                $catatan = LaporanShift::with('user', 'jadwal.shift')->whereHas('jadwal.shift')->orderBy('created_at', 'desc');
                
                if ($request->date) {
                    $date = $request->date;
                    $catatan = $catatan->whereDate('created_at', '=', $date);
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
                $catatan = $catatan->get();

                $shift->map(function ($shift) use ($request){
                    $cek = FormIsian::where('form_jenis', 'CCTV')->get();
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

                        $isi = $isi->get();

                        $isi->map(function ($isi) {
                            return $isi->eos = $isi->laporan->user->nama;
                        });
                        $s = $isi->map(function ($isi) {
                            return $isi->jam_laporan = Carbon::parse($isi->created_at)->format('H:i');
                        });

                        $warna = $isi->map(function ($isi) {
                            return $isi->warna = $isi->laporan->user->color;
                        });
                        $isi->map(function ($isi) {
                            if ($isi->pilihan){
                                return $isi->keadaan = $isi->pilihan->pilihan;
                            } else {
                                return $isi->keadaan = $isi->isian;
                            }
                        });

                        return [$cek->kondisi = $isi, $cek->s = $s, $cek->warna = $warna];
                    });

                    return [$shift->data = $cek, $shift->jam = $a[1][1], $shift->warna = $a[2][2]];
                });

               
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

}
