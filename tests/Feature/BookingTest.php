<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;
    private User $owner;
    private Branch $branch;
    private RoomType $roomType;
    private Room $room1;
    private Room $room2;

    protected function setUp(): void
    {
        parent::setUp();

        // Buat data dasar
        $this->branch = Branch::create([
            'name' => 'Cabang Test',
            'description' => 'Deskripsi Cabang Test',
            'address' => 'Jl. Test No. 123',
            'longitude' => '106.8',
            'latitude' => '-6.2',
            'phone' => '08123456789',
            'qris_code' => 'qris.png',
            'is_active' => true,
        ]);

        $this->roomType = RoomType::create([
            'branch_id' => $this->branch->id,
            'name' => 'Tipe Test',
            'description' => 'Deskripsi Tipe Test',
            'price' => 150000.00,
            'room_size' => 12,
            'is_active' => true,
        ]);

        // Buat 2 kamar aktif untuk tipe ini (kapasitas = 2)
        $this->room1 = Room::create([
            'room_type_id' => $this->roomType->id,
            'number' => 101,
            'is_active' => true,
            'is_filled' => false,
        ]);

        $this->room2 = Room::create([
            'room_type_id' => $this->roomType->id,
            'number' => 102,
            'is_active' => true,
            'is_filled' => false,
        ]);

        // Buat users
        $this->customer = User::create([
            'name' => 'Customer Test',
            'email' => 'customer@test.com',
            'password' => 'password123',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => 'admin',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner Test',
            'email' => 'owner@test.com',
            'password' => 'password123',
            'role' => 'pemilik_kos',
            'is_active' => true,
        ]);
    }

    public function test_customer_can_create_booking_successfully()
    {
        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/bookings', [
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->addDays(1)->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'notes' => 'Tolong bersih',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'room_type_id',
                    'room_id',
                    'check_in_date',
                    'check_out_date',
                    'total_nights',
                    'total_price',
                    'status',
                ]
            ]);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'pending',
        ]);
    }

    public function test_booking_fails_when_capacity_is_exceeded_fase_1()
    {
        // Kapasitas tipe kamar ini adalah 2 (karena ada room1 dan room2).
        // Kita buat 2 booking aktif yang menempati rentang tanggal yang sama.
        Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'pending',
        ]);

        Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'confirmed',
        ]);

        // Booking ke-3 di tanggal yang sama harus ditolak (Tipe kamar penuh)
        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/bookings', [
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->addDays(1)->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Tipe kamar ini sudah penuh pada tanggal ' . now()->addDays(1)->toDateString() . '.',
            ]);
    }

    public function test_admin_can_confirm_booking_and_allocate_room_fase_2()
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/bookings/{$booking->id}/confirm", [
                'room_id' => $this->room1->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.room_id', $this->room1->id);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'room_id' => $this->room1->id,
            'status' => 'confirmed',
            'assigned_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room1->id,
            'is_filled' => true,
        ]);
    }

    public function test_admin_cannot_confirm_room_if_it_is_already_booked_in_overlapping_period()
    {
        // Booking 1 sudah dikonfirmasi di Room 1
        Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room1->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'confirmed',
        ]);

        // Booking 2 (baru/pending) ingin dikonfirmasi admin ke Room 1 juga di tanggal yang tumpang tindih
        $booking2 = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(2)->toDateString(), // Tumpang tindih (hari ke-2 sampai ke-4)
            'check_out_date' => now()->addDays(4)->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/bookings/{$booking2->id}/confirm", [
                'room_id' => $this->room1->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Kamar ini sudah terisi atau dipesan oleh pelanggan lain pada tanggal tersebut.',
            ]);
    }

    public function test_customer_can_cancel_their_own_booking()
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->customer, 'api')
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_booking_fails_when_pending_limit_exceeded()
    {
        // Buat 3 booking pending
        for ($i = 0; $i < 3; $i++) {
            Booking::create([
                'user_id' => $this->customer->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->addDays(1 + $i * 5)->toDateString(),
                'check_out_date' => now()->addDays(3 + $i * 5)->toDateString(),
                'total_nights' => 2,
                'total_price' => 300000.00,
                'status' => 'pending',
            ]);
        }

        // Booking ke-4 harus ditolak
        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/bookings', [
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->addDays(20)->toDateString(),
                'check_out_date' => now()->addDays(22)->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Anda memiliki batas maksimal 3 pemesanan tertunda (pending). Silakan lakukan pembayaran terlebih dahulu.',
            ]);
    }

    public function test_booking_fails_when_daily_cancellations_limit_exceeded()
    {
        // Buat dan cancel 5 booking hari ini
        for ($i = 0; $i < 5; $i++) {
            $booking = Booking::create([
                'user_id' => $this->customer->id,
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->addDays(1)->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'total_nights' => 2,
                'total_price' => 300000.00,
                'status' => 'cancelled',
            ]);
            // Pastikan updated_at diatur ke hari ini
            $booking->updated_at = now();
            $booking->save();
        }

        // Booking baru hari ini harus ditolak
        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/bookings', [
                'room_type_id' => $this->roomType->id,
                'check_in_date' => now()->addDays(10)->toDateString(),
                'check_out_date' => now()->addDays(12)->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Anda telah membatalkan 5 pemesanan hari ini. Anda tidak dapat membuat pemesanan baru lagi untuk hari ini.',
            ]);
    }
}
