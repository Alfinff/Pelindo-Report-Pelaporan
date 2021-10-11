<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterLaporanDikerjakanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ms_laporan_dikerjakan', function (Blueprint $table) {
            $table->string('range_jam_kode')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ms_laporan_dikerjakan', function (Blueprint $table) {
            $table->dropColumn('range_jam_kode');
        });
    }
}
