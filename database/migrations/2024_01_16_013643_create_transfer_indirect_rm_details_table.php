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
        Schema::create('transfer_indirect_rm_details', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->string('deleted_by', 9)->nullable();
            $table->string('updated_by', 9)->nullable();
            $table->string('created_by', 9);
            $table->unsignedBigInteger('id_header');
            $table->string('model', 95)->nullable();
            $table->string('assy_code', 50)->nullable();
            $table->string('part_code', 50);
            $table->string('part_name', 50)->nullable();
            $table->float('usage_qty', 18, 4)->nullable();
            $table->float('req_qty', 18, 4);
            $table->string('job', 50)->nullable();
            $table->float('sup_qty', 18, 4);

            $table->foreign('id_header')->references('id')->on('transfer_indirect_rm_headers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_indirect_rm_details');
    }
};
