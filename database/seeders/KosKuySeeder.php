<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Branch;
use App\Models\BranchPhoto;
use App\Models\Facility;
use App\Models\RoomType;
use App\Models\RoomPhoto;
use App\Models\Room;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\CheckInOut;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class KosKuySeeder extends Seeder
{
    /**
     * Helper to download internet image or generate local GD image fallback
     */
    private function seedImage(string $directory, string $filename, string $url, string $fallbackText = 'Image'): string
    {
        $path = $directory . '/' . $filename;
        
        try {
            // Fetch the image from URL with a 5-second timeout
            $response = Http::timeout(5)->get($url);
            if ($response->successful()) {
                Storage::disk('public')->put($path, $response->body());
                return $path;
            }
        } catch (\Exception $e) {
            // Network failure or timeout, fall back to generating an image
        }

        // Offline Fallback: Generate colored image using GD
        if (extension_loaded('gd')) {
            $width = 400;
            $height = 300;
            if ($directory === 'profile-pictures') {
                $width = 150;
                $height = 150;
            } elseif ($directory === 'branch-qris') {
                $width = 300;
                $height = 300;
            }
            
            $im = imagecreatetruecolor($width, $height);
            // Generate deterministic colors based on filename
            $hash = md5($filename);
            $r = hexdec(substr($hash, 0, 2)) % 150 + 50;
            $g = hexdec(substr($hash, 2, 2)) % 150 + 50;
            $b = hexdec(substr($hash, 4, 2)) % 150 + 50;
            
            $bg = imagecolorallocate($im, $r, $g, $b);
            imagefill($im, 0, 0, $bg);
            
            // Text color (White)
            $textcolor = imagecolorallocate($im, 255, 255, 255);
            imagestring($im, 5, ($width / 2) - (strlen($fallbackText) * 4), ($height / 2) - 8, $fallbackText, $textcolor);
            
            ob_start();
            imagepng($im);
            $imageData = ob_get_clean();
            imagedestroy($im);
            
            Storage::disk('public')->put($path, $imageData);
        } else {
            // Absolute minimal fallback (just text data)
            Storage::disk('public')->put($path, 'Image placeholder: ' . $fallbackText);
        }

        return $path;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Clean existing records
        Schema::disableForeignKeyConstraints();
        DB::table('check_in_outs')->truncate();
        DB::table('reviews')->truncate();
        DB::table('payments')->truncate();
        DB::table('bookings')->truncate();
        DB::table('rooms')->truncate();
        DB::table('room_type_facility')->truncate();
        DB::table('room_photos')->truncate();
        DB::table('room_types')->truncate();
        DB::table('branch_facility')->truncate();
        DB::table('branch_photos')->truncate();
        DB::table('facilities')->truncate();
        DB::table('branches')->truncate();
        DB::table('users')->truncate();
        Schema::enableForeignKeyConstraints();

        // Clean up storage directories
        Storage::disk('public')->deleteDirectory('profile-pictures');
        Storage::disk('public')->deleteDirectory('branch-qris');
        Storage::disk('public')->deleteDirectory('branch-photos');
        Storage::disk('public')->deleteDirectory('room-photos');
        Storage::disk('public')->deleteDirectory('payment-proofs');
        Storage::disk('public')->deleteDirectory('check-in-photos');
        Storage::disk('public')->deleteDirectory('check-out-photos');

        // Make sure directories exist
        Storage::disk('public')->makeDirectory('profile-pictures');
        Storage::disk('public')->makeDirectory('branch-qris');
        Storage::disk('public')->makeDirectory('branch-photos');
        Storage::disk('public')->makeDirectory('room-photos');
        Storage::disk('public')->makeDirectory('payment-proofs');
        Storage::disk('public')->makeDirectory('check-in-photos');
        Storage::disk('public')->makeDirectory('check-out-photos');

        // 2. Seed Facilities
        $facilitiesData = [
            ['name' => 'WiFi (100 Mbps)'],
            ['name' => 'AC (Air Conditioner)'],
            ['name' => 'Kamar Mandi Dalam'],
            ['name' => 'Kasur & Bantal (Springbed)'],
            ['name' => 'Lemari Pakaian'],
            ['name' => 'Meja & Kursi Belajar'],
            ['name' => 'Parkir Motor (Aman & Berpagar)'],
            ['name' => 'Dapur Bersama'],
            ['name' => 'Kulkas Bersama'],
            ['name' => 'Mesin Cuci Bersama'],
        ];

        $facilities = [];
        foreach ($facilitiesData as $fd) {
            $facilities[] = Facility::create($fd);
        }

        // 3. Seed Branches (Jember area)
        // We download and save the QRIS code to 'branch-qris/'
        $qris1 = $this->seedImage('branch-qris', 'qris_tegalboto.png', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=KosKuy-Tegalboto', 'QRIS Tegalboto');
        $qris2 = $this->seedImage('branch-qris', 'qris_sumbersari.png', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=KosKuy-Sumbersari', 'QRIS Sumbersari');
        $qris3 = $this->seedImage('branch-qris', 'qris_patrang.png', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=KosKuy-Patrang', 'QRIS Patrang');

        $branchesData = [
            [
                'name' => 'KosKuy Jember - Tegalboto',
                'description' => 'Cabang KosKuy premium dekat kampus Universitas Jember (UNEJ). Fasilitas super lengkap, nyaman, bersih, dan strategis dekat dengan pusat kuliner, kafe, dan perkuliahan. Sangat cocok untuk mahasiswa UNEJ maupun umum yang menginginkan hunian eksklusif dan tenang.',
                'address' => 'Jl. Kalimantan No. 37, Sumbersari, Jember, Jawa Timur',
                'latitude' => '-8.165482',
                'longitude' => '113.716883',
                'phone' => '081234567801',
                'qris_code' => $qris1,
                'is_active' => true,
            ],
            [
                'name' => 'KosKuy Jember - Sumbersari',
                'description' => 'Hunian sewa kos terbaik dengan harga terjangkau dekat kampus Politeknik Negeri Jember (POLIJE) & Universitas Muhammadiyah Jember. Menyediakan lingkungan yang tenang dan kondusif untuk mendukung kegiatan belajar mengajar mahasiswa.',
                'address' => 'Jl. Mastrip No. 12, Sumbersari, Jember, Jawa Timur',
                'latitude' => '-8.158739',
                'longitude' => '113.722510',
                'phone' => '081234567802',
                'qris_code' => $qris2,
                'is_active' => true,
            ],
            [
                'name' => 'KosKuy Jember - Patrang',
                'description' => 'Cabang Patrang berlokasi di area asri dan tenang dekat RSUD dr. Soebandi Jember. Sangat strategis untuk mahasiswa kedokteran, tenaga medis, staf rumah sakit, maupun pekerja kantoran yang beraktivitas di daerah perkotaan Jember.',
                'address' => 'Jl. Dr. Soebandi No. 45, Patrang, Jember, Jawa Timur',
                'latitude' => '-8.148202',
                'longitude' => '113.708112',
                'phone' => '081234567803',
                'qris_code' => $qris3,
                'is_active' => true,
            ]
        ];

        $branches = [];
        foreach ($branchesData as $bd) {
            $branches[] = Branch::create($bd);
        }

        // 4. Seed Branch Photos (House/apartment exterior photos saved locally)
        $branchPhotosData = [
            // Tegalboto
            [
                'branch_id' => 1,
                'photos' => [
                    'https://images.unsplash.com/photo-1513694203232-719a280e022f?w=800&auto=format&fit=crop',
                    'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?w=800&auto=format&fit=crop',
                ]
            ],
            // Sumbersari
            [
                'branch_id' => 2,
                'photos' => [
                    'https://images.unsplash.com/photo-1568605114967-8130f3a36994?w=800&auto=format&fit=crop',
                    'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=800&auto=format&fit=crop',
                ]
            ],
            // Patrang
            [
                'branch_id' => 3,
                'photos' => [
                    'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=800&auto=format&fit=crop',
                    'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?w=800&auto=format&fit=crop',
                ]
            ]
        ];

        foreach ($branchPhotosData as $bp) {
            $order = 1;
            foreach ($bp['photos'] as $p) {
                $filename = 'branch_' . $bp['branch_id'] . '_' . $order . '.jpg';
                $localPath = $this->seedImage('branch-photos', $filename, $p, 'Branch ' . $bp['branch_id'] . ' Photo ' . $order);
                
                BranchPhoto::create([
                    'branch_id' => $bp['branch_id'],
                    'photo' => $localPath,
                    'order' => $order++,
                ]);
            }
        }

        // 5. Sync Facilities to Branches
        $branches[0]->facilities()->sync([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        $branches[1]->facilities()->sync([1, 4, 5, 6, 7, 8, 9]);
        $branches[2]->facilities()->sync([1, 2, 4, 5, 6, 7, 8, 10]);

        // 6. Seed Users (Pemilik, Admin per Branch, and Customers)
        $pemilikProfile = $this->seedImage('profile-pictures', 'pemilik.jpg', 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop', 'Rudi Hartono');
        $pemilik = User::create([
            'name' => 'Rudi Hartono (Pemilik)',
            'email' => 'pemilik@example.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik_kos',
            'phone' => '081234567890',
            'address' => 'Jl. Karimata No. 5, Sumbersari, Jember',
            'profile_picture' => $pemilikProfile,
            'is_active' => true,
            'branch_id' => null,
        ]);

        $admins = [];
        $adminsData = [
            [
                'name' => 'Ahmad Tegalboto',
                'email' => 'admin1@example.com',
                'branch_id' => 1,
                'phone' => '081234567891',
                'profile_picture' => 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=150&auto=format&fit=crop',
            ],
            [
                'name' => 'Siti Sumbersari',
                'email' => 'admin2@example.com',
                'branch_id' => 2,
                'phone' => '081234567892',
                'profile_picture' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop',
            ],
            [
                'name' => 'Joko Patrang',
                'email' => 'admin3@example.com',
                'branch_id' => 3,
                'phone' => '081234567893',
                'profile_picture' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop',
            ],
        ];

        foreach ($adminsData as $index => $ad) {
            $adminProfile = $this->seedImage('profile-pictures', 'admin_' . ($index+1) . '.jpg', $ad['profile_picture'], $ad['name']);
            $admins[] = User::create([
                'name' => $ad['name'],
                'email' => $ad['email'],
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone' => $ad['phone'],
                'address' => 'Kantor Cabang ' . $ad['name'],
                'profile_picture' => $adminProfile,
                'is_active' => true,
                'branch_id' => $ad['branch_id'],
            ]);
        }

        $customers = [];
        $customersData = [
            [
                'name' => 'Reza Pratama',
                'email' => 'customer1@example.com',
                'phone' => '085123456781',
                'profile_picture' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop',
            ],
            [
                'name' => 'Anisa Lestari',
                'email' => 'customer2@example.com',
                'phone' => '085123456782',
                'profile_picture' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop',
            ],
            [
                'name' => 'Deni Prasetyo',
                'email' => 'customer3@example.com',
                'phone' => '085123456783',
                'profile_picture' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop',
            ],
            [
                'name' => 'Fanya Rahma',
                'email' => 'customer4@example.com',
                'phone' => '085123456784',
                'profile_picture' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=150&auto=format&fit=crop',
            ],
            [
                'name' => 'Gilang Ramadhan',
                'email' => 'customer5@example.com',
                'phone' => '085123456785',
                'profile_picture' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=150&auto=format&fit=crop',
            ],
        ];

        foreach ($customersData as $index => $cd) {
            $custProfile = $this->seedImage('profile-pictures', 'customer_' . ($index+1) . '.jpg', $cd['profile_picture'], $cd['name']);
            $customers[] = User::create([
                'name' => $cd['name'],
                'email' => $cd['email'],
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'phone' => $cd['phone'],
                'address' => 'Jl. Jawa No. 10, Jember',
                'profile_picture' => $custProfile,
                'is_active' => true,
                'branch_id' => null,
            ]);
        }

        // 7. Seed Room Types for each branch
        $roomTypes = [];
        
        $stdPhotos = [
            'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?w=800&auto=format&fit=crop',
        ];
        $dlxPhotos = [
            'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?w=800&auto=format&fit=crop',
            'https://images.unsplash.com/photo-1598928506311-c55ded91a20c?w=800&auto=format&fit=crop',
        ];

        foreach ($branches as $branch) {
            // Standard Room Type
            $standard = RoomType::create([
                'branch_id' => $branch->id,
                'name' => 'Standard Room',
                'description' => 'Kamar standar berukuran 3x4 meter yang nyaman dan ekonomis. Cocok untuk mahasiswa yang ingin fokus belajar dengan budget bersahabat.',
                'price' => 25000.00, // Rp 25.000 / malam (sekitar 750k / bulan)
                'room_size' => 12,
                'is_active' => true,
            ]);
            $standard->facilities()->sync([1, 4, 5, 6, 7]);

            // Deluxe Room Type
            $deluxe = RoomType::create([
                'branch_id' => $branch->id,
                'name' => 'Deluxe Room',
                'description' => 'Kamar premium berukuran 4x4 meter dilengkapi dengan fasilitas eksklusif seperti pendingin udara (AC), kamar mandi pribadi di dalam kamar, kasur springbed nyaman, dan area kerja/belajar yang lebih lega.',
                'price' => 50000.00, // Rp 50.000 / malam (sekitar 1.5jt / bulan)
                'room_size' => 16,
                'is_active' => true,
            ]);
            $deluxe->facilities()->sync([1, 2, 3, 4, 5, 6, 7]);

            $roomTypes[] = $standard;
            $roomTypes[] = $deluxe;

            // Seed Room Photos
            $order = 1;
            foreach ($stdPhotos as $sp) {
                $filename = 'room_std_' . $branch->id . '_' . $order . '.jpg';
                $localPath = $this->seedImage('room-photos', $filename, $sp, 'Standard Room Photo ' . $order);
                RoomPhoto::create([
                    'room_type_id' => $standard->id,
                    'photo' => $localPath,
                    'order' => $order++,
                ]);
            }

            $order = 1;
            foreach ($dlxPhotos as $dp) {
                $filename = 'room_dlx_' . $branch->id . '_' . $order . '.jpg';
                $localPath = $this->seedImage('room-photos', $filename, $dp, 'Deluxe Room Photo ' . $order);
                RoomPhoto::create([
                    'room_type_id' => $deluxe->id,
                    'photo' => $localPath,
                    'order' => $order++,
                ]);
            }
        }

        // 8. Seed Rooms (Individual rooms per RoomType)
        $rooms = [];
        foreach ($roomTypes as $rt) {
            $floor = ($rt->name === 'Deluxe Room') ? 2 : 1;
            for ($i = 1; $i <= 3; $i++) {
                $number = ($floor * 100) + $i;
                $rooms[] = Room::create([
                    'room_type_id' => $rt->id,
                    'number' => $number,
                    'is_active' => true,
                    'is_filled' => false,
                ]);
            }
        }

        // Helper function to find a room by room type
        $getRoomForType = function ($roomTypeId) use (&$rooms) {
            foreach ($rooms as $room) {
                if ($room->room_type_id === $roomTypeId && !$room->is_filled) {
                    return $room;
                }
            }
            return null;
        };

        // 9. Seed Bookings and related records

        // Image resources for workflows
        $proofUrl = 'https://images.unsplash.com/photo-1554415707-6e8cfc93fe23?w=500';
        $inUrl = 'https://images.unsplash.com/photo-1562240020-ce31ccb0fa7d?w=400';
        $outUrl = 'https://images.unsplash.com/photo-1582268611958-ebfd161ef9cf?w=400';

        // --- Booking 1: Completed (Lunas & Selesai) ---
        $b1RoomType = $roomTypes[0]; // Branch 1 Standard
        $b1Room = $getRoomForType($b1RoomType->id);
        $checkInDate = Carbon::now()->subDays(45)->toDateString();
        $checkOutDate = Carbon::now()->subDays(15)->toDateString();
        $totalNights = 30;
        $totalPrice = $b1RoomType->price * $totalNights;

        $booking1 = Booking::create([
            'user_id' => $customers[0]->id,
            'room_type_id' => $b1RoomType->id,
            'room_id' => $b1Room->id,
            'assigned_by' => $admins[0]->id,
            'assigned_at' => Carbon::now()->subDays(48)->toDateTimeString(),
            'check_in_date' => $checkInDate,
            'check_out_date' => $checkOutDate,
            'total_nights' => $totalNights,
            'total_price' => $totalPrice,
            'status' => 'completed',
            'notes' => 'Minta kamar dekat parkiran motor.',
        ]);

        $proof1 = $this->seedImage('payment-proofs', 'proof_1.jpg', $proofUrl, 'Payment Proof 1');
        Payment::create([
            'booking_id' => $booking1->id,
            'code' => 'PAY-' . Carbon::now()->subDays(48)->timestamp . '-01',
            'status' => 'paid',
            'proof_image' => $proof1,
            'handled_by' => $admins[0]->id,
            'handled_at' => Carbon::now()->subDays(48)->toDateTimeString(),
            'paid_at' => Carbon::now()->subDays(48)->toDateTimeString(),
        ]);

        $inPhoto1 = $this->seedImage('check-in-photos', 'check_in_1.jpg', $inUrl, 'Check In Photo 1');
        $outPhoto1 = $this->seedImage('check-out-photos', 'check_out_1.jpg', $outUrl, 'Check Out Photo 1');
        CheckInOut::create([
            'booking_id' => $booking1->id,
            'handled_by' => $admins[0]->id,
            'checked_in_at' => Carbon::parse($checkInDate)->addHours(14)->toDateTimeString(),
            'checked_out_at' => Carbon::parse($checkOutDate)->addHours(12)->toDateTimeString(),
            'check_in_photo' => $inPhoto1,
            'check_out_photo' => $outPhoto1,
            'notes' => 'Checked in smoothly. Check-out complete, room was clean.',
        ]);

        Review::create([
            'booking_id' => $booking1->id,
            'user_id' => $customers[0]->id,
            'rating' => 5,
            'comment' => 'Tempatnya bersih banget, dekat sekali dengan kampus UNEJ. Pelayanan admin mas Ahmad luar biasa ramah dan fast response. Wi-Fi kencang cocok buat ngerjain tugas.',
            'invisible' => false,
        ]);


        // --- Booking 2: Confirmed / Active (Sedang Menginap) ---
        $b2RoomType = $roomTypes[1]; // Branch 1 Deluxe
        $b2Room = $getRoomForType($b2RoomType->id);
        $b2Room->update(['is_filled' => true]);

        $checkInDate2 = Carbon::now()->subDays(10)->toDateString();
        $checkOutDate2 = Carbon::now()->addDays(20)->toDateString();
        $totalNights2 = 30;
        $totalPrice2 = $b2RoomType->price * $totalNights2;

        $booking2 = Booking::create([
            'user_id' => $customers[1]->id,
            'room_type_id' => $b2RoomType->id,
            'room_id' => $b2Room->id,
            'assigned_by' => $admins[0]->id,
            'assigned_at' => Carbon::now()->subDays(12)->toDateTimeString(),
            'check_in_date' => $checkInDate2,
            'check_out_date' => $checkOutDate2,
            'total_nights' => $totalNights2,
            'total_price' => $totalPrice2,
            'status' => 'confirmed',
            'notes' => 'Mohon dipersiapkan AC yang dingin ya kak.',
        ]);

        $proof2 = $this->seedImage('payment-proofs', 'proof_2.jpg', $proofUrl, 'Payment Proof 2');
        Payment::create([
            'booking_id' => $booking2->id,
            'code' => 'PAY-' . Carbon::now()->subDays(12)->timestamp . '-02',
            'status' => 'paid',
            'proof_image' => $proof2,
            'handled_by' => $admins[0]->id,
            'handled_at' => Carbon::now()->subDays(12)->toDateTimeString(),
            'paid_at' => Carbon::now()->subDays(12)->toDateTimeString(),
        ]);

        $inPhoto2 = $this->seedImage('check-in-photos', 'check_in_2.jpg', $inUrl, 'Check In Photo 2');
        CheckInOut::create([
            'booking_id' => $booking2->id,
            'handled_by' => $admins[0]->id,
            'checked_in_at' => Carbon::parse($checkInDate2)->addHours(14)->toDateTimeString(),
            'checked_out_at' => null,
            'check_in_photo' => $inPhoto2,
            'check_out_photo' => null,
            'notes' => 'Proses check-in berjalan lancar. Tamu menerima kunci fisik kamar ' . $b2Room->number . '.',
        ]);


        // --- Booking 3: Pending Payment (Menunggu Konfirmasi Bayar) ---
        $b3RoomType = $roomTypes[2]; // Branch 2 Standard
        $checkInDate3 = Carbon::now()->addDays(5)->toDateString();
        $checkOutDate3 = Carbon::now()->addDays(15)->toDateString();
        $totalNights3 = 10;
        $totalPrice3 = $b3RoomType->price * $totalNights3;

        $booking3 = Booking::create([
            'user_id' => $customers[2]->id,
            'room_type_id' => $b3RoomType->id,
            'room_id' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'check_in_date' => $checkInDate3,
            'check_out_date' => $checkOutDate3,
            'total_nights' => $totalNights3,
            'total_price' => $totalPrice3,
            'status' => 'pending',
            'notes' => 'Apakah ada tempat jemuran dekat kamar?',
        ]);

        $proof3 = $this->seedImage('payment-proofs', 'proof_3.jpg', $proofUrl, 'Payment Proof 3');
        Payment::create([
            'booking_id' => $booking3->id,
            'code' => 'PAY-' . Carbon::now()->subDays(1)->timestamp . '-03',
            'status' => 'pending',
            'proof_image' => $proof3,
            'handled_by' => null,
            'handled_at' => null,
            'paid_at' => null,
        ]);


        // --- Booking 4: Cancelled ---
        $b4RoomType = $roomTypes[3]; // Branch 2 Deluxe
        $checkInDate4 = Carbon::now()->subDays(5)->toDateString();
        $checkOutDate4 = Carbon::now()->addDays(5)->toDateString();
        $totalNights4 = 10;
        $totalPrice4 = $b4RoomType->price * $totalNights4;

        $booking4 = Booking::create([
            'user_id' => $customers[3]->id,
            'room_type_id' => $b4RoomType->id,
            'room_id' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'check_in_date' => $checkInDate4,
            'check_out_date' => $checkOutDate4,
            'total_nights' => $totalNights4,
            'total_price' => $totalPrice4,
            'status' => 'cancelled',
            'notes' => 'Salah pesan tanggal.',
        ]);

        $proof4 = $this->seedImage('payment-proofs', 'proof_4.jpg', $proofUrl, 'Payment Proof 4');
        Payment::create([
            'booking_id' => $booking4->id,
            'code' => 'PAY-' . Carbon::now()->subDays(6)->timestamp . '-04',
            'status' => 'failed',
            'proof_image' => $proof4,
            'rejection_reason' => 'Bukti transfer blur dan tidak terbaca nominalnya.',
            'handled_by' => $admins[1]->id,
            'handled_at' => Carbon::now()->subDays(5)->toDateTimeString(),
            'paid_at' => null,
        ]);


        // --- Booking 5: Confirmed / Upcoming ---
        $b5RoomType = $roomTypes[5]; // Branch 3 Deluxe
        $b5Room = $getRoomForType($b5RoomType->id);

        $checkInDate5 = Carbon::now()->addDays(2)->toDateString();
        $checkOutDate5 = Carbon::now()->addDays(32)->toDateString();
        $totalNights5 = 30;
        $totalPrice5 = $b5RoomType->price * $totalNights5;

        $booking5 = Booking::create([
            'user_id' => $customers[4]->id,
            'room_type_id' => $b5RoomType->id,
            'room_id' => $b5Room->id,
            'assigned_by' => $admins[2]->id,
            'assigned_at' => Carbon::now()->subDays(1)->toDateTimeString(),
            'check_in_date' => $checkInDate5,
            'check_out_date' => $checkOutDate5,
            'total_nights' => $totalNights5,
            'total_price' => $totalPrice5,
            'status' => 'confirmed',
            'notes' => 'Saya bawa kendaraan roda dua ya mbak/mas.',
        ]);

        $proof5 = $this->seedImage('payment-proofs', 'proof_5.jpg', $proofUrl, 'Payment Proof 5');
        Payment::create([
            'booking_id' => $booking5->id,
            'code' => 'PAY-' . Carbon::now()->subDays(1)->timestamp . '-05',
            'status' => 'paid',
            'proof_image' => $proof5,
            'handled_by' => $admins[2]->id,
            'handled_at' => Carbon::now()->subDays(1)->toDateTimeString(),
            'paid_at' => Carbon::now()->subDays(1)->toDateTimeString(),
        ]);
    }
}
