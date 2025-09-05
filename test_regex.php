<?php

$text = "Which of these is NOT a greenhouse gas? a) Carbon dioxide b) Oxygen c) Methane d) Nitrous oxide.";
echo "Original text: " . $text . PHP_EOL;

// Test the split approach
$parts = preg_split('/\s*[a-z]\)\s*/i', $text);
echo "Parts count: " . count($parts) . PHP_EOL;
echo "Question: " . trim($parts[0]) . PHP_EOL;
echo "Options:" . PHP_EOL;
for ($i = 1; $i < count($parts); $i++) {
  if (!empty(trim($parts[$i]))) {
    // Clean up option text (remove trailing punctuation)
    $option = trim($parts[$i]);
    $option = rtrim($option, '.,;!?');
    echo "  - " . $option . PHP_EOL;
  }
}
