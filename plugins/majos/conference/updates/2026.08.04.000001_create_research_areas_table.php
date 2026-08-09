<?php namespace Majos\Conference\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Facades\Db;
use Illuminate\Support\Facades\Schema;

class CreateResearchAreasTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('majos_conference_research_areas')) {
            Schema::create('majos_conference_research_areas', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('parent_id')->unsigned()->nullable()->index();
                $table->string('name');
                $table->string('code')->nullable();
                $table->text('description')->nullable();
                $table->integer('sort_order')->unsigned()->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('majos_conference_submissions', 'research_area')) {
            Schema::table('majos_conference_submissions', function (Blueprint $table) {
                $table->string('research_area')->nullable()->after('category');
                $table->string('research_topic')->nullable()->after('research_area');
            });
        }

        $this->seedDefaultResearchAreas();
    }

    protected function seedDefaultResearchAreas()
    {
        if (Db::table('majos_conference_research_areas')->count() > 0) {
            return;
        }

        $areas = [
            ['code' => 'A', 'name' => 'Radiation Protection in Healthcare',
             'description' => 'Abstracts may address justification, optimization, diagnostic reference levels and use of AI in any of the following topics:',
             'topics' => [
                ['code' => 'A1', 'name' => 'Standards, Regulations, Quality Control and Quality Assurance'],
                ['code' => 'A2', 'name' => 'Radiation protection of patients and staff in diagnostic radiography, fluoroscopy, mammography, dental, and CT'],
                ['code' => 'A3', 'name' => 'Radiation protection of patients and staff in radiotherapy, including brachytherapy'],
                ['code' => 'A4', 'name' => 'Radiation protection of patients, staff, and the public in diagnostic and therapeutic nuclear medicine and hybrid imaging'],
                ['code' => 'A5', 'name' => 'Radiation protection in medical exposures of children and pregnant women'],
                ['code' => 'A6', 'name' => 'Radiation protection of patients and staff in fluoroscopy-guided interventional and cardiology procedures'],
                ['code' => 'A7', 'name' => 'Learning from unintended and accidental medical exposures in medicine'],
                ['code' => 'A8', 'name' => 'Non-Ionizing Radiation in Healthcare (Magnetic Resonance Imaging (MRI), Ultrasound and, Laser Radiation)'],
             ]],
            ['code' => 'B', 'name' => 'Radiation Protection in Industrial and Other Practices',
             'description' => null,
             'topics' => [
                ['code' => 'B1', 'name' => 'Standards, Directives and Regulations'],
                ['code' => 'B2', 'name' => 'Industrial and agricultural applications (e.g., non-destructive testing, gauging and sterilization)'],
                ['code' => 'B3', 'name' => 'Veterinary applications in diagnostic and treatment procedures'],
                ['code' => 'B4', 'name' => 'Radiation protection in aerospace and air/space travel'],
                ['code' => 'B5', 'name' => 'Military applications and security screening'],
                ['code' => 'B6', 'name' => 'Safety and security of radioactive sources'],
                ['code' => 'B7', 'name' => 'Transportation of small amounts of radioactive material within these practices'],
                ['code' => 'B8', 'name' => 'Dosimetry and measurements'],
             ]],
            ['code' => 'C', 'name' => 'RADON and Naturally Occurring Radiation (NORM)',
             'description' => 'Abstracts may cover any of the following areas on protection and exposure:',
             'topics' => [
                ['code' => 'C1', 'name' => 'Legal aspects, standards and regulations'],
                ['code' => 'C2', 'name' => 'Radon and Thoron in the workplace and wider environment'],
                ['code' => 'C3', 'name' => 'NORM and natural background radiation'],
                ['code' => 'C4', 'name' => 'Use, recovery and re-use of materials containing NORM'],
                ['code' => 'C5', 'name' => 'Transport of materials containing NORM'],
                ['code' => 'C6', 'name' => 'Emerging industries or geographical areas with NORM'],
             ]],
            ['code' => 'D', 'name' => 'Communication, Stakeholder Involvement and Capacity Building',
             'description' => 'Abstracts may cover any of these areas or a combination:',
             'topics' => [
                ['code' => 'D1', 'name' => 'Education research and qualification standardization'],
                ['code' => 'D2', 'name' => 'Knowledge management and knowledge transfer'],
                ['code' => 'D3', 'name' => 'Experience from radiation protection societies'],
                ['code' => 'D4', 'name' => 'Stakeholder participation and ethical considerations'],
                ['code' => 'D5', 'name' => 'Risk perception and communication'],
             ]],
            ['code' => 'E', 'name' => 'Radiation Protection in Non-Ionizing Radiation Applications',
             'description' => null,
             'topics' => [
                ['code' => 'E1', 'name' => 'Standards, Directives and Regulations'],
                ['code' => 'E2', 'name' => 'Electromagnetic and Static Fields: From power transmission to 5G applications, electromobility and radar systems'],
                ['code' => 'E3', 'name' => 'Optical Radiation: UV, Infrared, LEDs, data transmission, laser radiation, applications for medical and wellness purposes (including UV or LED curing lamps used in manicures and pedicures)'],
             ]],
        ];

        $sort = 0;
        foreach ($areas as $area) {
            $sort++;
            $areaId = Db::table('majos_conference_research_areas')->insertGetId([
                'parent_id' => null,
                'name' => $area['name'],
                'code' => $area['code'],
                'description' => $area['description'],
                'sort_order' => $sort,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $childSort = 0;
            foreach ($area['topics'] as $topic) {
                $childSort++;
                Db::table('majos_conference_research_areas')->insert([
                    'parent_id' => $areaId,
                    'name' => $topic['name'],
                    'code' => $topic['code'],
                    'description' => null,
                    'sort_order' => $childSort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        if (Schema::hasColumn('majos_conference_submissions', 'research_topic')) {
            Schema::table('majos_conference_submissions', function (Blueprint $table) {
                $table->dropColumn(['research_area', 'research_topic']);
            });
        }

        Schema::dropIfExists('majos_conference_research_areas');
    }
}
