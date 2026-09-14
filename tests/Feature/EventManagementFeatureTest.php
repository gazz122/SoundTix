<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Performer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function organizer(array $attributes = []): User
    {
        return User::factory()->organizer()->create($attributes);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Konser Akhir Tahun',
            'description' => 'Nikmati penampilan musisi ternama.',
            'venue_name' => 'Gelora Bung Karno',
            'city' => 'Jakarta',
            'address' => 'Jl. Pintu Satu Senayan',
            'event_date' => now()->addDays(14)->toDateString(),
            'start_time' => '19:00',
            'end_time' => '22:00',
            'status' => 'draft',
            'performers' => ['Tulus', 'Glenn Fredly', 'Tulus'],
            ...$overrides,
        ];
    }

    public function test_organizer_sees_only_own_events(): void
    {
        $organizer = $this->organizer();
        $other = $this->organizer();
        Event::factory()->draft()->create(['organizer_id' => $organizer->id, 'title' => 'Event Saya']);
        Event::factory()->draft()->create(['organizer_id' => $other->id, 'title' => 'Event Orang Lain']);

        $this->actingAs($organizer)
            ->get('/organizer/events')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Organizer/Events/Index')
                    ->has('events', 1)
                    ->where('events.0.title', 'Event Saya'),
            );
    }

    public function test_organizer_can_create_draft_event_with_performers(): void
    {
        $organizer = $this->organizer();

        $response = $this->actingAs($organizer)->post('/organizer/events', $this->validPayload());

        $response->assertRedirect(route('organizer.events.index'));

        $event = Event::where('organizer_id', $organizer->id)->firstOrFail();
        $this->assertSame('draft', $event->status);
        $this->assertSame('konser-akhir-tahun', $event->slug);
        $this->assertSame(2, $event->performers()->count());
        $this->assertDatabaseHas('performers', ['name' => 'Tulus']);
        $this->assertSame(1, Performer::where('name', 'Tulus')->count());
    }

    public function test_organizer_can_create_event_as_pending(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post('/organizer/events', $this->validPayload(['status' => 'pending']))
            ->assertRedirect();

        $this->assertSame('pending', Event::where('organizer_id', $organizer->id)->firstOrFail()->status);
    }

    public function test_event_creation_creates_unique_slug_for_same_title(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)->post('/organizer/events', $this->validPayload());
        $this->actingAs($organizer)->post('/organizer/events', $this->validPayload());

        $slugs = Event::where('organizer_id', $organizer->id)->pluck('slug')->sort()->values();
        $this->assertSame(['konser-akhir-tahun', 'konser-akhir-tahun-2'], $slugs->all());
    }

    public function test_event_creation_validates_past_date_and_required_fields(): void
    {
        $this->actingAs($this->organizer())
            ->post('/organizer/events', $this->validPayload([
                'title' => '',
                'event_date' => now()->subDay()->toDateString(),
                'status' => 'published',
            ]))
            ->assertSessionHasErrors(['title', 'event_date', 'status']);

        $this->assertCount(0, Event::all());
    }

    public function test_organizer_can_edit_own_draft_event(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->draft()->create(['organizer_id' => $organizer->id, 'title' => 'Judul Lama']);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}", $this->validPayload(['title' => 'Judul Baru']))
            ->assertRedirect();

        $event->refresh();
        $this->assertSame('Judul Baru', $event->title);
        $this->assertSame('judul-baru', $event->slug);
        $this->assertSame('draft', $event->status);
    }

    public function test_published_event_cannot_be_edited(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}", $this->validPayload())
            ->assertForbidden();
    }

    public function test_organizer_cannot_manage_another_organizers_event(): void
    {
        $organizer = $this->organizer();
        $other = $this->organizer();
        $event = Event::factory()->draft()->create(['organizer_id' => $other->id]);

        $this->actingAs($organizer)
            ->get("/organizer/events/{$event->id}/edit")
            ->assertNotFound();

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/submit")
            ->assertNotFound();
    }

    public function test_organizer_can_submit_draft_for_approval(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->draft()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/submit")
            ->assertRedirect();

        $this->assertSame('pending', $event->fresh()->status);
    }

    public function test_rejected_event_can_be_resubmitted_and_reason_cleared(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->rejected()->create([
            'organizer_id' => $organizer->id,
            'rejection_reason' => 'Deskripsi terlalu singkat',
        ]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/submit")
            ->assertRedirect();

        $fresh = $event->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->rejection_reason);
    }

    public function test_published_event_cannot_be_submitted(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->published()->create(['organizer_id' => $organizer->id]);

        $this->actingAs($organizer)
            ->post("/organizer/events/{$event->id}/submit")
            ->assertForbidden();
    }

    public function test_guest_redirected_to_login_and_customer_forbidden(): void
    {
        $this->get('/organizer/events')->assertRedirect(route('login'));
        $this->post('/organizer/events', $this->validPayload())->assertRedirect(route('login'));

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/organizer/events')->assertForbidden();
    }

    public function test_admin_sees_pending_events_with_organizer_name(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();
        Event::factory()->pending()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event Menunggu Verifikasi',
        ]);

        $this->actingAs($admin)
            ->get('/admin/events')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Admin/Events/Index')
                    ->has('events.data', 1)
                    ->where('events.data.0.title', 'Event Menunggu Verifikasi')
                    ->where('events.data.0.organizer.name', $organizer->name),
            );
    }

    public function test_admin_can_approve_pending_event(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->pending()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/approve")
            ->assertRedirect();

        $this->assertSame('published', $event->fresh()->status);
    }

    public function test_admin_reject_requires_reason(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->pending()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/reject", ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('pending', $event->fresh()->status);
    }

    public function test_admin_can_reject_pending_event_with_reason(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->pending()->create(['title' => 'Event Ditolak']);

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/reject", ['rejection_reason' => 'Materi kurang lengkap'])
            ->assertRedirect();

        $fresh = $event->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Materi kurang lengkap', $fresh->rejection_reason);
    }

    public function test_admin_cannot_approve_non_pending_event(): void
    {
        $admin = $this->admin();
        $event = Event::factory()->published()->create();

        $this->actingAs($admin)
            ->post("/admin/events/{$event->id}/approve")
            ->assertNotFound();
    }

    public function test_non_admin_cannot_access_admin_event_routes(): void
    {
        $organizer = $this->organizer();
        $event = Event::factory()->pending()->create();

        $this->actingAs($organizer)
            ->get('/admin/events')
            ->assertForbidden();

        $this->actingAs($organizer)
            ->post("/admin/events/{$event->id}/approve")
            ->assertForbidden();
    }

    public function test_organizer_can_upload_event_poster(): void
    {
        Storage::fake('public');
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post('/organizer/events', [
                ...$this->validPayload(),
                'banner' => UploadedFile::fake()->image('poster.jpg', 400, 600),
            ])
            ->assertRedirect();

        $event = Event::where('organizer_id', $organizer->id)->firstOrFail();
        $this->assertNotNull($event->banner_path);
        Storage::disk('public')->assertExists($event->banner_path);
    }

    public function test_poster_must_be_an_image(): void
    {
        $organizer = $this->organizer();

        $this->actingAs($organizer)
            ->post('/organizer/events', [
                ...$this->validPayload(),
                'banner' => UploadedFile::fake()->create('poster.txt', 10),
            ])
            ->assertSessionHasErrors('banner');

        $this->assertSame(0, Event::where('organizer_id', $organizer->id)->count());
    }

    public function test_poster_can_be_replaced_on_update(): void
    {
        Storage::fake('public');
        $organizer = $this->organizer();
        $event = Event::factory()->draft()->create([
            'organizer_id' => $organizer->id,
            'banner_path' => $old = UploadedFile::fake()->image('old.jpg')->store('events', 'public'),
        ]);
        Storage::disk('public')->assertExists($old);

        $this->actingAs($organizer)
            ->put("/organizer/events/{$event->id}", [
                ...$this->validPayload(),
                'banner' => UploadedFile::fake()->image('new.jpg'),
            ])
            ->assertRedirect();

        $fresh = $event->fresh();
        $this->assertNotSame($old, $fresh->banner_path);
        Storage::disk('public')->assertExists($fresh->banner_path);
        Storage::disk('public')->assertMissing($old);

        $this->actingAs($organizer)
            ->get("/organizer/events/{$event->id}/edit")
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('event.banner_url', Storage::disk('public')->url($fresh->banner_path)),
            );
    }

    public function test_approved_event_appears_in_public_home(): void
    {
        $admin = $this->admin();
        $organizer = $this->organizer();
        $event = Event::factory()->pending()->create([
            'organizer_id' => $organizer->id,
            'title' => 'Event Baru Tayang',
            'slug' => 'event-baru-tayang',
        ]);

        $this->actingAs($admin)->post("/admin/events/{$event->id}/approve");

        $this->get('/')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->has('events.data', 1)
                    ->where('events.data.0.title', 'Event Baru Tayang'),
            );
    }
}
