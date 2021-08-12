<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLaporanIsiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ms_laporan_isi', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 191)->unique();
            $table->string('laporan_id');
            $table->string('form_isian_id');
            $table->string('pilihan_id');
            $table->string('keterangan')->nullable();
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
        Schema::dropIfExists('ms_laporan_isi');
    }
}
