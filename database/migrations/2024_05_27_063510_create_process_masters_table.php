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
        Schema::create('process_masters', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('line_code', 15);
            $table->string('assy_code', 45);
            $table->string('model_code', 25);
            $table->string('model_type', 15);
            $table->string('process_code', 15);
            $table->smallInteger('process_seq')->nullable();
            $table->decimal('cycle_time', 14, 2);
            $table->dateTime('deleted_at')->nullable();
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
        Schema::dropIfExists('process_masters');
    }
};
