<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
    private User $admin;
    private Branch $branch;
    private RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->otherCustomer = User::create([
            'name' => 'Customer Lain',
            'email' => 'other-customer@test.com',
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
    }

    public function test_customer_can_review_their_completed_booking(): void
    {
        $booking = $this->createBookingFor($this->customer, 'completed');

        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/reviews', [
                'booking_id' => $booking->id,
                'rating' => 5,
                'comment' => 'Kamarnya bersih dan nyaman.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.user_id', $this->customer->id)
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'user_id' => $this->customer->id,
            'rating' => 5,
        ]);
    }

    public function test_customer_can_review_their_confirmed_booking(): void
    {
        $booking = $this->createBookingFor($this->customer, 'confirmed');

        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/reviews', [
                'booking_id' => $booking->id,
                'rating' => 4,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.user_id', $this->customer->id)
            ->assertJsonPath('data.rating', 4);
    }

    public function test_customer_cannot_review_another_customers_booking(): void
    {
        $booking = $this->createBookingFor($this->customer, 'completed');

        $response = $this->actingAs($this->otherCustomer, 'api')
            ->postJson('/api/reviews', [
                'booking_id' => $booking->id,
                'rating' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Anda tidak memiliki akses untuk memberikan ulasan pada pemesanan ini.',
            ]);
    }

    public function test_customer_cannot_review_pending_booking(): void
    {
        $booking = $this->createBookingFor($this->customer, 'pending');

        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/reviews', [
                'booking_id' => $booking->id,
                'rating' => 5,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Anda hanya bisa memberikan ulasan untuk pemesanan yang telah dikonfirmasi atau selesai.',
            ]);
    }

    public function test_customer_cannot_review_same_booking_twice(): void
    {
        $booking = $this->createBookingFor($this->customer, 'completed');

        Review::create([
            'booking_id' => $booking->id,
            'user_id' => $this->customer->id,
            'rating' => 5,
            'comment' => 'Review awal.',
        ]);

        $response = $this->actingAs($this->customer, 'api')
            ->postJson('/api/reviews', [
                'booking_id' => $booking->id,
                'rating' => 4,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Pemesanan ini sudah diberikan ulasan sebelumnya.',
            ]);
    }

    public function test_admin_cannot_create_review(): void
    {
        $booking = $this->createBookingFor($this->customer, 'completed');

        $response = $this->actingAs($this->admin, 'api')
            ->postJson('/api/reviews', [
                'booking_id' => $booking->id,
                'rating' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Hanya customer yang dapat memberikan ulasan.',
            ]);
    }

    private function createBookingFor(User $user, string $status): Booking
    {
        return Booking::create([
            'user_id' => $user->id,
            'room_type_id' => $this->roomType->id,
            'check_in_date' => now()->subDays(3)->toDateString(),
            'check_out_date' => now()->subDay()->toDateString(),
            'total_nights' => 2,
            'total_price' => 300000.00,
            'status' => $status,
        ]);
    }
}
