<?php
// Script to strictly inject independent bugs into distinct API methods
$dir = __DIR__ . '/../backend/app/Http/Controllers/Api/V1/';

$targets = [
    [
        'file' => 'DashboardController.php',
        'method' => 'public function summary()',
        'bug' => "\n        // QA BUG: Break Dashboard Summary\n        return \\Core\\ApiResponse::serverError('Failed to load summary metrics');"
    ],
    [
        'file' => 'DashboardController.php',
        'method' => 'public function operations()',
        'bug' => "\n        // QA BUG: Break Operations Overview\n        return \\Core\\ApiResponse::validationError('Invalid time range for operations');"
    ],
    [
        'file' => 'CartController.php',
        'method' => 'public function addToCart()',
        'bug' => "\n        // QA BUG: Break Add to Cart\n        return \\Core\\ApiResponse::validationError('Product quantity exceeds stock limit');"
    ],
    [
        'file' => 'CartController.php',
        'method' => 'public function removeItem($id = null)',
        'bug' => "\n        // QA BUG: Break Remove Item\n        return \\Core\\ApiResponse::notFound('Cart item not found in session');"
    ],
    [
        'file' => 'InventoryController.php',
        'method' => 'public function index()',
        'bug' => "\n        // QA BUG: Break Production Queue\n        return \\Core\\ApiResponse::serverError('Database connection lost while fetching queue');"
    ],
    [
        'file' => 'AdminController.php',
        'method' => 'public function listStaff()',
        'bug' => "\n        // QA BUG: Break List Staff\n        return \\Core\\ApiResponse::unauthorized('You do not have permission to view staff list');"
    ],
    [
        'file' => 'AddressController.php',
        'method' => 'public function store()',
        'bug' => "\n        // QA BUG: Break Add Address\n        return \\Core\\ApiResponse::validationError('Invalid phone number format');"
    ],
    [
        'file' => 'SupportTicketController.php',
        'method' => 'public function store()',
        'bug' => "\n        // QA BUG: Break Create Ticket\n        return \\Core\\ApiResponse::serverError('SMTP server not responding, cannot create ticket');"
    ],
    [
        'file' => 'SupportTicketController.php',
        'method' => 'public function index()',
        'bug' => "\n        // QA BUG: Break Get Tickets\n        return \\Core\\ApiResponse::serverError('Failed to parse ticket filters');"
    ],
    [
        'file' => 'SalesController.php',
        'method' => 'public function reports()',
        'bug' => "\n        // QA BUG: Break Sales Report\n        return \\Core\\ApiResponse::serverError('Report generation timed out');"
    ],
    [
        'file' => 'OrderController.php',
        'method' => 'public function index()',
        'bug' => "\n        // QA BUG: Break List All Orders\n        return \\Core\\ApiResponse::serverError('Memory limit exhausted while fetching orders');"
    ]
];

foreach ($targets as $target) {
    $path = $dir . $target['file'];
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        $search = $target['method'] . "\n    {";
        $replace = $target['method'] . "\n    {" . $target['bug'];
        
        $newContent = str_replace($search, $replace, $content);
        if ($newContent !== $content) {
            file_put_contents($path, $newContent);
            echo "Injected bug into {$target['file']} -> {$target['method']}\n";
            continue;
        }
        
        // try windows line endings
        $search2 = $target['method'] . "\r\n    {";
        $replace2 = $target['method'] . "\r\n    {" . str_replace("\n", "\r\n", $target['bug']);
        $newContent2 = str_replace($search2, $replace2, $content);
        if ($newContent2 !== $content) {
            file_put_contents($path, $newContent2);
            echo "Injected bug into {$target['file']} -> {$target['method']}\n";
        } else {
            echo "Failed to find method in {$target['file']} -> {$target['method']}\n";
        }
    } else {
        echo "File not found: {$target['file']}\n";
    }
}
echo "Done!\n";
