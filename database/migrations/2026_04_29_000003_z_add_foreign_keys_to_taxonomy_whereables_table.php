<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('taxonomy_whereables', function (Blueprint $table) {
            $table->foreign(['taxonomy_where_id'])
                ->references(['id'])
                ->on('taxonomy_wheres')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::table('taxonomy_whereables', function (Blueprint $table) {
            $table->dropForeign('taxonomy_whereables_taxonomy_where_id_foreign');
        });
    }
};
