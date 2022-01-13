<?php

namespace App\Imports;

use App\Models\Informasi;
use App\Models\Jadwal;
use App\Models\Laporan;
use App\Models\LaporanRangeJam;
use App\Models\LaporanShift;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;

class CatatanIsian implements ToCollection, WithStartRow
{
    protected $month;
    protected $year;
    protected $jamCatatan;
    protected $kode_shift;

    public function __construct($month, $year)
    {
        $this->month = $month;
        $this->year = $year;
        $this->jamCatatan = ['15:57:00','23:57:00','07:57:00'];
        $this->kode_shift = ['P','S','M'];
    }

    public function startRow(): int
    {
        return 2;
    }

    public function collection(Collection $rows)
    {
        //
        $m = date($this->month);
        $y = date($this->year);
        $jam = $this->jamCatatan;
        $kode = $this->kode_shift;
        $d = cal_days_in_month(CAL_GREGORIAN,$m,$y);
        $itr = 1;
        // $a = [];
        foreach ($rows as $row)
        {
            if($itr<=$d){
                // $b=collect();
                for($i = 0;$i < 3;$i++){
                    $j=$i+1;
                    if(!empty($row[$j])){
                        $tgl=str_pad($itr, 2, '0', STR_PAD_LEFT);
                        $tanggal = $y.'-'.$m.'-'.$tgl;
                        $created_at = $tanggal.' '.$jam[$i];
                        $kd = $kode[$i];
                        $laporan = Laporan::with('user')->whereDate('created_at', $tanggal)->whereHas('jadwal',function ($qq) use($kd) {
                            $qq->where('kode_shift', $kd);
                        })->orderBy('created_at','DESC')->orderBy('updated_at','DESC');
                        $query = str_replace(array('?'), array('\'%s\''), $laporan->toSql());
                        $query = vsprintf($query, $laporan->getBindings());
                        // dd($query);
                        $laporan = $laporan->first();

                        // input laporan shift
                        if(!$laporan){
                            //paksa insert walaupun belum ada laporan
                            $jadwal = Jadwal::with('user', 'shift')->where('tanggal', $tanggal)->where('kode_shift',$kd)->inRandomOrder()->first();
                            $catatan = new LaporanShift;
                            $catatan->uuid = generateUuid();
                            $catatan->judul = 'Catatan '.$j;
                            $catatan->isi = $row[$j];
                            $catatan->jadwal_shift_id = $jadwal->uuid;
                            $catatan->user_id = $jadwal->user_id;
                            $catatan->created_at = $created_at;
                            $catatan->save();
                        }else{
                            $catatan = new LaporanShift;
                            $catatan->uuid = generateUuid();
                            $catatan->judul = 'Catatan '.$j;
                            $catatan->isi = $row[$j];
                            $catatan->jadwal_shift_id = $laporan->jadwal_shift_id;
                            $catatan->user_id = $laporan->user_id;
                            $catatan->created_at = $created_at;
                            $catatan->save();
                        }
                        
                        $ctt = LaporanShift::where('uuid',$catatan->uuid)->first();
                        // create informasi notif
                        $informasi = Informasi::create([
                            'uuid'         => generateUuid(),
                            'info_id'      => $ctt->uuid,
                            'judul'        => $ctt->judul,
                            'isi'          => $ctt->isi,
                            'jenis'        => env('NOTIF_CATATAN'),
                        ]);
                        
                        //debugging
                        // $object = new \stdClass();
                        // $object->id = $ctt->uuid;
                        // $object->judul = $ctt->judul;
                        // $object->isi = $ctt->isi;
                        // $object->user = $ctt->user->nama;
                        // $object->created_at = $ctt->created_at;
                        // $object->sql = $query;
                        // $object->tgl = $tgl;
                        // $b->push($object);
                    }
                }
                $itr=$itr+1;
                // array_push($a,$b);
            }
            // dd($a);
        }
    }
}
