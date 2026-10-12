<?php

return [

    /*
     * Exports with at most this many rows are generated while the user waits. Bigger ones run in the
     * background on the "exports" queue and show up on the Exports page when ready.
     */
    'sync_limit' => (int) env('EXPORTS_SYNC_LIMIT', 5000),

    /*
     * PDFs take much longer to lay out (about 4 ms per row), so they go to the background sooner.
     */
    'pdf_sync_limit' => (int) env('EXPORTS_PDF_SYNC_LIMIT', 1000),

    /*
     * PDFs hold the whole document in memory, so they stop at this many rows. Excel streams and goes much further.
     */
    'pdf_max_rows' => (int) env('EXPORTS_PDF_MAX_ROWS', 10000),

    /*
     * Excel sheets cannot hold more than 1,048,576 rows including the heading rows.
     */
    'xlsx_max_rows' => (int) env('EXPORTS_XLSX_MAX_ROWS', 1000000),

    'chunk_size' => (int) env('EXPORTS_CHUNK_SIZE', 1000),

    /*
     * Finished files are deleted after this many days.
     */
    'keep_days' => (int) env('EXPORTS_KEEP_DAYS', 7),

    'queue' => env('EXPORTS_QUEUE', 'exports'),

    'disk' => env('EXPORTS_DISK', 'local'),

    'memory_limit' => env('EXPORTS_MEMORY_LIMIT', '768M'),
];
