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
        Schema::create('keikaku_calc_templates', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('calculation_at');
            $table->string('line_code', 15);
            $table->string('name', 25);
            $table->float('worktype1');
            $table->float('worktype2');
            $table->float('worktype3');
            $table->float('worktype4');
            $table->float('worktype5');
            $table->float('worktype6');
            $table->string('status', 1); // active, non active
            $table->string('category', 2); // friday, non friday
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
        Schema::dropIfExists('keikaku_calc_templates');
    }
};
