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
        Schema::create('keikaku_model_changes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->date('production_date');
            $table->dateTime('running_at');
            $table->string('wo_code', 50);
            $table->string('line_code', 15);
            $table->string('process_code', 15);
            $table->smallInteger('process_seq')->nullable();
            $table->smallInteger('seq_data')->nullable();
            $table->char('change_flag', 1);
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
        Schema::dropIfExists('keikaku_model_changes');
    }
};
