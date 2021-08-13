<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLaporanDikerjakanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ms_laporan_dikerjakan', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 191)->unique();
            $table->string('jadwal_shift_id');
            $table->string('form_jenis');
            $table->string('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ms_laporan_dikerjakan');
    }
}
