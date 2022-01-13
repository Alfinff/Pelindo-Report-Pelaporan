<?php

namespace App\Http\Controllers;

use App\Imports\CatatanIsian;
use App\Imports\LaporanIsian;
use App\Models\Laporan;
use Carbon\Carbon;
use App\Models\LaporanCetakApproval;
use App\Models\LaporanShift;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class LaporanIsianController extends Controller
{
   
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function store()
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
            else 
            {
                $validator = Validator::make($this->request->all(), [
                    'file' => 'required|mimes:csv,xls,xlsx',
                    'tanggal' => 'required',
                    'tipe' => 'required'
                ]);
        
                if ($validator->fails()) {
                    return writeLogValidation($validator->errors());
                }
                $tanggal = $this->request->tanggal;
                $tipe = $this->request->tipe;
                //Validasi laporan tersebut sudah diisi apa belum
                $laporan = Laporan::whereDate('created_at',$tanggal)->where('form_jenis',$tipe)->first();
                if($laporan){
                    throw new Exception('Laporan '.$tipe.' pada tanggal '.$tanggal.' sudah diinputkan !');
                }
                try{
                    $current   = Carbon::now()->format('YmdHs');
                    $file = $this->request->file;
                    $nama_file = $current.'_'.$file->getClientOriginalName();
                    $file->move('laporan_isi',$nama_file);

                    Excel::import(new LaporanIsian($tanggal, $tipe), public_path('/laporan_isi/'.$nama_file));
                    unlink(public_path('/laporan_isi/'.$nama_file));
                    return response()->json([
                        'success' => true,
                        'message' => 'Laporan Dikirimkan',
                        'code'    => 201
                    ]);
                } catch (\Throwable $th) {
                    return writeLog($th->getMessage());
                }
            }
        } catch (\Throwable $th) {
            return writeLog($th->getMessage());
        }
    }

    public function catatan()
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
            else 
            {
                $validator = Validator::make($this->request->all(), [
                    'file' => 'required|mimes:csv,xls,xlsx',
                    'month' => 'required',
                    'year' => 'required'
                ]);
        
                if ($validator->fails()) {
                    return writeLogValidation($validator->errors());
                }
                
                $month = $this->request->month;
                $year = $this->request->year;
                //Validasi catatan tersebut sudah diisi apa belum
                $catatan = LaporanShift::whereMonth('created_at', $month)->whereYear('created_at', $year)->first();
                if($catatan){
                    throw new Exception('Catatan Shift pada bulan '.$month.'-'.$year.' sudah diinputkan !');
                }
                try{
                    $current   = Carbon::now()->format('YmdHs');
                    $file = $this->request->file;
                    $nama_file = $current.'_'.$file->getClientOriginalName();
                    $file->move('laporan_isi',$nama_file);

                    Excel::import(new CatatanIsian($month, $year), public_path('/laporan_isi/'.$nama_file));
                    unlink(public_path('/laporan_isi/'.$nama_file));
                    return response()->json([
                        'success' => true,
                        'message' => 'Catatan Shift Dikirimkan',
                        'code'    => 201
                    ]);
                } catch (\Throwable $th) {
                    return writeLog($th->getMessage());
                }
            }
        } catch (\Throwable $th) {
            return writeLog($th->getMessage());
        }
    }
}