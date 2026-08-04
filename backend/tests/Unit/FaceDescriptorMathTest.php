<?php

namespace Tests\Unit;

use App\Support\FaceDescriptorMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\FaceDescriptorFixtures;
use Tests\TestCase;

class FaceDescriptorMathTest extends TestCase
{
    use FaceDescriptorFixtures;
    use RefreshDatabase;

    public function test_euclidean_distance_is_zero_for_identical_descriptors(): void
    {
        $descriptor = $this->faceDescriptor(2);
        $this->assertSame(0.0, FaceDescriptorMath::euclidean($descriptor, $descriptor));
        $this->assertTrue(FaceDescriptorMath::matches($descriptor, $descriptor));
    }

    public function test_normalize_rejects_wrong_size_and_zero_vectors(): void
    {
        $this->expectException(ValidationException::class);
        FaceDescriptorMath::normalize([1, 2, 3]);
    }

    public function test_classify_three_zones_with_default_thresholds(): void
    {
        config([
            'services.identity.face_pass_max' => 0.45,
            'services.identity.face_review_max' => 0.60,
        ]);

        $this->assertSame(FaceDescriptorMath::ZONE_CONFIDENT, FaceDescriptorMath::classify(0.44));
        $this->assertSame(FaceDescriptorMath::ZONE_CONFIDENT, FaceDescriptorMath::classify(0.0));
        $this->assertSame(FaceDescriptorMath::ZONE_UNCERTAIN, FaceDescriptorMath::classify(0.45));
        $this->assertSame(FaceDescriptorMath::ZONE_UNCERTAIN, FaceDescriptorMath::classify(0.59));
        $this->assertSame(FaceDescriptorMath::ZONE_MISMATCH, FaceDescriptorMath::classify(0.60));
        $this->assertSame(FaceDescriptorMath::ZONE_MISMATCH, FaceDescriptorMath::classify(1.2));

        $this->assertTrue(FaceDescriptorMath::isConfident(0.4));
        $this->assertTrue(FaceDescriptorMath::isUncertain(0.5));
        $this->assertTrue(FaceDescriptorMath::isMismatch(0.7));
    }

    public function test_classify_respects_explicit_thresholds(): void
    {
        $this->assertSame(
            FaceDescriptorMath::ZONE_UNCERTAIN,
            FaceDescriptorMath::classify(0.3, 0.2, 0.4),
        );
        $this->assertSame(
            FaceDescriptorMath::ZONE_CONFIDENT,
            FaceDescriptorMath::classify(0.19, 0.2, 0.4),
        );
    }
}
