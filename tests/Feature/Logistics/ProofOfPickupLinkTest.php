<?php

namespace Tests\Feature\Logistics;

use App\Support\ProtectedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Proof-of-pickup photos open through a signed link. */
class ProofOfPickupLinkTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_proof_of_pickup_photos_open_through_a_signed_link(): void
    {
        $this->assertTrue(ProtectedFile::isProtected('proof-of-pickup/photo.jpg'));
        $this->assertStringContainsString('/files/proof-of-pickup/photo.jpg', ProtectedFile::url('proof-of-pickup/photo.jpg'));
    }
}
