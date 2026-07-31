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
}
