<?php

// Helper for PaymentRecorderTest: records one payment in its own process.
require __DIR__.'/../../vendor/autoload.php';

$recorder = new App\Payment\PaymentRecorder(new App\Payment\Database($argv[1]));
try {
    $recorder->record(1, 10.0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
