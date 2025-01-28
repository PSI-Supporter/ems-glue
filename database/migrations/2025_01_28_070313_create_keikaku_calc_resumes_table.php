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
        Schema::create('keikaku_calc_resumes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->date('production_date');
            $table->string('line_code', 15);
            $table->float('total_plan_worktime_morning');
            $table->float('total_plan_worktime_night');
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
        Schema::dropIfExists('keikaku_calc_resumes');
    }
};
