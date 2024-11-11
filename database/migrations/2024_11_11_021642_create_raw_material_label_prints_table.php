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
        Schema::create('raw_material_label_prints', function (Blueprint $table) {
            $table->id();
            $table->string('code', 21);
            $table->string('item_code', 50);
            $table->string('doc_code', 50);
            $table->string('parent_code', 21)->nullable();
            $table->float('quantity', 12, 3);
            $table->string('lot_code', 50);
            $table->string('action', 15);
            $table->string('created_by', 9);
            $table->string('pc_name', 50)->nullable();
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
        Schema::dropIfExists('raw_material_label_prints');
    }
};
