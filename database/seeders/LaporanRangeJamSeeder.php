<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\LaporanRangeJam;

class LaporanRangeJamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $abjad = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'Q', 'Y', 'Z'];

        for($i=0;$i<=23;$i++) {
            LaporanRangeJam::create([
                'uuid'  => generateUuid(),
                'time' => $i.':00',
                'kode' => $abjad[$i],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
