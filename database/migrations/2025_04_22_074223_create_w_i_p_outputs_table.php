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
        Schema::create('w_i_p_outputs', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->date('production_date');
            $table->dateTime('running_at');
            $table->string('shift_code', 1);
            $table->string('wo_code', 5);
            $table->string('wo_full_code', 50);
            $table->string('item_code', 50);
            $table->string('line_code', 15);
            $table->string('model_code', 15);
            $table->string('type', 50);
            $table->string('specs', 50);
            $table->float('ok_qty', 18, 1);
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
        Schema::dropIfExists('w_i_p_outputs');
    }
};
