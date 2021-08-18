<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInformasiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ms_informasi', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 191);
            $table->string('info_id', 191);
            $table->string('judul')->nullable();
            $table->text('isi')->nullable();
            $table->string('jenis')->nullable();
            $table->text('ikon')->nullable();
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
        Schema::dropIfExists('ms_informasi');
    }
}
