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
        Schema::create('keikaku_draft_data', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->string('line_code', 15);
            $table->date('production_date');
            $table->smallInteger('seq');
            $table->string('model_code', 20);
            $table->string('wo_code', 5);
            $table->string('wo_full_code', 50)->nullable();
            $table->string('item_code', 50);
            $table->integer('lot_size');
            $table->integer('plan_qty')->nullable();
            $table->string('type', 100);
            $table->string('specs', 50);
            $table->string('specs_side', 50)->nullable();
            $table->float('cycle_time', 14, 2)->nullable();
            $table->string('packaging', 5)->nullable();
            $table->string('created_by', 9);
            $table->string('revision', 5);
            $table->string('updated_by', 9)->nullable();
            $table->string('deleted_by', 9)->nullable();
            $table->date('start_production_date');
            $table->string('shift', 1);
            $table->string('file_year', 4);
            $table->string('file_month', 2);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('keikaku_draft_data');
    }
};
