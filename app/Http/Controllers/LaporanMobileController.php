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
use App\Models\FormIsian;
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

    public function catatanShift(Request $request)
    {
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

            // cek jadwal shift user
            $cekJadwal = Jadwal::where('uuid', $this->request->jadwal_shift_id)->first();
            if (!$cekJadwal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            // cek catatan shift hari ini sudah dikirim / belum
            $cekCatatanShiftSekarang = LaporanShift::where('jadwal_shift_id', $this->request->jadwal_shift_id)->where('user_id', $uuid)->whereDate('created_at', date('Y-m-d'))->first();
            // ->whereHas('jadwal', function ($query) {
            //     $query->where('kode_shift', $cekJadwal->kode_shift);
            // })

            if ($cekCatatanShiftSekarang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sudah mengirim laporan shift',
                    'code'    => 404,
                ]);
            }
            
            $catatan = LaporanShift::create([
                'uuid' => generateUuid(),
                'judul' => $this->request->judul,
                'isi' => $this->request->isi,
                'jadwal_shift_id' => $this->request->jadwal_shift_id,
                'user_id' => $uuid
            ]);
            
            // kirim Info Ke Shift Selanjutnya
            $informasi = Informasi::create([
                'uuid'         => generateUuid(),
                'info_id'      => $catatan->uuid,
                'judul'        => $this->request->judul,
                'isi'          => $this->request->isi,
                'jenis'        => env('NOTIF_CATATAN'),
            ]);

            
            $kodeShift = strtolower($cekJadwal->kode_shift);
            if($kodeShift == 'p') {
                $shiftSelanjutnya = Jadwal::with('user')->where('kode_shift', 'S')->where('tanggal', date('Y-m-d'))->get();
            } else if($kodeShift == 's') {
                $shiftSelanjutnya = Jadwal::with('user')->where('kode_shift', 'M')->where('tanggal', date('Y-m-d'))->get();
            } else if($kodeShift == 'm') {
                $shiftSelanjutnya = Jadwal::with('user')->where('kode_shift', 'P')->where('tanggal', date('Y-m-d', strtotime(date('Y-m-d'). ' +1 day')))->get();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama jadwal tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            // blast notif Info Ke Shift Selanjutnya
            foreach ($shiftSelanjutnya as $item) {
                InformasiUser::create([
                    'uuid'         => generateUuid(),
                    'user_id'      => $item->user->uuid,
                    'informasi_id' => $informasi->uuid,
                    'dibaca'       => 0,
                ]);

                if ($item->user->fcm_token) {
                    $to      = $item->user->fcm_token;
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

        $validator = Validator::make($this->request->all(), [
            'jadwal_shift_id' => 'required',
            'form_jenis' => 'required',
            'laporan.*.form_isian_id' => 'required',
            // 'laporan.*.pilihan_id' => 'required',
            // 'laporan.*.isian' => 'required',
            // 'laporan.*.keterangan' => 'required',
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

            // cek jadwal ada / tidak
            $cekJadwal = Jadwal::where('uuid', $this->request->jadwal_shift_id)->first();
            if (!$cekJadwal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            // cek laporan sedang dikerjakan atau tidak
            $jam_sekarang = date('Y-m-d H').':00:00';
            $jam_sekarang_plus1 = date('Y-m-d H', strtotime('+1 hour')).':00:00';
            $where = array('jadwal_shift_id' => $this->request->jadwal_shift_id, 'form_jenis' => $this->request->form_jenis);

            $cek = LaporanDikerjakan::where($where);
            $cek = $cek->whereBetween('created_at', [date('Y-m-d H:i:s', strtotime($jam_sekarang)), date('Y-m-d H:i:s', strtotime($jam_sekarang_plus1))]);
            $cek = $cek->first();
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
            $cek = Laporan::where($where);
            $cek = $cek->whereBetween('created_at', [date('Y-m-d H:i:s', strtotime($jam_sekarang)), date('Y-m-d H:i:s', strtotime($jam_sekarang_plus1))]);
            $cek = $cek->first();
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
            $laporan->user_id = $uuid;

            // cek pada jam ini termasuk range jam shift user yang mengirim atau tidak
            $getShift = Shift::where('kode', $cekJadwal->kode_shift)->first();
            $jammulai = strtotime(date('Y-m-d H:i:s', strtotime($getShift->mulai)));
            $jamselesai = strtotime(date('Y-m-d H:i:s', strtotime($getShift->selesai)));
            $jamsekarang = strtotime(date('Y-m-d H:i:s'));

            // jika jam shift sudah terlewat maka ambil created ad dari laporan di kerjakan
            if(($jammulai <= $jamsekarang) && ($jamselesai >= $jamsekarang)) {
                
            } else {
                // ambil created at dari laporan ketika dikerjakan
                $cekDikerjakan = LaporanDikerjakan::where($where)->where('user_id', $uuid)->first();
                if($cekDikerjakan) {
                    $jam_laporanDikerjakan = date('Y-m-d H', strtotime($cekDikerjakan->created_at)).':00:00';
                    $plus1 = strtotime('+1 hour', strtotime($jam_laporanDikerjakan));
                    $jam_laporanDikerjakan_plus1 = date('Y-m-d H', $plus1).':00:00';

                    // cek laporan pada jam tersebut sudah di kerjakan / belum
                    $cek = Laporan::where($where);
                    $cek = $cek->whereBetween('created_at', [date('Y-m-d H:i:s', strtotime($jam_laporanDikerjakan)), date('Y-m-d H:i:s', strtotime($jam_laporanDikerjakan_plus1))]);
                    $cek = $cek->first();
                    if($cek) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Laporan pada jam tersebut telah dikirim',
                            'code'    => 404,
                        ]);
                    }

                    $laporan->created_at = $cekDikerjakan->created_at;
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
