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
        Schema::create('production_times', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->string('shift_code', 2);
            $table->date('production_date');
            $table->string('line_code', 15);
            $table->float('working_hours', 16, 1);
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
        Schema::dropIfExists('production_times');
    }
};
