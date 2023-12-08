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
        Schema::create('raw_material_labels', function (Blueprint $table) {
            $table->string('code', 21)->primary();
            $table->string('item_code', 50);
            $table->string('doc_code', 50);
            $table->string('parent_code', 21)->nullable();
            $table->float('quantity', 12, 3);
            $table->string('lot_code', 50);
            $table->string('splitted', 1)->nullable();
            $table->string('combined', 1)->nullable();
            $table->string('composed', 1)->nullable();
            $table->string('created_by', 9);
            $table->string('updated_by', 9)->nullable();
            $table->string('deleted_by', 9)->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('raw_material_labels');
    }
};
