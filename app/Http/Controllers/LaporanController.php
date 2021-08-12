<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Laporan;
use App\Models\LaporanIsi;
use App\Models\LaporanCatatan;
use Illuminate\Support\Facades\DB;
use GrahamCampbell\Flysystem\Facades\Flysystem;
use Illuminate\Support\Facades\Hash;

class LaporanController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function catatanShift()
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
            
            return $user;
            $catatan = LaporanCatatan::create([
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
            'judul' => 'required',
            'isi' => 'required',
            'kategori' => 'required',
            // 'isian'
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
                'judul' => $this->request->judul,
                'isi' => $this->request->isi,
                'form_jenis' => $this->request->kategori
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

}
