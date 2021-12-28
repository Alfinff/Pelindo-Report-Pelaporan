<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Informasi;
use App\Models\InformasiUser;
use App\Models\Laporan;
use App\Models\LaporanCetakApproval;
use App\Models\FormIsianKategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use GrahamCampbell\Flysystem\Facades\Flysystem;

class LaporanApprovalController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index()
    {
        try 
        {
            $decodeToken = parseJwt($this->request->header('Authorization'));
            $uuid = $decodeToken->user->uuid;
            $cekUser = User::where('uuid', $uuid)->first();
            if (!$cekUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak ditemukan',
                    'code'    => 404,
                ]);
            }
            
            $laporan = LaporanCetakApproval::with('user', 'approver')->where('soft_delete', 0);
            $search = $this->request->search;

            if ($this->request->search) {
                $laporan = $laporan->whereHas('user', function($dd) use ($search) {
                    $dd->where(DB::raw("trim(lower(nama))"), 'LIKE', trim(strtolower($search)).'%');
                });
            }

            if ($this->request->date) {
                $laporan->whereDate('tanggal', date('Y-m-d', strtotime($this->request->date)));
            }

            if (($this->request->jenis) && ($this->request->jenis != 'ALL')) {
                $laporan->where('jenis', strtoupper($this->request->jenis));
            }

            $laporan = $laporan->orderBy('created_at', 'desc')->paginate(10);
            $laporan = $laporan->setPath(env('APP_URL', 'https://centro.pelindo.co.id/api/pelaporan/').'superadmin/laporan/cetak?search='.$this->request->search.'&jenis='.$this->request->jenis.'&date='.$this->request->date);

            if (empty($laporan)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Tidak Ditemukan',
                    'code'    => 404,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'OK',
                'code'    => 200,
                'data'  => $laporan
            ]);
        } catch (\Throwable $th) {
            return writeLog($th->getMessage());
        }
    }

    public function getDiEOS()
    {
        try 
        {
            $decodeToken = parseJwt($this->request->header('Authorization'));
            $uuid = $decodeToken->user->uuid;
            $cekUser = User::where('uuid', $uuid)->first();
            if (!$cekUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak ditemukan',
                    'code'    => 404,
                ]);
            }
            
            $laporan = LaporanCetakApproval::with('user', 'approver')->where('soft_delete', 0);
            $search = $this->request->search;

            if ($this->request->search) {
                $laporan = $laporan->whereHas('user', function($dd) use ($search) {
                    $dd->where(DB::raw("trim(lower(nama))"), 'LIKE', trim(strtolower($search)).'%');
                });
            }

            if ($this->request->date) {
                $laporan->whereDate('tanggal', date('Y-m-d', strtotime($this->request->date)));
            }

            if (($this->request->jenis) && ($this->request->jenis != 'ALL')) {
                $laporan->where('jenis', strtoupper($this->request->jenis));
            }

            $laporan = $laporan->orderBy('created_at', 'desc')->paginate(10);
            $laporan = $laporan->setPath(env('APP_URL', 'https://centro.pelindo.co.id/api/pelaporan/').'eos/laporan/cetak?search='.$this->request->search.'&jenis='.$this->request->jenis.'&date='.$this->request->date);

            if (empty($laporan)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data Tidak Ditemukan',
                    'code'    => 404,
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'OK',
                'code'    => 200,
                'data'  => $laporan
            ]);
        } catch (\Throwable $th) {
            return writeLog($th->getMessage());
        }
    }

    public function requestLaporan()
    {
        // cek validasi
        $validator = Validator::make($this->request->all(), [
            'tanggal' => 'required',
            'jenis' => 'required',
        ]);

        if ($validator->fails()) {
            return writeLogValidation($validator->errors());
        }

        DB::beginTransaction(); 
        try 
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

            $tanggal = null;
            $jenis = null;
            $tanggal = date('Y-m-d', strtotime($this->request->tanggal));
            $jenis = strtoupper($this->request->jenis);

            if($jenis != 'ALL') {
                $formKategoriIsian = FormIsianKategori::where('form_jenis', $jenis)->get();
                if (!count($formKategoriIsian)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data jenis form tidak ditemukan',
                        'code'    => 404
                    ]);
                }
            }

            // cek apakah sudah request
            // ->where('user_id', $uuid)
            $cek = LaporanCetakApproval::with('user')->whereDate('tanggal', $tanggal)->where('jenis', $jenis)->first();
            if($cek) {
                if($cek->user->uuid == $uuid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda telah melakukan request laporan pada '.$tanggal.' dengan kategori '.$jenis,
                        'code'    => 404,
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Laporan telah direquest oleh '.ucwords($cek->user->nama ?? ''),
                        'code'    => 404,
                    ]);
                }
            }

            // insert request
            $approval = LaporanCetakApproval::create([
                'uuid'     => generateUuid(),
                'user_id'  => $uuid,
                'jenis'  => $jenis,
                'tanggal'  => $tanggal,
                'created_at' => date('Y-m-d H:i:s'),
                'is_approved' => 0,
                'soft_delete' => 0
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil request laporan, tunggu approval dari admin',
                'code'    => 200
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return writeLog($th->getMessage());
        }
    }

    public function approve()
    {
        // cek validasi
        $validator = Validator::make($this->request->all(), [
            'uuid' => 'required',
        ]);

        if ($validator->fails()) {
            return writeLogValidation($validator->errors());
        }

        DB::beginTransaction();
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
                
            $laporanApprove = LaporanCetakApproval::where('uuid', $this->request->uuid)->first();
            if (!$laporanApprove) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan',
                    'code'    => 404,
                ]);
            }
            
            $laporanApprove->update([
                'approved_by'  => $uuid,
                'approved_at'  => date('Y-m-d H:i:s'),
                'is_approved'  => 1,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil approve cetak laporan',
                'code'    => 200
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return writeLog($th->getMessage());
        }
    }

    public function multiapprove()
    {
        // cek validasi
        $validator = Validator::make($this->request->all(), [
            'uuid' => 'required',
        ]);

        if ($validator->fails()) {
            return writeLogValidation($validator->errors());
        }

        DB::beginTransaction();
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

            $approval = explode(',',$this->request->uuid);
            foreach($approval as $item) {
                $laporanApprove = LaporanCetakApproval::where('uuid', $item)->first();
                if (!$laporanApprove) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data tidak ditemukan',
                        'code'    => 404,
                    ]);
                }
                
                $laporanApprove->update([
                    'approved_by'  => $uuid,
                    'approved_at'  => date('Y-m-d H:i:s'),
                    'is_approved'  => 1,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil approve cetak laporan',
                'code'    => 200
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return writeLog($th->getMessage());
        }
    }
}