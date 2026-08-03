<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenCvRuntimeAssetTest extends TestCase
{
    public function test_the_pinned_opencv_runtime_is_shipped_with_the_public_application(): void
    {
        $runtime = public_path('vendor/opencv/opencv-4.8.0.js');

        $this->assertFileExists($runtime);
        $this->assertGreaterThan(9_000_000, filesize($runtime));
        $this->assertSame(
            '806cb5646afa6fa946b736afa1beaf1443bdfda718404d75f9794ddc2c10b1cc',
            hash_file('sha256', $runtime),
        );
    }
}
