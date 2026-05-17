<?php

declare(strict_types=1);

namespace DotEnvIt\Collage;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class Collage
{
    protected array $imagePaths = [];

    protected int $canvasWidth;

    protected int $padding;

    protected int $canvasHeight;

    protected string $disk;

    protected ?string $absolutePath = null;

    protected ?string $relativePath = null;

    public function __construct()
    {
        // Read directly from config framework with structural class defaults
        $this->canvasWidth  = config('collage.canvas_width', 1600);
        $this->canvasHeight = config('collage.canvas_height', 1600);
        $this->padding      = config('collage.padding', 12);
        $this->disk         = config('collage.disk', 'public');
    }

    public static function make(): self
    {
        return new static;
    }

    // Optional fluid-chain modifiers if you want to override settings at runtime:
    public function disk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function width(int $width): self
    {
        $this->canvasWidth = $width;

        return $this;
    }

    public function height(int $height): self
    {
        $this->canvasHeight = $height;

        return $this;
    }

    public function from(array $imagePaths): self
    {
        $this->imagePaths = $imagePaths;

        return $this;
    }

    public function save(string $destinationPath): self
    {
        if (empty($this->imagePaths)) {
            return $this;
        }

        $count = count($this->imagePaths);

        $imageMeta = [];
        foreach (array_values($this->imagePaths) as $path) {
            if (! file_exists($path)) {
                continue;
            }
            [$width, $height] = getimagesize($path);
            $imageMeta[]      = ['path' => $path, 'width' => $width, 'height' => $height];
        }

        if (empty($imageMeta)) {
            return $this;
        }

        if ($count === 3) {
            $canvas = $this->buildThreeImageLayout($imageMeta);
        } else {
            $canvas = $this->buildFallbackLayout($imageMeta, $count);
        }

        Storage::disk($this->disk)->makeDirectory(dirname($destinationPath));
        $this->absolutePath = Storage::disk($this->disk)->path($destinationPath);

        $canvas->save($this->absolutePath);
        $canvas->destroy();

        $this->relativePath = $destinationPath;

        return $this;
    }

    /** Get the absolute local disk directory file path of the saved collage. */
    public function getPath(): ?string
    {
        return $this->absolutePath;
    }

