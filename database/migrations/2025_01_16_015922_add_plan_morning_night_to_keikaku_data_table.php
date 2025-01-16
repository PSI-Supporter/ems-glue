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
        Schema::table('keikaku_data', function (Blueprint $table) {
            $table->integer('plan_morning_qty')->nullable();
            $table->integer('plan_night_qty')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('keikaku_data', function (Blueprint $table) {
            //
        });
    }
};
