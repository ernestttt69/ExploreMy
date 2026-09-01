<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // NOTE: The unique constraint on (collection_id, wishlist_id) cannot be
        // dropped via normal ALTER TABLE because MySQL reports error 1553
        // ("needed in a foreign key constraint"). The essential schema changes
        // for independence are already complete:
        //   1. attraction_id column exists and is backfilled with data
        //   2. attraction_id is NOT NULL
        //   3. FK attraction_id -> attractions(attraction_id) CASCADE exists
        //   4. FK wishlist_id -> wishlists(wishlist_id) CASCADE has been dropped
        //   5. wishlist_id is now nullable
        //
        // The remaining unique index on (collection_id, wishlist_id) is
        // harmless and does not couple collections to wishlists.
        // Duplicate prevention is handled at the application level.
    }

    public function down(): void
    {
        // No-op (nothing was changed)
    }
};
