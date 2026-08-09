<?php namespace Majos\Conference\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Db;
use Illuminate\Support\Facades\Schema;

class CreatePresentationTypesTable extends Migration
{
    public function up()
    {
        Schema::create('majos_conference_presentation_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        $types = [
            ['name' => 'Oral Presentation', 'description' => null],
            ['name' => 'Poster-Oral Presentation', 'description' => null],
            ['name' => 'Poster Presentation', 'description' => null],
            ['name' => 'Young Scientist Competition', 'description' => null],
        ];

        foreach ($types as $type) {
            $type['created_at'] = now();
            $type['updated_at'] = now();
            Db::table('majos_conference_presentation_types')->insert($type);
        }
    }

    public function down()
    {
        Schema::dropIfExists('majos_conference_presentation_types');
    }
}
