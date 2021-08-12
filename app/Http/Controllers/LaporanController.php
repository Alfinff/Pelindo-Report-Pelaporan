<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Laporan;
use App\Models\LaporanIsi;
use App\Models\LaporanShift;
use Illuminate\Support\Facades\DB;
use GrahamCampbell\Flysystem\Facades\Flysystem;
use Illuminate\Support\Facades\Hash;

class LaporanController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function getLaporan(Request $request)
    {
        
    }

    public function getCatatanShift(Request $request)
    {
        
    }

    public function catatanShift(Request $request)
    {
        $this->validate($this->request, [
            'judul' => 'required',
            'isi' => 'required',
            'kategori' => 'required',
        ]);

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
            
            $catatan = LaporanShift::create([
                'uuid' => generateUuid(),
                'judul' => $this->request->judul,
                'isi' => $this->request->isi,
                'form_jenis' => $this->request->kategori,
                'user_id' => $uuid
            ]);

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
        $this->validate($this->request, [
            // 'shift' => 'required',
            'form_jenis' => 'required',
            'laporan.*.form_isian_id' => 'required',
            // 'laporan.*.pilihan_id' => 'required',
            // 'laporan.*.isian' => 'required',
            // 'laporan.*.keterangan' => 'required',
        ]);

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

            $laporan = Laporan::create([
                'uuid' => generateUuid(),
                'shift' => $this->request->shift ?? '',
                'form_jenis' => $this->request->form_jenis ?? '',
                'user_id' => $uuid,
            ]);
            
            foreach($this->request->laporan as $item) {
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
