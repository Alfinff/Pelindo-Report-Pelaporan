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

class LaporanMobileController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function catatanShift(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->validate($this->request, [
                'judul' => 'required',
                'isi' => 'required',
                'jadwal_shift_id' => 'required',
            ]);

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

            $cekJadwal = Jadwal::where('uuid', $this->request->jadwal_shift_id)->first();
            if (!$cekJadwal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            $cekCatatanShiftSekarang = LaporanShift::where('jadwal_shift_id', $this->request->jadwal_shift_id)->where('user_id', $uuid)->whereDate('created_at', date('Y-m-d'))->first();

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

            $shiftHariIni = Jadwal::with('user')->where('tanggal', date('Y-m-d'))->get();
            foreach ($shiftHariIni as $item) {
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
        DB::beginTransaction();
        try {
            $this->validate($this->request, [
                'jadwal_shift_id' => 'required',
                'form_jenis' => 'required',
                'laporan.*.form_isian_id' => 'required',
                // 'laporan.*.pilihan_id' => 'required',
                // 'laporan.*.isian' => 'required',
                // 'laporan.*.keterangan' => 'required',
            ]);

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

            $cekJadwal = Jadwal::where('uuid', $this->request->jadwal_shift_id)->first();
            if (!$cekJadwal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jadwal tidak ditemukan',
                    'code'    => 404,
                ]);
            }

            $jam_sekarang = date('Y-m-d H').':00:00';
            $jam_sekarang_plus1 = date('Y-m-d H', strtotime('+1 hour')).':00:00';

            $where = array('jadwal_shift_id' => $this->request->jadwal_shift_id, 'form_jenis' => $this->request->form_jenis);
            
            // $cek = LaporanDikerjakan::where($where);
            // $cek = $cek->whereBetween('created_at', [date('Y-m-d H:i:s', strtotime($jam_sekarang)), date('Y-m-d H:i:s', strtotime($jam_sekarang_plus1))]);
            // $cek = $cek->first();

            // if($cek) {
            //     if($cek->user_id == $uuid) {
            //         return response()->json([
            //             'success' => false,
            //             'message' => 'Silahkan Melanjutkan pengisian laporan',
            //             'code'    => 404,
            //         ]);
            //     } else {
            //         $pengerja = User::where('uuid', $cek->user_id)->first();
            //         $nama = '';
            //         if($pengerja) {
            //             $nama = ucwords($pengerja->nama);
            //         }

            //         return response()->json([
            //             'success' => false,
            //             'message' => 'Laporan telah masih dikerjakan oleh '.$nama,
            //             'code'    => 404,
            //         ]);
            //     }
            // }

            $cek = Laporan::where($where);
            $cek = $cek->whereBetween('created_at', [date('Y-m-d H:i:s', strtotime($jam_sekarang)), date('Y-m-d H:i:s', strtotime($jam_sekarang_plus1))]);
            $cek = $cek->first();

            if($cek) {
                return response()->json([
                    'success' => false,
                    'message' => 'Laporan ini telah dikirim',
                    'code'    => 404,
                ]);
            }

            $laporan = Laporan::create([
                'uuid' => generateUuid(),
                'jadwal_shift_id' => $this->request->jadwal_shift_id,
                'form_jenis' => $this->request->form_jenis,
                'user_id' => $uuid,
            ]);
            
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
