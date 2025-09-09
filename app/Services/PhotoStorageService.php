<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Exception;

class PhotoStorageService
{
    private const PHOTO_DIRECTORY = 'photos';
    private const THUMBNAIL_DIRECTORY = 'photos/thumbnails';
    private const MAX_IMAGE_WIDTH = 1920;
    private const MAX_IMAGE_HEIGHT = 1080;
    private const THUMBNAIL_SIZE = 300;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'image/webp'
    ];

    public function storePhoto(UploadedFile $file, string $albumId = null): array
    {
        $this->validateFile($file);

        $filename = $this->generateFilename($file);
        $path = $this->storeOriginalImage($file, $filename);
        $thumbnailPath = $this->createThumbnail($file, $filename);
        
        return [
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    public function deletePhoto(string $path, ?string $thumbnailPath = null): bool
    {
        $deleted = true;

        // Delete main photo
        if ($path && Storage::disk('public')->exists($path)) {
            $deleted = Storage::disk('public')->delete($path) && $deleted;
        }

        // Delete thumbnail
        if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
            $deleted = Storage::disk('public')->delete($thumbnailPath) && $deleted;
        }

        return $deleted;
    }

    public function getPhotoUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public function getThumbnailUrl(?string $thumbnailPath): ?string
    {
        if (!$thumbnailPath) {
            return null;
        }

        return Storage::disk('public')->url($thumbnailPath);
    }

    public function createZipArchive(array $photoPaths, string $zipName): string
    {
        $zip = new \ZipArchive();
        $zipPath = storage_path('app/temp/' . $zipName);
        
        // Ensure temp directory exists
        $tempDir = dirname($zipPath);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Cannot create zip file');
        }

        foreach ($photoPaths as $photoPath => $filename) {
            $fullPath = storage_path('app/public/' . $photoPath);
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $filename);
            }
        }

        $zip->close();
        
        return $zipPath;
    }

    public function extractExifData(UploadedFile $file): ?array
    {
        if (!function_exists('exif_read_data') || !str_starts_with($file->getMimeType(), 'image/')) {
            return null;
        }

        $exifData = @exif_read_data($file->getRealPath());
        
        if (!$exifData) {
            return null;
        }

        return [
            'camera' => [
                'make' => $exifData['Make'] ?? null,
                'model' => $exifData['Model'] ?? null,
                'software' => $exifData['Software'] ?? null,
            ],
            'settings' => [
                'iso' => $exifData['ISOSpeedRatings'] ?? null,
                'aperture' => $exifData['FNumber'] ?? null,
                'shutter_speed' => $exifData['ExposureTime'] ?? null,
                'focal_length' => $exifData['FocalLength'] ?? null,
                'flash' => isset($exifData['Flash']) ? ($exifData['Flash'] & 1) > 0 : null,
            ],
            'datetime_original' => $exifData['DateTimeOriginal'] ?? null,
            'gps' => $this->extractGpsData($exifData),
            'raw' => $exifData, // Keep raw data for future use
        ];
    }

    private function validateFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception('Invalid file upload');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new Exception('Unsupported file type: ' . $file->getMimeType());
        }

        // Additional size check (Laravel validation should catch this too)
        if ($file->getSize() > 10 * 1024 * 1024) { // 10MB
            throw new Exception('File too large');
        }
    }

    private function generateFilename(UploadedFile $file): string
    {
        $timestamp = now()->timestamp;
        $random = bin2hex(random_bytes(8));
        $extension = $file->getClientOriginalExtension();
        
        return "{$timestamp}_{$random}.{$extension}";
    }

    private function storeOriginalImage(UploadedFile $file, string $filename): string
    {
        // Process and optimize the image
        $image = Image::make($file);
        
        // Resize if too large while maintaining aspect ratio
        if ($image->width() > self::MAX_IMAGE_WIDTH || $image->height() > self::MAX_IMAGE_HEIGHT) {
            $image->resize(self::MAX_IMAGE_WIDTH, self::MAX_IMAGE_HEIGHT, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        // Auto-orient based on EXIF data
        $image->orientate();

        // Optimize quality
        $quality = $this->getOptimalQuality($file->getMimeType());
        
        $path = self::PHOTO_DIRECTORY . '/' . $filename;
        $fullPath = storage_path('app/public/' . $path);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $image->save($fullPath, $quality);
        
        return $path;
    }

    private function createThumbnail(UploadedFile $file, string $filename): string
    {
        $thumbnailFilename = 'thumb_' . $filename;
        $thumbnailPath = self::THUMBNAIL_DIRECTORY . '/' . $thumbnailFilename;
        $fullThumbnailPath = storage_path('app/public/' . $thumbnailPath);
        
        // Ensure directory exists
        $thumbnailDir = dirname($fullThumbnailPath);
        if (!file_exists($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        // Create square thumbnail
        $image = Image::make($file);
        $image->fit(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE);
        $image->save($fullThumbnailPath, 85); // Good quality for thumbnails
        
        return $thumbnailPath;
    }

    private function getOptimalQuality(string $mimeType): int
    {
        return match ($mimeType) {
            'image/jpeg' => 90,
            'image/png' => 9,   // PNG compression level (0-9)
            'image/webp' => 85,
            default => 90
        };
    }

    private function extractGpsData(array $exifData): ?array
    {
        if (!isset($exifData['GPSLatitude']) || !isset($exifData['GPSLongitude'])) {
            return null;
        }

        $latitude = $this->convertGpsCoordinate(
            $exifData['GPSLatitude'],
            $exifData['GPSLatitudeRef']
        );
        
        $longitude = $this->convertGpsCoordinate(
            $exifData['GPSLongitude'], 
            $exifData['GPSLongitudeRef']
        );

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'altitude' => $exifData['GPSAltitude'] ?? null,
        ];
    }

    private function convertGpsCoordinate(array $coordinate, string $hemisphere): ?float
    {
        if (count($coordinate) !== 3) {
            return null;
        }

        $degrees = count($coordinate) > 0 ? $this->gpsToDecimal($coordinate[0]) : 0;
        $minutes = count($coordinate) > 1 ? $this->gpsToDecimal($coordinate[1]) : 0;
        $seconds = count($coordinate) > 2 ? $this->gpsToDecimal($coordinate[2]) : 0;

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        // Convert to negative if Southern/Western hemisphere
        if (in_array($hemisphere, ['S', 'W'])) {
            $decimal *= -1;
        }

        return $decimal;
    }

    private function gpsToDecimal($coordPart): float
    {
        if (strpos($coordPart, '/') !== false) {
            $parts = explode('/', $coordPart);
            if (count($parts) === 2 && $parts[1] != 0) {
                return floatval($parts[0]) / floatval($parts[1]);
            }
        }
        
        return floatval($coordPart);
    }

    public function getStorageStats(): array
    {
        $photoDirectory = storage_path('app/public/' . self::PHOTO_DIRECTORY);
        
        $totalSize = 0;
        $fileCount = 0;
        
        if (is_dir($photoDirectory)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($photoDirectory)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                    $fileCount++;
                }
            }
        }

        return [
            'total_files' => $fileCount,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'available_space_mb' => disk_free_space(storage_path('app/public')),
        ];
    }
}