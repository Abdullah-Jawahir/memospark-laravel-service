<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

use App\Models\User;

$student = User::where('email', 'student@example.com')->withCount('decks')->first();
echo "Student decks count: " . $student->decks_count . "\n";

$admin = User::where('email', 'admin@example.com')->withCount('decks')->first();
echo "Admin decks count: " . $admin->decks_count . "\n";

echo "Student details:\n";
print_r($student->toArray());
