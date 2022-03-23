<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Informasi;
use App\Models\InformasiUser;
use App\Models\Shift;
use App\Models\Jadwal;
use App\Models\Laporan;
use App\Models\LaporanIsi;
use App\Models\LaporanShift;
use App\Models\LaporanDikerjakan;
use App\Models\LaporanRangeJam;
use App\Models\FormIsian;
use App\Models\FormIsianKategori;
use App\Models\LaporanCetakApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use GrahamCampbell\Flysystem\Facades\Flysystem;
use Carbon\Carbon;

class LaporanMobileController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function detailLaporan($id)
    {
        try {

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

            // ->where('form_jenis', 'LIKE', $this->request->form_jenis)
            $laporan = Laporan::where('uuid', $id)->first();

            if(!$laporan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data laporan tidak ditemukan',
                    'code'    => 404
                ]);
            }
        
            $formKategoriIsian = FormIsianKategori::where('form_jenis', $this->request->form_jenis)->get();

            if (!count($formKategoriIsian)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data jenis form tidak ditemukan',
                    'code'    => 404
                ]);
            }

            $laporanisi = LaporanIsi::where('laporan_id', $id)->with('isian')->get();

            // return $laporanisi;

            if(!count($laporanisi)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data laporan isi tidak ditemukan',
                    'code'    => 404
                ]);
            }

            $data = $formKategoriIsian->map(function ($dataKategori) use ($laporanisi) {
                $data = [];
                $form = FormIsian::with(['jenis_form', 'kategori_isian', 'pilihan' => function($query) {
                    $query->orderByRaw("
                    CASE pilihan
                    WHEN 'OK' THEN 1
                    WHEN 'NORMAL' THEN 2
                    WHEN 'RUNNING' THEN 3
                    WHEN 'HIJAU (penuh)' THEN 4
                    WHEN 'STANDBY' THEN 5
                    WHEN 'NOT OK' THEN 6
                    ELSE 7
                    END ASC
                    ");
                }])->where('status', 1)->where('kategori', $dataKategori->kode)->where('form_jenis', $this->request->form_jenis)->orderBy('kategori', 'asc')->orderByRaw(
                    "CASE 
                    WHEN judul like 'PAC 1 T%' THEN 2
                    WHEN judul like 'PAC 1 H%' THEN 3
                    WHEN judul like 'PAC 2 T%' THEN 4
                    WHEN judul like 'PAC 2 H%' THEN 5
                    WHEN judul like 'PAC 3 T%' THEN 6
                    WHEN judul like 'PAC 3 H%' THEN 7
                    WHEN judul like 'PAC 4 T%' THEN 8
                    WHEN judul like 'PAC 4 H%' THEN 9
                    WHEN judul like 'PAC 5 T%' THEN 10
                    WHEN judul like 'PAC 5 H%' THEN 11
                    WHEN judul like 'UPS 1 V%' THEN 12
                    WHEN judul like 'UPS 1 A%' THEN 13
                    WHEN judul like 'UPS 1 L%' THEN 14
                    WHEN judul like 'UPS 2 V%' THEN 15
                    WHEN judul like 'UPS 2 A%' THEN 16
                    WHEN judul like 'UPS 2 L%' THEN 17
                    WHEN judul like 'UPS APC V%' THEN 18
                    WHEN judul like 'UPS APC I%' THEN 19
                    WHEN judul like 'UPS APC R%' THEN 20
                    ELSE 1
                    END ASC"
                )->orderBy('judul', 'asc')->get();

                $form = $form->map(function ($dataForm) use ($laporanisi) {
                    $isian = [];
                    $jawaban = [];
                    $keterangan = [];
                    $form = [];
                    $form['uuid'] = $dataForm->uuid ?? '';
                    $form['judul'] = $dataForm->judul ?? '';
                    $form['status'] = $dataForm->status ?? '';
                    $form['created_at'] = $dataForm->created_at ?? '';
                    $form['updated_at'] = $dataForm->updated_at ?? '';
                    $form['tipe'] = $dataForm->tipe;
                    $form['kategori'] = '';
                    if($dataForm->kategori_isian) {
                        $form['kategori'] = str_replace('-', ' ', $dataForm->kategori_isian->kode) ?? '';
                    }
                    $form['jenis'] = '';
                    if($dataForm->jenis_form) {
                        $form['jenis'] = $dataForm->jenis_form->nama  ?? '';
                    }
                    $form['pilihan'] = [];
                    if($dataForm->pilihan) {
                        $pilihan = $dataForm->pilihan->map(function ($dataPilihan) {
                            $pilihan = [];
                            $pilihan['uuid'] = $dataPilihan->uuid ?? '';
                            $pilihan['pilihan'] = $dataPilihan->pilihan ?? '';
                            $pilihan['laporan_id'] = $dataPilihan->isian_id ?? '';

                            return $pilihan;
                        });

                        $form['pilihan'] = $pilihan;
                    }

                    $jawaban = [];
                    $jawaban['uuid'] = '';
                    $jawaban['pilihan'] = '';
                    $jawaban['isian_id'] = '';
                    $jawaban['created_at'] = '';
                    $jawaban['updated_at'] = '';
                    foreach($laporanisi as $item) {
                        if($item->form_isian_id == $dataForm->uuid) {
                            if($item->pilihan) {
                                $jawaban = $item->pilihan;
                            }
                            $keterangan = $item->keterangan ?? '';
                            $isian = $item->isian ?? '';
                        }
                    }

                    $form['jawaban'] = $jawaban;
                    $form['isian'] = $isian;
                    $form['keterangan'] = $keterangan;

                    return $form;
                });

                $data['kategori'] = $dataKategori->kode;
                $data['data'] = $form;
                
                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Data Form '.$this->request->form_jenis,
                'code'    => 200,
                'tipe'    => $this->request->form_jenis,
                'data'    => $data,
            ]);
        
        } catch (\Throwable $th) {
            return writeLog($th->getMessage());
        }
    }

    public function updateCatatanShift(Request $request, $id)
    {
        // cek validasi input
        $validator = Validator::make($this->request->all(), [
            'judul' => 'required',
            'isi' => 'required'
        ]);

        if ($validator->fails()) {
            return writeLogValidation($validator->errors());
        }

        DB::beginTransaction();
        try {
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

            // cek catatan shift user
            $cekCatatan = LaporanShift::where('uuid', $id)->where('user_id', $uuid)->first();
            if (!$cekCatatan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Laporan Shift tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            // bisa di edit atau tidak
            $created_at = strtotime($cekCatatan->created_at);
            $batasedit = strtotime('-1 days');

            if($created_at <= $batasedit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Laporan tidak bisa diedit karena melewati batas waktu yang ditentukan',
                    'code'    => 404,
                ]);
            }
            
            $cekCatatan->update([
                'judul' => $this->request->judul,
                'isi' => $this->request->isi,
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil edit laporan shift',
                'code'    => 200,
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return writeLog($th->getMessage());
        }
    }

    // mulai fungsi dengan pengecekan modul
    public function catatanShift(Request $request)
    {
        // cek validasi input
        $validator = Validator::make($this->request->all(), [
            'judul' => 'required',
            'isi' => 'required',
            'jadwal_shift_id' => 'required',
        ]);

        if ($validator->fails()) {
            return writeLogValidation($validator->errors());
        }

        DB::beginTransaction();
        try {
            $cekJadwal = '';
            $jammulai = '';
            $jamselesai = '';
            $jamsekarang = '';
            $dataShift = '';
            $minusoneday = date('Y-m-d', strtotime('-1 day', strtotime(date('Y-m-d'))));

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

            // validasi jadwal
            $dataJadwal = validasiJadwal($this->request->jadwal_shift_id, $uuid);
            if($dataJadwal['errcode'] == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan',
                    'code'    => 404,
                ]);
            } else if($dataJadwal['errcode'] == 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal anda libur',
                    'code'    => 404,
                ]);
            } else if($dataJadwal['errcode'] == 0) {
                $cekJadwal = $dataJadwal['data'] ?? '';
            }

            // validasi jam shift
            $dataShift = validasiJamShift($cekJadwal->kode_shift);
            if($dataShift['errcode'] == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal Shift tidak ditemukan',
                    'code'    => 404,
                ]);
            }
            $jammulai = $dataShift['jammulai'] ?? '';
            $jamselesai = $dataShift['jamselesai'] ?? '';
            $jamsekarang = $dataShift['jamsekarang'] ?? '';
            $getShift = $dataShift['data'] ?? '';

            $js = date('Y-m-d H:i:s', $jamselesai);
            $jamselesaiplus3 = strtotime('+3 hour', strtotime($js));
            $from = date('Y-m-d H:i:s', $jammulai);
            $to = date('Y-m-d H:i:s', $jamselesaiplus3);
            
            // cek sudah masuk waktu pengiriman laporan shift / belum
            if(($jammulai <= $jamsekarang) && ($jamselesaiplus3 >= $jamsekarang)) {
                $cek = LaporanShift::where('user_id', $uuid)->where('jadwal_shift_id', $this->request->jadwal_shift_id)->whereBetween('created_at', [$from, $to])->first();
                if ($cek) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kamu sudah melakukan pelaporan shift hari ini, pelaporan shift hanya bisa dilakukan 1 kali',
                        'code'    => 404
                    ]);
                }
            } else {
                // cek sudah masuk waktu shift / belum
                if (($jammulai >= $jamsekarang) && ($jamselesaiplus3 >= $jamsekarang)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda belum bisa melakukan pelaporan karena belum masuk jam shift anda',
                        'code'    => 404
                    ]);
                }

                // if (($jammulai <= $jamsekarang) && ($jamselesaiplus3 <= $jamsekarang)) {
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'Batas waktu pengiriman laporan shift sudah berakhir',
                //         'code'    => 404
                //     ]);
                // }
            }

            $created_at = '';
            if($cekJadwal->kode_shift == 'M') {
                $created_at = $minusoneday. ' '.date('H:i:s');
            } else {
                $created_at = date('Y-m-d H:i:s');
            }
            
            // input laporan shift
            $catatan = LaporanShift::create([
                'uuid' => generateUuid(),
                'judul' => $this->request->judul,
                'isi' => $this->request->isi,
                'jadwal_shift_id' => $this->request->jadwal_shift_id,
                'user_id' => $uuid,
                'created_at' => $created_at
            ]);
            
            // create informasi notif
            $informasi = Informasi::create([
                'uuid'         => generateUuid(),
                'info_id'      => $catatan->uuid,
                'judul'        => $this->request->judul,
                'isi'          => $this->request->isi,
                'jenis'        => env('NOTIF_CATATAN'),
            ]);
            
            // kirim Info Ke Shift Selanjutnya
            // $kodeShift = strtolower($cekJadwal->kode_shift);
            // if($kodeShift == 'p') {
            //     $shiftSelanjutnya = Jadwal::with('user')->where('kode_shift', 'S')->where('tanggal', date('Y-m-d'))->get();
            // } else if($kodeShift == 's') {
            //     $shiftSelanjutnya = Jadwal::with('user')->where('kode_shift', 'M')->where('tanggal', date('Y-m-d'))->get();
            // } else if($kodeShift == 'm') {
            //     $shiftSelanjutnya = Jadwal::with('user')->where('kode_shift', 'P')->where('tanggal', date('Y-m-d', strtotime(date('Y-m-d'). ' +1 day')))->get();
            // } else {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Nama jadwal tidak ditemukan',
            //         'code'    => 404,
            //     ]);
            // }

            // // blast notif Info Ke Shift Selanjutnya
            // foreach ($shiftSelanjutnya as $item) {
            //     InformasiUser::create([
            //         'uuid'         => generateUuid(),
            //         'user_id'      => $item->user->uuid,
            //         'informasi_id' => $informasi->uuid,
            //         'dibaca'       => 0,
            //     ]);

            //     if ($item->user->fcm_token) {
            //         $to      = $item->user->fcm_token;
            //         $payload = [
            //             'title'    => 'Laporan Shift',
            //             'body'     => $this->request->isi,
            //             'priority' => 'high',
            //         ];
            //         sendFcm($to, $payload, $payload);
            //     }
            // }

            // kirim ke semua user
            foreach(User::all() as $item) {
                InformasiUser::create([
                    'uuid'         => generateUuid(),
                    'user_id'      => $item->uuid,
                    'informasi_id' => $informasi->uuid,
                    'dibaca'       => 0,
                ]);
    
                if ($item->fcm_token) {
                    $to      = $item->fcm_token;
                    $payload = [
                        'title'    => 'Laporan Shift',
                        'body'     => $this->request->isi,
                        'priority' => 'high',
                    ];
                    sendFcm($to, $payload, $payload);
                }
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengirim laporan shift',
                'code'    => 200,
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return writeLog($th->getMessage());
        }
    }

    public function formIsian()
    {
        // cek validasi input
        $validator = Validator::make($this->request->all(), [
            'jadwal_shift_id' => 'required',
            'form_jenis' => 'required',
            'laporan.*.form_isian_id' => 'required',
            'range_jam_kode' => 'required',
        ]);

        if ($validator->fails()) {
            return writeLogValidation($validator->errors());
        }

        $qr_code_mode = false;
        if(!$this->request->has('qr_code') && $qr_code_mode){
            return response()->json([
                'success' => false,
                'message' => 'Lakukan scan Kode QR terlebih dahulu',
                'code'    => 404,
            ]);
        }

        DB::beginTransaction();
        try {
            $cekJadwal = '';
            $jammulai = '';
            $jamselesai = '';
            $jamsekarang = '';
            $dataShift = '';
            $minusoneday = date('Y-m-d', strtotime('-1 day', strtotime(date('Y-m-d'))));

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

            // validasi jadwal
            $dataJadwal = validasiJadwal($this->request->jadwal_shift_id, $uuid);
            if($dataJadwal['errcode'] == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan',
                    'code'    => 404,
                ]);
            } else if($dataJadwal['errcode'] == 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal anda libur',
                    'code'    => 404,
                ]);
            } else if($dataJadwal['errcode'] == 0) {
                $cekJadwal = $dataJadwal['data'] ?? '';
            }

            // cek kode laporan range jam 
            $cekkoderangejam = LaporanRangeJam::where('kode', $this->request->range_jam_kode)->first();
            if (!$cekkoderangejam) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode range jam tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            // 'jadwal_shift_id' => $this->request->jadwal_shift_id, 
            $where = array(
                'form_jenis' => $this->request->form_jenis,
                'range_jam_kode' => $this->request->range_jam_kode
            );

            $created_at = '';
            if($cekJadwal->kode_shift == 'M') {
                $created_at = $minusoneday. ' '.date('H:i:s');
            } else {
                $created_at = date('Y-m-d H:i:s');
            }

            $cek = LaporanDikerjakan::where($where)->whereDate('created_at', date('Y-m-d', strtotime($created_at)))->first();
            if($cek) {
                if($cek->user_id != $uuid) {
                    $pengerja = User::where('uuid', $cek->user_id)->first();
                    $nama = '';
                    if($pengerja) {
                        $nama = ucwords($pengerja->nama);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Laporan masih dikerjakan oleh '.$nama,
                        'code'    => 404,
                    ]);
                }
            }

            // cek laporan sudah dikerjakan / belum
            $cek = Laporan::where($where)->whereDate('created_at', date('Y-m-d', strtotime($created_at)))->first();
            if($cek) {
                return response()->json([
                    'success' => false,
                    'message' => 'Laporan pada jam ini telah dikirim',
                    'code'    => 404,
                ]);
            }

            $laporan = new Laporan;
            $laporan->uuid = generateUuid();
            $laporan->jadwal_shift_id = $this->request->jadwal_shift_id;
            $laporan->form_jenis = $this->request->form_jenis;
            $laporan->range_jam_kode = $this->request->range_jam_kode;
            $laporan->user_id = $uuid;

            // validasi jam shift
            $dataShift = validasiJamShift($cekJadwal->kode_shift);
            if($dataShift['errcode'] == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal Shift tidak ditemukan',
                    'code'    => 404,
                ]);
            }
            $jammulai = $dataShift['jammulai'] ?? '';
            $jamselesai = $dataShift['jamselesai'] ?? '';
            $jamsekarang = $dataShift['jamsekarang'] ?? '';
            $getShift = $dataShift['data'] ?? '';

            if($cekJadwal->kode_shift == 'M') {
                $laporan->created_at = $minusoneday. ' '.date('H:i:s');
            }

            // jika jam shift sudah terlewat maka ambil created ad dari laporan di kerjakan
            if(($jammulai <= $jamsekarang) && ($jamselesai >= $jamsekarang)) {
                
            } else {
                // ambil created at dari laporan ketika dikerjakan
                $cekDikerjakan = LaporanDikerjakan::where($where)->whereDate('created_at', date('Y-m-d', strtotime($created_at)))->where('user_id', $uuid)->first();
                if($cekDikerjakan) {
                    // cek laporan pada jam tersebut sudah di kerjakan / belum
                    $cek = Laporan::where($where)->whereDate('created_at', date('Y-m-d', strtotime($created_at)))->first();
                    if($cek) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Laporan pada jam tersebut telah dikirim',
                            'code'    => 404,
                        ]);
                    }
                    
                    if($cekJadwal->kode_shift == 'M') {
                        $laporan->created_at = $minusoneday. ' '.date('H:i:s', strtotime($cekDikerjakan->created_at));
                    } else {
                        $laporan->created_at = $cekDikerjakan->created_at;
                    }
                } else {
                    if($cekJadwal->kode_shift == 'M') {
                        $laporan->created_at = $minusoneday. ' '.date('H:i:s');
                    }
                }
            }

            // insert ke laporan
            $laporan->save();
            
            // validasi dan input jawaban form
            foreach($this->request->laporan as $item) {

                $cekForm = FormIsian::where('uuid', $item['form_isian_id'])->where('form_jenis', $this->request->form_jenis)->first();
                if(!$cekForm) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ID Form Tidak Sesuai',
                        'code'    => 404,
                    ]);
                } else {
                    if($cekForm->tipe!='ISIAN') {
                        if($item['pilihan_id']==null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Masih ada pilihan yang belum dijawab, periksa kembali!',
                                'code'    => 404,
                            ]);
                        }
                    } else if($cekForm->tipe=='ISIAN') {
                        if($item['isian']==null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Masih ada form yang belum dijawab, periksa kembali!',
                                'code'    => 404,
                            ]);
                        }
                    }
                }

                $cekForm = FormIsian::where('uuid', $item['form_isian_id'])->where('form_jenis', $this->request->form_jenis)->first();
                if(!$cekForm) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ID Form Tidak Sesuai',
                        'code'    => 404,
                    ]);
                }

                $laporanIsi = LaporanIsi::create([
                    'uuid' => generateUuid(),
                    'laporan_id' => $laporan->uuid,
                    'form_isian_id' => $item['form_isian_id'],
                    'pilihan_id' => $item['pilihan_id'] ?? '',
                    'isian' => $item['isian'] ?? '',
                    'keterangan' => $item['keterangan'] ?? '',
                ]);
            }

            //Auto Approval Request
            if($this->request->range_jam_kode == 'H') {
                $cekRequest = LaporanCetakApproval::where('jenis', 'ALL')->whereDate('tanggal', $minusoneday)->first();
                if(!$cekRequest) {
                    $approval = LaporanCetakApproval::create([
                        'uuid'     => generateUuid(),
                        'user_id'  => $uuid,
                        'jenis'  => 'ALL',
                        'tanggal'  => $minusoneday,
                        'created_at' => date('Y-m-d H:i:s'),
                        'is_approved' => 0,
                        'soft_delete' => 0
                    ]);
                }
            }
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil mengirim laporan form',
                'code'    => 200,
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return writeLog($th->getMessage());
        }
    }
}