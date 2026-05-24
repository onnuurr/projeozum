<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 220)->nullable()->after('name');
        });

        // Mevcut satırlar için slug üret; çakışırsa id ekleyerek tekilleştir.
        $rows = DB::table('products')->select('id', 'name')->get();
        $used = [];
        foreach ($rows as $row) {
            $base = Str::slug($row->name ?: ('urun-' . $row->id), '-', 'tr');
            if ($base === '') {
                $base = 'urun-' . $row->id;
            }
            $slug = $base;
            $i    = 2;
            while (in_array($slug, $used, true) || DB::table('products')->where('slug', $slug)->where('id', '!=', $row->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $used[] = $slug;
            DB::table('products')->where('id', $row->id)->update(['slug' => $slug]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', 220)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
