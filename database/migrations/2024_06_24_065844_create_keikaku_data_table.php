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
        Schema::create('keikaku_data', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->string('line_code', 15);
            $table->date('production_date');
            $table->smallInteger('seq');
            $table->string('model_code', 15);
            $table->string('wo_code', 5);
            $table->string('wo_full_code', 50);
            $table->string('item_code', 50);
            $table->integer('lot_size');
            $table->integer('plan_qty');
            $table->integer('actual_qty')->nullable();
            $table->string('type', 50);
            $table->string('specs', 50);
            $table->string('specs_side', 50);
            $table->float('cycle_time', 14, 2);
            $table->string('packaging', 5);
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
        Schema::dropIfExists('keikaku_data');
    }
};
