<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 191);
            $table->string('type', 32)->default('kumas'); // kumas/aksesuar/etiket
            $table->string('unit', 16)->default('adet');   // metre/adet/kg
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('current_stock', 14, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
