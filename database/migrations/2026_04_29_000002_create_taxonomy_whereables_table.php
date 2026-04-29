<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('taxonomy_whereables', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('taxonomy_where_id');
            $table->morphs('taxonomy_whereable');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('taxonomy_whereables');
    }
};
