<?php

namespace Tests\Support;

trait FaceDescriptorFixtures
{
    /**
     * @return list<float>
     */
    protected function faceDescriptor(int $hotIndex = 0): array
    {
        $raw = array_fill(0, 128, 0.0);
        $raw[$hotIndex % 128] = 1.0;

        return $raw;
    }

    /**
     * Unit vector at a known Euclidean distance from faceDescriptor($baseHot).
     *
     * @return list<float>
     */
    protected function faceDescriptorAtDistance(float $distance, int $baseHot = 0): array
    {
        $distance = max(0.0, min(2.0, $distance));
        $sinHalf = min(1.0, $distance / 2.0);
        $cosHalf = sqrt(max(0.0, 1.0 - ($sinHalf * $sinHalf)));
        $sinTheta = 2.0 * $sinHalf * $cosHalf;
        $cosTheta = 1.0 - (2.0 * $sinHalf * $sinHalf);

        $raw = array_fill(0, 128, 0.0);
        $raw[$baseHot % 128] = $cosTheta;
        $raw[($baseHot + 1) % 128] = $sinTheta;

        return $raw;
    }
}
