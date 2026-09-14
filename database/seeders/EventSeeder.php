<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\Performer;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    /**
     * Seed a set of published demo events with performers and ticket categories.
     */
    public function run(): void
    {
        $organizer = User::where('email', 'organizer@example.com')->first()
            ?? User::factory()->create([
                'name' => 'Test Organizer',
                'email' => 'organizer@example.com',
                'role' => UserRole::Organizer,
            ]);

        $samples = [
            [
                'title' => 'Konser Nusantara Jaya',
                'city' => 'Jakarta',
                'venue' => 'Istora Senayan',
                'date' => 14,
                'performers' => [['name' => 'Kikan & Bands', 'genre' => 'Pop'], ['name' => 'Rizki Arsip', 'genre' => 'Rock']],
            ],
            [
                'title' => 'Festival Musik Bandung Live',
                'city' => 'Bandung',
                'venue' => 'Gedung Sate Area',
                'date' => 21,
                'performers' => [['name' => 'Laras Sunda', 'genre' => 'Dangdut'], ['name' => 'Jazzy FM', 'genre' => 'Jazz']],
            ],
            [
                'title' => 'Jazz in Sunset',
                'city' => 'Bali',
                'venue' => 'Garuda Wisnu Kencana',
                'date' => 28,
                'performers' => [['name' => 'Saksofon Biru', 'genre' => 'Jazz'], ['name' => 'Vokal Senja', 'genre' => 'Indie']],
            ],
            [
                'title' => 'Sound Fusion Festival',
                'city' => 'Yogyakarta',
                'venue' => 'Taman Budaya',
                'date' => 35,
                'performers' => [['name' => 'Gamelan Elektronik', 'genre' => 'EDM'], ['name' => 'Akustik Malioboro', 'genre' => 'Indie']],
            ],
        ];

        foreach ($samples as $sample) {
            $event = Event::create([
                'organizer_id' => $organizer->id,
                'title' => $sample['title'],
                'slug' => Str::slug($sample['title']),
                'description' => "SoundTix menghadirkan {$sample['title']} secara langsung di {$sample['venue']}. Nikmati penampilan istimewa dari para musisi papan atas dalam satu malam yang tak terlupakan. Satu tiket untuk satu pintu masuk lain sesuai ketentuan venue.",
                'venue_name' => $sample['venue'],
                'city' => $sample['city'],
                'address' => "Jl. {$sample['city']} No. 17, {$sample['city']}",
                'event_date' => now()->addDays($sample['date'])->toDateString(),
                'start_time' => '19:00',
                'end_time' => '22:30',
                'status' => 'published',
            ]);

            foreach ($sample['performers'] as $performerData) {
                $performer = Performer::firstOrCreate(['name' => $performerData['name']], $performerData);
                $event->performers()->attach($performer->id);
            }

            $categories = [
                ['name' => 'Reguler', 'price' => 150_000, 'quantity' => 500],
                ['name' => 'Festival', 'price' => 250_000, 'quantity' => 300],
                ['name' => 'VIP', 'price' => 750_000, 'quantity' => 100],
            ];

            foreach ($categories as $category) {
                TicketCategory::create([
                    'event_id' => $event->id,
                    'name' => $category['name'],
                    'description' => 'Area '.$category['name'].' dengan fasilitas sesuai ketentuan event.',
                    'price' => $category['price'],
                    'total_quantity' => $category['quantity'],
                    'available_quantity' => $category['quantity'],
                    'max_per_order' => 5,
                ]);
            }
        }
    }
}
