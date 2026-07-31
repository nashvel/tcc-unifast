<?php

namespace Tests\Unit;

use App\Support\FaceDescriptorMath;
use Illuminate\Validation\ValidationException;
use Tests\Support\FaceDescriptorFixtures;
use Tests\TestCase;

class FaceDescriptorMathTest extends TestCase
{
    use FaceDescriptorFixtures;

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
}