    /** Get the publicly accessible HTTP application asset URL of the saved collage. */
    public function getUrl(): ?string
    {
        if (! $this->relativePath) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->relativePath);
    }

    protected function buildThreeImageLayout(array $imageMeta): \Intervention\Image\Image
    {
        $first = $imageMeta[0];

        if ($first['width'] >= $first['height']) {
            $usableWidth = $this->canvasWidth - ($this->padding * 2);
            $colWidth    = floor(($this->canvasWidth - ($this->padding * 3)) / 2);

            $bottom1_height        = (int) (($imageMeta[1]['height'] / $imageMeta[1]['width']) * $colWidth);
            $bottom2_height        = (int) (($imageMeta[2]['height'] / $imageMeta[2]['width']) * $colWidth);
            $targetBottomRowHeight = max($bottom1_height, $bottom2_height);

            $topScaledHeight  = (int) (($first['height'] / $first['width']) * $usableWidth);
            $calculatedHeight = $topScaledHeight + $targetBottomRowHeight + ($this->padding * 3);

            if ($calculatedHeight > $this->canvasHeight) {
                $scaleRatio            = $this->canvasHeight / $calculatedHeight;
                $topScaledHeight       = (int) ($topScaledHeight * $scaleRatio);
                $targetBottomRowHeight = (int) ($targetBottomRowHeight * $scaleRatio);
                $finalHeight           = $this->canvasHeight;
            } else {
                $finalHeight = $calculatedHeight;
            }

            $canvas = Image::canvas($this->canvasWidth, $finalHeight, '#ffffff');

            $tile1 = Image::make($first['path'])->fit($usableWidth, $topScaledHeight);
            $canvas->insert($tile1, 'top-left', $this->padding, $this->padding)->destroy();

            $yOffsetForBottomRow = $this->padding + $topScaledHeight + $this->padding;

            $tile2 = Image::make($imageMeta[1]['path'])->fit($colWidth, $targetBottomRowHeight);
            $canvas->insert($tile2, 'top-left', $this->padding, $yOffsetForBottomRow)->destroy();

            $tile3 = Image::make($imageMeta[2]['path'])->fit($colWidth, $targetBottomRowHeight);
            $canvas->insert($tile3, 'top-left', $this->padding + $colWidth + $this->padding, $yOffsetForBottomRow)->destroy();
        } else {
            $colWidth = floor(($this->canvasWidth - ($this->padding * 3)) / 2);

            $right1_scaled_height = (int) (($imageMeta[1]['height'] / $imageMeta[1]['width']) * $colWidth);
            $right2_scaled_height = (int) (($imageMeta[2]['height'] / $imageMeta[2]['width']) * $colWidth);
            $calculatedLeftHeight = $right1_scaled_height + $right2_scaled_height + $this->padding;

            if ($calculatedLeftHeight > $this->canvasHeight) {
                $scaleRatio           = $this->canvasHeight / $calculatedLeftHeight;
                $right1_scaled_height = (int) ($right1_scaled_height * $scaleRatio);
                $right2_scaled_height = (int) ($right2_scaled_height * $scaleRatio);
                $targetLeftHeight     = $this->canvasHeight;
            } else {
                $targetLeftHeight = $calculatedLeftHeight;
            }

            $finalHeight = $targetLeftHeight + ($this->padding * 2);
            $canvas      = Image::canvas($this->canvasWidth, $finalHeight, '#ffffff');

            $tile1 = Image::make($first['path'])->fit($colWidth, $targetLeftHeight);
            $canvas->insert($tile1, 'top-left', $this->padding, $this->padding)->destroy();

            $tile2 = Image::make($imageMeta[1]['path'])->fit($colWidth, $right1_scaled_height);
            $canvas->insert($tile2, 'top-left', $colWidth + ($this->padding * 2), $this->padding)->destroy();

            $yOffsetForBottom = $this->padding + $right1_scaled_height + $this->padding;
            $tile3            = Image::make($imageMeta[2]['path'])->fit($colWidth, $right2_scaled_height);
            $canvas->insert($tile3, 'top-left', $colWidth + ($this->padding * 2), $yOffsetForBottom)->destroy();
        }

        return $canvas;
    }

    protected function buildFallbackLayout(array $imageMeta, int $count): \Intervention\Image\Image
    {
        $rowsData = [];

        if ($count % 2 !== 0) {
            $first = $imageMeta[0];
            if ($first['width'] >= $first['height']) {
                $rowsData[] = [
                    'type'   => 'single-row',
                    'images' => [$first],
                    'height' => (int) (($first['height'] / $first['width']) * ($this->canvasWidth - ($this->padding * 2))),
                ];
                array_shift($imageMeta);
            } else {
                $targetWidth = floor(($this->canvasWidth - ($this->padding * 3)) * 0.4);
                $rowsData[]  = [
                    'type'   => 'side-portrait',
                    'images' => [$first],
                    'width'  => $targetWidth,
                    'height' => (int) (($first['height'] / $first['width']) * $targetWidth),
                ];
                array_shift($imageMeta);
            }
        }

        $pairs = array_chunk($imageMeta, 2);
        foreach ($pairs as $pair) {
            if (count($pair) === 2) {
                $targetHeight = min($pair[0]['height'], $pair[1]['height']);
                if ($targetHeight > 450) {
                    $targetHeight = 450;
                }

                $rowsData[] = ['type' => 'pair-row', 'images' => $pair, 'height' => $targetHeight];
            } else {
                $single     = $pair[0];
                $rowsData[] = [
                    'type'   => 'single-row',
                    'images' => [$single],
                    'height' => (int) (($single['height'] / $single['width']) * ($this->canvasWidth - ($this->padding * 2))),
                ];
            }
        }

        $totalCanvasHeight  = $this->padding * 2;
        $portraitSideHeight = 0;
        foreach ($rowsData as $row) {
            if ($row['type'] === 'side-portrait') {
                $portraitSideHeight = $row['height'];
            } else {
                $totalCanvasHeight += $row['height'] + $this->padding;
            }
        }

        $calculatedHeight = max($totalCanvasHeight, $portraitSideHeight + ($this->padding * 2));

        $globalScaleRatio = 1.0;
        if ($calculatedHeight > $this->canvasHeight) {
            $globalScaleRatio = $this->canvasHeight / $calculatedHeight;
            $finalHeight      = $this->canvasHeight;
        } else {
            $finalHeight = $calculatedHeight;
        }

        $canvas             = Image::canvas($this->canvasWidth, $finalHeight, '#ffffff');
        $currentY           = $this->padding;
        $leftOffsetForPairs = $this->padding;
        $pairWidthModifier  = $this->canvasWidth;

        foreach ($rowsData as $row) {
            if ($row['type'] === 'side-portrait') {
                $scaledWidth  = $row['width'];
                $scaledHeight = (int) ($row['height'] * $globalScaleRatio);

                $tile = Image::make($row['images'][0]['path'])->fit($scaledWidth, $scaledHeight);
                $canvas->insert($tile, 'top-left', $this->padding, $this->padding)->destroy();

                $leftOffsetForPairs = $scaledWidth + ($this->padding * 2);
                $pairWidthModifier  = $this->canvasWidth - $scaledWidth - $this->padding;
            }
        }

        foreach ($rowsData as $row) {
            if ($row['type'] === 'side-portrait') {
                continue;
            }

            $rowHeight = (int) ($row['height'] * $globalScaleRatio);

            if ($row['type'] === 'single-row') {
                $targetWidth = $this->canvasWidth - ($this->padding * 2);
                $tile        = Image::make($row['images'][0]['path'])->fit($targetWidth, $rowHeight);
                $canvas->insert($tile, 'top-left', $this->padding, $currentY)->destroy();
                $currentY += $rowHeight + $this->padding;
            }

            if ($row['type'] === 'pair-row') {
                $availableWidth = $pairWidthModifier - ($this->padding * 3);
                $halfWidth      = floor($availableWidth / 2);

                $tile1 = Image::make($row['images'][0]['path'])->fit($halfWidth + $this->padding, $rowHeight);
                $canvas->insert($tile1, 'top-left', $leftOffsetForPairs, $currentY)->destroy();

                $tile2                 = Image::make($row['images'][1]['path'])->fit($halfWidth - $this->padding, $rowHeight);
                $xOffsetForSecondImage = $leftOffsetForPairs + $pairWidthModifier - $halfWidth - $this->padding;
                $canvas->insert($tile2, 'top-left', $xOffsetForSecondImage, $currentY)->destroy();

                $currentY += $rowHeight + $this->padding;
            }
        }

        return $canvas;
    }
}
