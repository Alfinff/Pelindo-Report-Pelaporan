<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLaporanShiftTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ms_laporan_shift', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 191)->unique();
            $table->string('judul')->nullable();
            $table->string('isi')->nullable();
            $table->string('form_jenis')->nullable();
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
        Schema::dropIfExists('ms_laporan_shift');
    }
}
