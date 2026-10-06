<?php

use Illuminate\Support\Facades\DB;

test('sqlite waits for another writer instead of failing at once', function () {
    expect(DB::connection()->getConfig('busy_timeout'))->toBe(5000)
        ->and(DB::selectOne('PRAGMA busy_timeout')->timeout)->toBe(5000);
});

test('sqlite transactions begin as writes, so they wait for the lock instead of being refused', function () {
    expect(DB::connection()->getConfig('transaction_mode'))->toBe('IMMEDIATE');
});

test('sqlite keeps a write-ahead log, so readers do not wait for writers', function () {
    // The in-memory database of the tests can only report "memory".
    expect(DB::selectOne('PRAGMA journal_mode')->journal_mode)->toBeIn(['wal', 'memory'])
        ->and((int) DB::selectOne('PRAGMA synchronous')->synchronous)->toBe(1);
});
