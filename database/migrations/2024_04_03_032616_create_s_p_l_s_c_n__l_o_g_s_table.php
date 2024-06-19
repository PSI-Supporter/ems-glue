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
        Schema::create('SPLSCN_LOG', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('SPLSCN_ID', 50);
            $table->string('SPLSCN_DATATYPE', 1);
            $table->decimal('SPLSCN_OLDQTY', 12, 3);
            $table->decimal('SPLSCN_NEWQTY', 12, 3);
            $table->string('created_by', 9);
            $table->string('updated_by', 9)->nullable();
            $table->string('deleted_by', 9)->nullable();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('SPLSCN_LOG');
    }
};
