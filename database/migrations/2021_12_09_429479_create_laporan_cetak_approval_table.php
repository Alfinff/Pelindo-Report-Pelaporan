<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLaporanCetakApprovalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ms_laporan_cetak_approval', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 191)->unique();
            $table->string('user_id');
            $table->string('jenis');
            $table->datetime('tanggal');
            $table->string('approved_by')->nullable();
            $table->string('approved_at')->nullable();
            $table->integer('is_approved')->default(0);
            $table->integer('soft_delete')->default(0);
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
        Schema::dropIfExists('ms_laporan_cetak_approval');
    }
}
