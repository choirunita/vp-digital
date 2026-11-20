<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['maker', 'Maker@2026', 'Staf Keuangan', 'Staf Keuangan', 'maker'],
            ['checker', 'Checker@2026', 'Manager Keuangan', 'Manager Keuangan', 'checker'],
            ['approver1', 'Approver1@2026', 'Direktur Keuangan', 'Direktur Keuangan', 'approver1'],
            ['approver2', 'Approver2@2026', 'Direktur Utama', 'Direktur Utama', 'approver2'],
            ['sesdir', 'Sesdir@2026', 'Sekretaris Direksi', 'Sekretaris Direksi', 'sesdir'],
            ['admin', 'Admin@2026', 'Admin Sistem', 'Tim TI', 'admin'],
            ['guest', 'Guest@2026', 'Tamu (demo)', 'Hanya melihat', 'guest'],
        ] as [$username, $password, $fullName, $title, $role]) {
            User::updateOrCreate(['username' => $username], [
                'password' => Hash::make($password), 'full_name' => $fullName, 'title' => $title,
                'role' => $role, 'active' => true, 'must_change_pw' => false,
            ]);
        }
    }
}
