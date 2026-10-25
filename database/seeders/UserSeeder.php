<?php

namespace Database\Seeders;

use App\Models\User;
use Core\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Birden çok kez çalıştırılabilir: var olan kayıtlara dokunmaz. */
    public function run()
    {
        // 1. Sistemin ilk yöneticisi (ilk girişten sonra şifreyi değiştirin!)
        $this->createOnce('admin', 'admin@markuvo.com', '123456', 'admin');

        // 2. Test kullanıcıları
        for ($i = 1; $i <= 10; $i++) {
            $this->createOnce("test_user_{$i}", "user{$i}@markuvo.com", 'password', 'user');
        }
    }

    protected function createOnce(string $username, string $email, string $password, string $role): void
    {
        if (User::where('email', $email)->exists()) {
            return;
        }

        User::create([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
        ]);
    }
}
