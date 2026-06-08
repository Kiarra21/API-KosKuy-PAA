<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $admin;
    private User $owner;
    private Branch $branch;
    private RoomType $roomType;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

        $this->booking = Booking::create([
            'user_id' => $this->customer->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => 'pending',
        ]);
    }

    public function test_customer_can_submit_proof_image_successfully()
    {
        $file = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/payments/submit', [
                'booking_id' => $this->booking->id,
                'proof_image' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'booking_id',
                    'code',
                    'status',
                    'proof_image',
                ]
            ]);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $this->booking->id,
            'status' => 'pending',
        ]);

        // Pastikan file tersimpan di storage
        $payment = Payment::where('booking_id', $this->booking->id)->first();
        Storage::disk('public')->assertExists($payment->proof_image);
    }

    public function test_admin_can_approve_payment_successfully()
    {
        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'code' => 'PAY-TEST',
            'status' => 'pending',
            'proof_image' => 'proof.jpg',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/payments/{$payment->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'handled_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reject_payment_successfully()
    {
        $payment = Payment::create([
            'booking_id' => $this->booking->id,
            'code' => 'PAY-TEST',
            'status' => 'pending',
            'proof_image' => 'proof.jpg',
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->postJson("/api/payments/{$payment->id}/reject", [
                'rejection_reason' => 'Bukti transfer buram',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
            'handled_by' => $this->admin->id,
            'rejection_reason' => 'Bukti transfer buram',
        ]);
    }
}
