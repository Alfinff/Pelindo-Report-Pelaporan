<?php

namespace App\Imports;

use App\Models\FormIsian;
use App\Models\FormPilihan;
use App\Models\Jadwal;
use App\Models\Laporan;
use App\Models\LaporanCetakApproval;
use App\Models\LaporanIsi;
use App\Models\LaporanRangeJam;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class LaporanIsian implements ToCollection, WithStartRow
{
    protected $tanggal;
    protected $tipe;
    protected $range;

    public function __construct($tanggal, $tipe)
    {
        $this->tanggal = $tanggal;
        $this->tipe = $tipe;
        $this->range = LaporanRangeJam::orderByRaw(
            "CASE kode_shift
            WHEN 'P' THEN 1
            WHEN 'S' THEN 2
            ELSE 3
            END ASC"
        )->orderBy('time','asc')->get();
    }

    public function startRow(): int
    {
        return 3;
    }

    public function collection(Collection $rows)
    {
        $tanggal = $this->tanggal;
        $tipe = $this->tipe;
        $range = $this->range;
        
        //Loop untuk insert laporan
        $laporanIns = collect();
        foreach($range as $jam){
            //randomize penggarap
            $jadwal = Jadwal::with('user', 'shift')->whereDate('tanggal', $tanggal)->where('kode_shift',$jam->kode_shift)->inRandomOrder()->first();
            $hour = Carbon::parse($jam->time)->addMinutes(3)->format('H:i:s');
            $timestamptz = $tanggal.' '.$hour;

            $laporan = new Laporan;
            $laporan->uuid = generateUuid();
            $laporan->jadwal_shift_id = $jadwal->uuid;
            $laporan->form_jenis = $tipe;
            $laporan->range_jam_kode = $jam->kode;
            $laporan->user_id = $jadwal->user_id;
            $laporan->created_at = $timestamptz;
            $laporan->save();

            //push ke data Laporan
            $object = new \stdClass();
            $laporan = Laporan::where('uuid',$laporan->uuid)->first();
            $object->id = $laporan->uuid;
            $object->created_at = $laporan->created_at;
            $object->isilaporan = collect();
            $laporanIns->push($object);

            //Auto Approval Request
            if($jam->kode == 'H') {
                $cekRequest = LaporanCetakApproval::where('jenis', 'ALL')->whereDate('tanggal', $tanggal)->first();
                if(!$cekRequest) {
                    $approval = LaporanCetakApproval::create([
                        'uuid'     => generateUuid(),
                        'user_id'  => $jadwal->user_id,
                        'jenis'  => 'ALL',
                        'tanggal'  => $tanggal,
                        'created_at' => $timestamptz,
                        'is_approved' => 0,
                        'soft_delete' => 0
                    ]);
                }
            }
        }
        // dd($laporanIns);

        //Isi laporan
        foreach ($rows as $row)
        {
            $uuid = $row[2];
            if($uuid!=''){
                $cekForm = FormIsian::where('uuid', $uuid)->where('form_jenis',$tipe)->first();
                if($cekForm){
                    $jenisinput='pilihan';
                    if($cekForm->tipe!='ISIAN') {
                        //Cek inputan PAC
                        if($tipe=='FCT'){
                            $cekPAC = FormIsian::where('uuid', $uuid)->where('judul','like','PAC %')->first();
                            if($cekPAC){
                                $jenisinput='pac';
                            }
                        }
                    } else if($cekForm->tipe=='ISIAN') {
                        $jenisinput='isian';
                    }
                    // for($i = 3; $i < $range->count()+3; $i++) {
                    for($i = 3; $i < $range->count()+3; $i++) {
                        if(!empty($row[$i])){
                            $pilihan = null;
                            $isi = null;
                            if($jenisinput!='isian'){
                                if($jenisinput=='pac' && is_numeric($row[$i])){
                                    $pilihan = FormPilihan::where('isian_id',$uuid)->where('pilihan','RUNNING')->first();
                                    $isi=$row[$i];
                                }else{
                                    $pilihan = FormPilihan::where('isian_id',$uuid)->where('pilihan',$row[$i])->first();
                                }
                            }else{
                                $isi=$row[$i];
                            }
                            //push ke data isian laporan
                            $object = new \stdClass();
                            $object->form_isian_id = $uuid;
                            $object->pilihan_id = $pilihan ? $pilihan->uuid : '';
                            $object->isian = $isi ?? '';
                            $object->keterangan = '';
                            $laporanIns[$i-3]->isilaporan->push($object);
                        }
                    }
                }else{
                    throw new Exception('UUID '.$uuid.' tidak ditemukan');
                }
            }
        }
        // dd($laporanIns);

        $laporanisiinsert = collect();
        foreach($laporanIns as $laporan){
            // dd($laporan);
            foreach($laporan->isilaporan as $item) {
                // dd($laporan,$item);
                $laporanIsi = new LaporanIsi;
                $laporanIsi->uuid = generateUuid();
                $laporanIsi->laporan_id = $laporan->id;
                $laporanIsi->form_isian_id = $item->form_isian_id;
                $laporanIsi->pilihan_id = $item->pilihan_id;
                $laporanIsi->isian = $item->isian;
                $laporanIsi->keterangan = $item->keterangan;
                $laporanIsi->created_at = $laporan->created_at;
                $laporanIsi->save();
                //debug
                // $object = new \stdClass();
                // $laporanIsi = LaporanIsi::where('uuid',$laporanIsi->uuid)->first();
                // $object->uuid = $laporanIsi->uuid;
                // $object->created_at = $laporanIsi->created_at;
                // $laporanisiinsert->push($object);
            }
        }

        //approval request

    }
}
