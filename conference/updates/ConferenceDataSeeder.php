<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;
use Majos\Conference\Models\ConferenceDay;
use Majos\Conference\Models\Venue;
use Majos\Conference\Models\Speaker;
use Majos\Conference\Models\Session;

class ConferenceDataSeeder extends Seeder
{
    public function run()
    {
        // Reset tables in FK-safe order for MySQL.
        Schema::disableForeignKeyConstraints();

        try {
            \DB::table('majos_conference_session_speaker')->truncate();
            Session::truncate();
            Speaker::truncate();
            Venue::truncate();
            ConferenceDay::truncate();
        }
        finally {
            Schema::enableForeignKeyConstraints();
        }

        // Create Venues
        $mainHall = Venue::create([
            'name' => 'Main Hall',
            'slug' => \Str::slug('Main Hall'),
            'location' => 'Building A, Ground Floor',
            'capacity' => 500,
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $workshopRoom = Venue::create([
            'name' => 'Workshop Room A',
            'slug' => \Str::slug('Workshop Room A'),
            'location' => 'Building B, First Floor',
            'capacity' => 100,
            'is_published' => true,
            'sort_order' => 2,
        ]);

        $auditorium = Venue::create([
            'name' => 'Auditorium',
            'slug' => \Str::slug('Auditorium'),
            'location' => 'Main Building',
            'capacity' => 300,
            'is_published' => true,
            'sort_order' => 3,
        ]);

        // Create Conference Days (2 days)
        $day1 = ConferenceDay::create([
            'title' => 'Day 1 - Nuclear Energy Summit',
            'date' => now()->addDays(30)->startOfDay(),
            'slug' => 'day-1-nuclear-summit',
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $day2 = ConferenceDay::create([
            'title' => 'Day 2 - Nuclear Technology & Policy',
            'date' => now()->addDays(31)->startOfDay(),
            'slug' => 'day-2-nuclear-tech',
            'sort_order' => 2,
            'is_published' => true,
        ]);

        // Create Speakers (10) - all nuclear energy experts
        $speakers = [];
        $speakerData = [
            [
                'full_name' => 'Dr. Elena Petrova',
                'title' => 'Chief Nuclear Scientist',
                'company' => 'International Atomic Energy Agency',
                'country' => 'Austria',
                'expertise' => 'Nuclear Reactor Design, Safety Systems',
                'email' => 'elena.petrova@iaea.org',
                'linkedin_url' => 'https://linkedin.com/in/elenapetrova',
                'website_url' => 'https://iaea.org',
                'biography' => 'Dr. Petrova has 20 years of experience in nuclear reactor safety and is a leading advisor for nuclear energy policy worldwide.',
                'is_featured' => true,
            ],
            [
                'full_name' => 'Dr. James Okonkwo',
                'title' => 'Nuclear Engineer',
                'company' => 'TerraPower',
                'country' => 'Nigeria',
                'expertise' => 'Next-Gen Reactors, Molten Salt Technology',
                'email' => 'jokonkwo@terrapower.com',
                'linkedin_url' => 'https://linkedin.com/in/jamesokonkwo',
                'website_url' => 'https://terrapower.com',
                'biography' => 'James leads advanced reactor design at TerraPower, focusing on innovative nuclear solutions for developing nations.',
                'is_featured' => true,
            ],
            [
                'full_name' => 'Dr. Sarah Mitchell',
                'title' => 'Radiation Safety Director',
                'company' => 'World Nuclear Association',
                'country' => 'United Kingdom',
                'expertise' => 'Radiation Protection, Nuclear Medicine',
                'email' => 's.mitchell@world-nuclear.org',
                'linkedin_url' => 'https://linkedin.com/in/sarahmitchell',
                'website_url' => 'https://world-nuclear.org',
                'biography' => 'Sarah is an expert in radiation safety protocols and nuclear applications in medicine.',
                'is_featured' => false,
            ],
            [
                'full_name' => 'Prof. Hiroshi Tanaka',
                'title' => 'Nuclear Policy Analyst',
                'company' => 'Japan Atomic Energy Agency',
                'country' => 'Japan',
                'expertise' => 'Nuclear Policy, Waste Management',
                'email' => 'hiroshi.tanaka@jaea.go.jp',
                'linkedin_url' => 'https://linkedin.com/in/hiroshitanaka',
                'website_url' => 'https://jaea.go.jp',
                'biography' => 'Professor Tanaka advises the Japanese government on nuclear energy policy and radioactive waste solutions.',
                'is_featured' => true,
            ],
            [
                'full_name' => 'Dr. Maria Garcia',
                'title' => 'Fusion Research Lead',
                'company' => 'ITER Organization',
                'country' => 'Spain',
                'expertise' => 'Nuclear Fusion, Plasma Physics',
                'email' => 'm.garcia@iter.org',
                'linkedin_url' => 'https://linkedin.com/in/mariagarcia',
                'website_url' => 'https://iter.org',
                'biography' => 'Maria leads fusion research at ITER, working towards sustainable nuclear fusion energy.',
                'is_featured' => false,
            ],
            [
                'full_name' => 'Dr. Robert Chen',
                'title' => 'Nuclear Materials Scientist',
                'company' => 'Oak Ridge National Laboratory',
                'country' => 'United States',
                'expertise' => 'Nuclear Materials, Fuel Cycle',
                'email' => 'robert.chen@ornl.gov',
                'linkedin_url' => 'https://linkedin.com/in/robertchen',
                'website_url' => 'https://ornl.gov',
                'biography' => 'Robert specializes in advanced nuclear materials and fuel cycle research at ORNL.',
                'is_featured' => false,
            ],
            [
                'full_name' => 'Dr. Anna Kowalski',
                'title' => 'Nuclear Energy Economist',
                'company' => 'Nuclear Energy Institute',
                'country' => 'Poland',
                'expertise' => 'Nuclear Economics, Energy Markets',
                'email' => 'a.kowalski@nei.org',
                'linkedin_url' => 'https://linkedin.com/in/annakowalski',
                'website_url' => 'https://nei.org',
                'biography' => 'Anna analyzes the economic aspects of nuclear energy and its role in global energy markets.',
                'is_featured' => true,
            ],
            [
                'full_name' => 'Dr. Michael Schmidt',
                'title' => 'Nuclear Security Expert',
                'company' => 'Federal Office for Nuclear Safety',
                'country' => 'Germany',
                'expertise' => 'Nuclear Security, Non-Proliferation',
                'email' => 'm.schmidt@bfs.de',
                'linkedin_url' => 'https://linkedin.com/in/michaelschmidt',
                'website_url' => 'https://bfs.de',
                'biography' => 'Michael is a leading expert on nuclear security and non-proliferation strategies.',
                'is_featured' => false,
            ],
            [
                'full_name' => 'Dr. Lisa Thompson',
                'title' => 'Environmental Radiologist',
                'company' => 'Environmental Protection Agency',
                'country' => 'Canada',
                'expertise' => 'Environmental Radioactivity, Impact Assessment',
                'email' => 'lisa.thompson@epa.ca',
                'linkedin_url' => 'https://linkedin.com/in/lisathompson',
                'website_url' => 'https://epa.ca',
                'biography' => 'Lisa specializes in environmental radioactivity and assessing nuclear facility impacts.',
                'is_featured' => false,
            ],
            [
                'full_name' => 'Dr. Ahmed Hassan',
                'title' => 'Nuclear Power Plant Manager',
                'company' => 'Rosatom',
                'country' => 'Russia',
                'expertise' => 'Plant Operations, Reactor Management',
                'email' => 'a.hassan@rosatom.ru',
                'linkedin_url' => 'https://linkedin.com/in/ahmedhassan',
                'website_url' => 'https://rosatom.ru',
                'biography' => 'Ahmed manages nuclear power plant operations with extensive experience in reactor management.',
                'is_featured' => true,
            ],
        ];

        foreach ($speakerData as $data) {
            $speaker = Speaker::create([
                'full_name' => $data['full_name'],
                'slug' => \Str::slug($data['full_name']),
                'title' => $data['title'],
                'country' => $data['country'],
                'expertise' => $data['expertise'],
                'company' => $data['company'],
                'email' => $data['email'],
                'linkedin_url' => $data['linkedin_url'],
                'website_url' => $data['website_url'],
                'biography' => $data['biography'],
                'photo' => null,
                'is_featured' => $data['is_featured'],
                'is_published' => true,
            ]);
            $speakers[] = $speaker;
        }

        // Create Sessions (10 sessions across 2 days) - all nuclear energy focused
        $sessions = [
            // Day 1 sessions
            [
                'title' => 'Opening Keynote: The Future of Nuclear Energy',
                'description' => 'A visionary talk on the future of nuclear energy, advanced reactors, and their role in achieving net-zero emissions.',
                'conference_day_id' => $day1->id,
                'venue_id' => $mainHall->id,
                'starts_at' => $day1->date->copy()->setTime(9, 0),
                'ends_at' => $day1->date->copy()->setTime(10, 30),
                'session_type' => 'keynote',
                'track' => 'nuclear',
                'capacity' => 500,
                'sort_order' => 1,
                'is_published' => true,
                'speaker_ids' => [$speakers[0]->id],
            ],
            [
                'title' => 'Next-Generation Nuclear Reactors',
                'description' => 'Exploring small modular reactors (SMRs) and Generation IV reactor designs that promise safer, more efficient nuclear power.',
                'conference_day_id' => $day1->id,
                'venue_id' => $mainHall->id,
                'starts_at' => $day1->date->copy()->setTime(11, 0),
                'ends_at' => $day1->date->copy()->setTime(12, 30),
                'session_type' => 'presentation',
                'track' => 'nuclear',
                'capacity' => 500,
                'sort_order' => 2,
                'is_published' => true,
                'speaker_ids' => [$speakers[1]->id],
            ],
            [
                'title' => 'Nuclear Waste Management Solutions',
                'description' => 'Discussion on advanced techniques for radioactive waste disposal and long-term storage strategies.',
                'conference_day_id' => $day1->id,
                'venue_id' => $auditorium->id,
                'starts_at' => $day1->date->copy()->setTime(14, 0),
                'ends_at' => $day1->date->copy()->setTime(15, 30),
                'session_type' => 'panel',
                'track' => 'waste-management',
                'capacity' => 300,
                'sort_order' => 3,
                'is_published' => true,
                'speaker_ids' => [$speakers[3]->id, $speakers[8]->id],
            ],
            [
                'title' => 'Nuclear Energy Economics & Market Trends',
                'description' => 'Analyzing the cost structures, financing models, and competitiveness of nuclear power in modern energy markets.',
                'conference_day_id' => $day1->id,
                'venue_id' => $mainHall->id,
                'starts_at' => $day1->date->copy()->setTime(16, 0),
                'ends_at' => $day1->date->copy()->setTime(17, 30),
                'session_type' => 'keynote',
                'track' => 'economics',
                'capacity' => 500,
                'sort_order' => 4,
                'is_published' => true,
                'speaker_ids' => [$speakers[6]->id],
            ],
            // Day 2 sessions
            [
                'title' => 'Workshop: Nuclear Reactor Safety Systems',
                'description' => 'Hands-on deep dive into modern safety systems, passive cooling, and emergency protocols for nuclear reactors.',
                'conference_day_id' => $day2->id,
                'venue_id' => $workshopRoom->id,
                'starts_at' => $day2->date->copy()->setTime(9, 30),
                'ends_at' => $day2->date->copy()->setTime(12, 30),
                'session_type' => 'workshop',
                'track' => 'safety',
                'capacity' => 100,
                'sort_order' => 1,
                'is_published' => true,
                'speaker_ids' => [$speakers[0]->id, $speakers[5]->id],
            ],
            [
                'title' => 'Nuclear Medicine & Radiation Applications',
                'description' => 'Latest breakthroughs in medical imaging, cancer treatment, and diagnostic applications using nuclear technology.',
                'conference_day_id' => $day2->id,
                'venue_id' => $auditorium->id,
                'starts_at' => $day2->date->copy()->setTime(14, 0),
                'ends_at' => $day2->date->copy()->setTime(15, 30),
                'session_type' => 'presentation',
                'track' => 'medical',
                'capacity' => 300,
                'sort_order' => 2,
                'is_published' => true,
                'speaker_ids' => [$speakers[2]->id],
            ],
            [
                'title' => 'Nuclear Fuel Cycle & Materials',
                'description' => 'Exploring uranium enrichment, fuel fabrication, and advanced materials for next-generation reactors.',
                'conference_day_id' => $day2->id,
                'venue_id' => $workshopRoom->id,
                'starts_at' => $day2->date->copy()->setTime(16, 0),
                'ends_at' => $day2->date->copy()->setTime(17, 30),
                'session_type' => 'workshop',
                'track' => 'fuel-cycle',
                'capacity' => 100,
                'sort_order' => 3,
                'is_published' => true,
                'speaker_ids' => [$speakers[5]->id],
            ],
            [
                'title' => 'Nuclear Policy & Regulation Panel',
                'description' => 'Panel discussion on international nuclear regulations, non-proliferation treaties, and policy frameworks.',
                'conference_day_id' => $day2->id,
                'venue_id' => $mainHall->id,
                'starts_at' => $day2->date->copy()->setTime(10, 0),
                'ends_at' => $day2->date->copy()->setTime(11, 30),
                'session_type' => 'panel',
                'track' => 'policy',
                'capacity' => 500,
                'sort_order' => 4,
                'is_published' => true,
                'speaker_ids' => [$speakers[3]->id, $speakers[7]->id],
            ],
            [
                'title' => 'Nuclear Plant Operations & Management',
                'description' => 'Best practices for operating nuclear power plants efficiently, safely, and in compliance with regulations.',
                'conference_day_id' => $day2->id,
                'venue_id' => $workshopRoom->id,
                'starts_at' => $day2->date->copy()->setTime(13, 0),
                'ends_at' => $day2->date->copy()->setTime(14, 30),
                'session_type' => 'workshop',
                'track' => 'operations',
                'capacity' => 100,
                'sort_order' => 5,
                'is_published' => true,
                'speaker_ids' => [$speakers[9]->id],
            ],
            [
                'title' => 'Nuclear Fusion: Path to Limitless Energy',
                'description' => 'Update on fusion reactor projects like ITER and the latest breakthroughs in achieving sustainable fusion.',
                'conference_day_id' => $day2->id,
                'venue_id' => $auditorium->id,
                'starts_at' => $day2->date->copy()->setTime(15, 0),
                'ends_at' => $day2->date->copy()->setTime(16, 30),
                'session_type' => 'presentation',
                'track' => 'fusion',
                'capacity' => 300,
                'sort_order' => 6,
                'is_published' => true,
                'speaker_ids' => [$speakers[4]->id],
            ],
        ];

        foreach ($sessions as $sessionData) {
            $speakerIds = $sessionData['speaker_ids'];
            unset($sessionData['speaker_ids']);
            $sessionData['slug'] = \Str::slug($sessionData['title']);

            $session = Session::create($sessionData);
            $session->speakers()->sync($speakerIds);
        }

        echo "Seeded: " . count($speakers) . " speakers, " . ConferenceDay::count() . " days, " . Venue::count() . " venues, " . Session::count() . " sessions.\n";
    }
}
