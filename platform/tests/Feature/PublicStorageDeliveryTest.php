<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageDeliveryTest extends TestCase
{
    public function test_it_streams_an_existing_file_from_the_public_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reports/result.txt', 'tembo-storage-ok');

        $this->get('/storage/reports/result.txt')
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=result.txt')
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertStreamedContent('tembo-storage-ok');
    }

    public function test_it_returns_not_found_for_missing_or_hidden_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('.private', 'not public');

        $this->get('/storage/missing.txt')->assertNotFound();
        $this->get('/storage/.private')->assertNotFound();
    }
}
