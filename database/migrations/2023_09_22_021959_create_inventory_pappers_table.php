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
        Schema::create('inventory_pappers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('nomor_urut');
            $table->string('item_code', 50);
            $table->double('item_qty');
            $table->integer('item_box');
            $table->string('checker_id', 9);
            $table->string('auditor_id', 9);
            $table->string('created_by', 9);
            $table->string('updated_by', 9)->nullable();
            $table->string('deleted_by', 9)->nullable();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inventory_pappers');
    }
};
