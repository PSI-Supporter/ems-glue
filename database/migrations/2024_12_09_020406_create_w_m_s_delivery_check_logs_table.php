<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('WMS_DLVCHK_LOGS', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('dlv_id', 30);
            $table->string('dlv_itmcd', 30);
            $table->string('dlv_refno', 30);
            $table->integer('dlv_qty');
            $table->string('dlv_PIC', 50)->nullable();
            $table->dateTime('dlv_date')->nullable();
            $table->string('dlv_PicSend', 50)->nullable();
            $table->dateTime('dlv_DateSend')->nullable();
            $table->integer('dlv_stchk')->nullable();
            $table->integer('dlv_stcfm')->nullable();
            $table->string('dlv_transno', 20)->nullable();
            $table->string('created_by', 9);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('WMS_DLVCHK_LOGS');
    }
};
