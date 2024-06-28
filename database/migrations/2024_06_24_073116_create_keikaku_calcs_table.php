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
        Schema::create('keikaku_calcs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->string('shift_code', 2);
            $table->date('production_date');
            $table->dateTime('calculation_at');
            $table->string('line_code', 15);
            $table->float('worktype1');
            $table->float('worktype2');
            $table->float('worktype3');
            $table->float('worktype4');
            $table->float('worktype5');
            $table->float('worktype6');
            $table->string('flag_mot', 2); // change model (M) or Overtime (OT)
            $table->float('efficiency');
            $table->string('created_by', 9);
            $table->string('updated_by', 9)->nullable();
            $table->string('deleted_by', 9)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keikaku_calcs');
    }
};
