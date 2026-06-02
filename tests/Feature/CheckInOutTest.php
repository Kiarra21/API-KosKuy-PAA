<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\CheckInOut;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckInOutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $adminBranchA;
    private User $adminBranchB;
    private Branch $branchA;
    private Branch $branchB;
    private RoomType $roomTypeA;
    private RoomType $roomTypeB;
    private Room $roomA;
    private Room $roomB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Setup Cabang A
        $this->branchA = Branch::create([
            'name' => 'Cabang A',
            'description' => 'Cabang A',
            'address' => 'Jl. A',
            'longitude' => '106.8',
            'latitude' => '-6.2',
            'phone' => '08123456789',
            'qris_code' => 'qrisA.png',
            'is_active' => true,
        ]);

        $this->roomTypeA = RoomType::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Tipe A',
            'description' => 'Tipe A',
            'price' => 100000.00,
            'room_size' => 10,
            'is_active' => true,
        ]);

        $this->roomA = Room::create([
            'room_type_id' => $this->roomTypeA->id,
            'number' => 101,
            'is_active' => true,
            'is_filled' => false,
        ]);

        // Setup Cabang B
        $this->branchB = Branch::create([
            'name' => 'Cabang B',
            'description' => 'Cabang B',
            'address' => 'Jl. B',
            'longitude' => '106.9',
            'latitude' => '-6.3',
            'phone' => '08987654321',
            'qris_code' => 'qrisB.png',
            'is_active' => true,
        ]);

        $this->roomTypeB = RoomType::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Tipe B',
            'description' => 'Tipe B',
            'price' => 120000.00,
            'room_size' => 12,
            'is_active' => true,
        ]);

        $this->roomB = Room::create([
            'room_type_id' => $this->roomTypeB->id,
            'number' => 201,
            'is_active' => true,
            'is_filled' => false,
        ]);

        // Users
        $this->customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@test.com',
            'password' => 'password123',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $this->adminBranchA = User::create([
            'name' => 'Admin A',
            'email' => 'adminA@test.com',
            'password' => 'password123',
            'role' => 'admin',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);

        $this->adminBranchB = User::create([
            'name' => 'Admin B',
            'email' => 'adminB@test.com',
            'password' => 'password123',
            'role' => 'admin',
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);
    }

    public function test_customer_cannot_process_check_in_or_check_out()
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_id' => $this->roomA->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'total_nights' => 2,
            'total_price' => 200000.00,
            'status' => 'confirmed',
        ]);

        $responseCheckIn = $this->actingAs($this->customer, 'api')
            ->postJson("/api/bookings/{$booking->id}/check-in");
        $responseCheckIn->assertStatus(403);

        $responseCheckOut = $this->actingAs($this->customer, 'api')
            ->postJson("/api/bookings/{$booking->id}/check-out");
        $responseCheckOut->assertStatus(403);
    }

    public function test_admin_can_process_check_in_successfully()
    {
        // Booking terjadwal dari hari ini
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_id' => $this->roomA->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'total_nights' => 2,
            'total_price' => 200000.00,
            'status' => 'confirmed',
        ]);

        $file = UploadedFile::fake()->image('check_in.jpg');

        $response = $this->actingAs($this->adminBranchA, 'api')
            ->postJson("/api/bookings/{$booking->id}/check-in", [
                'check_in_photo' => $file,
                'notes' => 'Tamunya ramah',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'booking_id',
                    'checked_in_at',
                    'check_in_photo',
                    'notes'
                ]
            ]);

        $this->assertDatabaseHas('check_in_outs', [
            'booking_id' => $booking->id,
            'handled_by' => $this->adminBranchA->id,
            'notes' => 'Tamunya ramah',
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $this->roomA->id,
            'is_filled' => true,
        ]);
    }

    public function test_admin_cannot_check_in_early()
    {
        // Booking terjadwal besok
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_id' => $this->roomA->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'total_price' => 200000.00,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->adminBranchA, 'api')
            ->postJson("/api/bookings/{$booking->id}/check-in");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Belum saatnya check-in. Tanggal check-in dijadwalkan pada: ' . $booking->check_in_date->toDateString() . '.',
            ]);
    }

    public function test_admin_cannot_check_in_after_check_out_date()
    {
        // Booking terjadwal kemarin dan selesai hari ini (check-out hari ini)
        // Kita simulasikan hari ini melewati tanggal check_out_date
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_id' => $this->roomA->id,
            'check_in_date' => now()->subDays(2)->toDateString(),
            'check_out_date' => now()->subDay()->toDateString(), // check_out kemarin
            'total_nights' => 1,
            'total_price' => 100000.00,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->adminBranchA, 'api')
            ->postJson("/api/bookings/{$booking->id}/check-in");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Masa pemesanan sudah berakhir. Tanggal check-out dijadwalkan pada: ' . $booking->check_out_date->toDateString() . '.',
            ]);
    }

    public function test_admin_can_process_check_out_successfully_and_free_room()
    {
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_id' => $this->roomA->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'total_nights' => 2,
            'total_price' => 200000.00,
            'status' => 'confirmed',
        ]);

        // Simulasikan check-in dulu
        CheckInOut::create([
            'booking_id' => $booking->id,
            'handled_by' => $this->adminBranchA->id,
            'checked_in_at' => now(),
            'notes' => 'Tamunya ramah',
        ]);

        $file = UploadedFile::fake()->image('check_out.jpg');

        $response = $this->actingAs($this->adminBranchA, 'api')
            ->postJson("/api/bookings/{$booking->id}/check-out", [
                'check_out_photo' => $file,
                'notes' => 'Checkout lancar',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'booking_id',
                    'checked_in_at',
                    'checked_out_at',
                    'check_out_photo',
                    'notes'
                ]
            ]);

        $this->assertDatabaseHas('check_in_outs', [
            'booking_id' => $booking->id,
            'handled_by' => $this->adminBranchA->id,
            'notes' => "Tamunya ramah\nCheckout Notes: Checkout lancar",
        ]);

        // Status booking berubah menjadi completed
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'completed',
        ]);

        // Kamar menjadi kosong kembali (is_filled = false)
        $this->assertDatabaseHas('rooms', [
            'id' => $this->roomA->id,
            'is_filled' => false,
        ]);
    }

    public function test_admin_cannot_access_bookings_from_another_branch()
    {
        // Booking di Cabang B
        $booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomTypeB->id,
            'room_id' => $this->roomB->id,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'total_nights' => 2,
            'total_price' => 240000.00,
            'status' => 'confirmed',
        ]);

        // Admin A mencoba check-in booking Cabang B
        $response = $this->actingAs($this->adminBranchA, 'api')
            ->postJson("/api/bookings/{$booking->id}/check-in");

        $response->assertStatus(403);
    }
}
